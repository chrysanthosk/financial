#!/usr/bin/env bash
set -euo pipefail

# -------------------- helpers --------------------
log()  { echo -e "\n\033[1;32m[INFO]\033[0m $*"; }
warn() { echo -e "\n\033[1;33m[WARN]\033[0m $*"; }
err()  { echo -e "\n\033[1;31m[ERR ]\033[0m $*"; }
die()  { err "$*"; exit 1; }

require_root(){ [[ "${EUID:-$(id -u)}" -eq 0 ]] || die "Run as root: sudo $0"; }

prompt() { # var, msg, default(optional)
  local var="$1" msg="$2" def="${3:-}" val=""
  if [[ -n "$def" ]]; then
    read -r -p "$msg [$def]: " val
    val="${val:-$def}"
  else
    read -r -p "$msg: " val
  fi
  printf -v "$var" "%s" "$val"
}

yesno(){ # msg default(y/n)
  local msg="$1" def="${2:-y}" ans=""
  read -r -p "$msg [${def}]: " ans
  ans="${ans:-$def}"
  [[ "$ans" =~ ^[Yy]$ ]]
}

# Escape value for .env inside double quotes
env_escape() {
  local s="$1"
  s="${s//\\/\\\\}"
  s="${s//\"/\\\"}"
  s="${s//$/\\$}"
  s="${s//$'\n'/\\n}"
  printf "%s" "$s"
}

# -------------------- OS detection --------------------
OS_ID=""
OS_LIKE=""
OS_FAMILY=""  # debian|rhel
detect_os(){
  if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    OS_ID="${ID:-}"
    OS_LIKE="${ID_LIKE:-}"
  fi

  if [[ "$OS_ID" =~ (ubuntu|debian) ]] || [[ "$OS_LIKE" =~ (debian|ubuntu) ]]; then
    OS_FAMILY="debian"
  elif [[ "$OS_ID" =~ (rhel|centos|rocky|almalinux|fedora) ]] || [[ "$OS_LIKE" =~ (rhel|fedora|centos) ]]; then
    OS_FAMILY="rhel"
  else
    die "Unsupported OS. Need Ubuntu/Debian or RHEL/Rocky/Alma."
  fi
}

# -------------------- variables (set by prompts) --------------------
PROJECT_SLUG=""
GIT_REPO=""
GIT_BRANCH="main"

DOMAIN="_"
APP_NAME="Laravel Portal"
APP_URL="http://127.0.0.1"

APP_USER=""
APP_DIR=""

DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME=""
DB_USER=""
DB_PASS=""

ENABLE_HTTPS="no"
SSL_MODE="letsencrypt"          # letsencrypt|existing
CERT_FULLCHAIN=""
CERT_PRIVKEY=""
ADMIN_EMAIL="admin@example.com"

PHP_VER=""                      # e.g. 8.4
PHP_FPM_SERVICE=""
PHP_FPM_SOCK=""

DB_SERVICE="mysql"
WEB_GROUP="www-data"            # Ubuntu/Debian default; on RHEL we will adjust to "nginx"

# -------------------- install packages --------------------
detect_php_version(){
  if command -v php >/dev/null 2>&1; then
    PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
  else
    PHP_VER=""
  fi
}

install_packages_debian(){
  log "Installing packages (Debian/Ubuntu)..."
  export DEBIAN_FRONTEND=noninteractive

  apt-get update -y
  apt-get install -y ca-certificates curl git unzip zip gnupg lsb-release software-properties-common rsync

  apt-get install -y nginx mysql-server

  if [[ "$OS_ID" == "ubuntu" ]]; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
  fi

  detect_php_version
  if [[ -z "$PHP_VER" ]]; then
    if apt-cache show php8.4-cli >/dev/null 2>&1; then PHP_VER="8.4"; else PHP_VER="8.2"; fi
  fi

  log "Using PHP ${PHP_VER}"
  apt-get install -y \
    "php${PHP_VER}" "php${PHP_VER}-cli" "php${PHP_VER}-fpm" \
    "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" "php${PHP_VER}-zip" \
    "php${PHP_VER}-mysql" "php${PHP_VER}-bcmath" "php${PHP_VER}-intl" "php${PHP_VER}-gd"

  PHP_FPM_SERVICE="php${PHP_VER}-fpm"
  PHP_FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
  WEB_GROUP="www-data"

  log "Installing Node.js (LTS)..."
  curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
  apt-get install -y nodejs

  log "Installing Certbot..."
  apt-get install -y certbot python3-certbot-nginx

  # Install official Composer (avoid distro Composer warnings)
  if command -v composer >/dev/null 2>&1; then
    log "Composer detected: $(composer --version | head -n1)"
  else
    log "Installing Composer..."
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  fi
}

install_packages_rhel(){
  log "Installing packages (RHEL/Rocky/Alma)..."
  dnf -y install ca-certificates curl git unzip zip tar rsync

  dnf -y install epel-release || true
  dnf -y install nginx

  log "Installing MariaDB (MySQL-compatible)..."
  dnf -y install mariadb-server mariadb
  DB_SERVICE="mariadb"

  log "Installing PHP + extensions..."
  dnf -y install php php-cli php-fpm php-mbstring php-xml php-curl php-zip php-mysqlnd php-bcmath php-intl php-gd

  PHP_FPM_SERVICE="php-fpm"
  PHP_FPM_SOCK="/run/php-fpm/www.sock"
  WEB_GROUP="nginx"

  log "Installing Node.js (LTS)..."
  dnf -y install nodejs npm || true

  log "Installing Certbot..."
  dnf -y install certbot python3-certbot-nginx || true

  if command -v composer >/dev/null 2>&1; then
    log "Composer detected: $(composer --version | head -n1)"
  else
    log "Installing Composer..."
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
  fi
}

ensure_services(){
  log "Enabling and starting services..."
  systemctl enable nginx || true
  systemctl enable "$PHP_FPM_SERVICE" || true
  systemctl enable "$DB_SERVICE" || true

  systemctl restart "$DB_SERVICE" || true
  systemctl restart "$PHP_FPM_SERVICE" || true

  # Guard: nginx.conf sometimes missing if dpkg previously failed
  if [[ ! -f /etc/nginx/nginx.conf ]]; then
    warn "/etc/nginx/nginx.conf missing; creating minimal default."
    mkdir -p /etc/nginx/{modules-enabled,conf.d,sites-available,sites-enabled}
    cat >/etc/nginx/nginx.conf <<'EOF'
user www-data;
worker_processes auto;
pid /run/nginx.pid;
include /etc/nginx/modules-enabled/*.conf;

events { worker_connections 1024; }

http {
  sendfile on;
  tcp_nopush on;
  tcp_nodelay on;
  keepalive_timeout 65;
  types_hash_max_size 2048;

  include /etc/nginx/mime.types;
  default_type application/octet-stream;

  access_log /var/log/nginx/access.log;
  error_log  /var/log/nginx/error.log;

  gzip on;

  include /etc/nginx/conf.d/*.conf;
  include /etc/nginx/sites-enabled/*;
}
EOF
  fi

  systemctl restart nginx || true
}

ensure_nginx_fastcgi_files(){
  # Some minimal/partial nginx installs may miss fastcgi_params and snippets.
  # Create what we need so vhosts don't depend on distro-specific files.
  if [[ ! -f /etc/nginx/fastcgi_params ]]; then
    log "Creating missing /etc/nginx/fastcgi_params"
    cat >/etc/nginx/fastcgi_params <<'EOF'
fastcgi_param  QUERY_STRING       $query_string;
fastcgi_param  REQUEST_METHOD     $request_method;
fastcgi_param  CONTENT_TYPE       $content_type;
fastcgi_param  CONTENT_LENGTH     $content_length;

fastcgi_param  SCRIPT_NAME        $fastcgi_script_name;
fastcgi_param  REQUEST_URI        $request_uri;
fastcgi_param  DOCUMENT_URI       $document_uri;
fastcgi_param  DOCUMENT_ROOT      $document_root;
fastcgi_param  SERVER_PROTOCOL    $server_protocol;
fastcgi_param  REQUEST_SCHEME     $scheme;
fastcgi_param  HTTPS              $https if_not_empty;

fastcgi_param  GATEWAY_INTERFACE  CGI/1.1;
fastcgi_param  SERVER_SOFTWARE    nginx/$nginx_version;

fastcgi_param  REMOTE_ADDR        $remote_addr;
fastcgi_param  REMOTE_PORT        $remote_port;
fastcgi_param  SERVER_ADDR        $server_addr;
fastcgi_param  SERVER_PORT        $server_port;
fastcgi_param  SERVER_NAME        $server_name;
EOF
  fi

  mkdir -p /etc/nginx/snippets || true
}

# -------------------- app user + directories --------------------
create_app_user_and_dirs(){
  APP_USER="$PROJECT_SLUG"
  APP_DIR="/opt/$PROJECT_SLUG"

  log "Creating app user: $APP_USER"
  if id -u "$APP_USER" >/dev/null 2>&1; then
    warn "User $APP_USER already exists; reusing."
  else
    useradd --system --create-home --shell /bin/bash "$APP_USER"
  fi

  mkdir -p "$APP_DIR"
  chown -R "$APP_USER":"$APP_USER" "$APP_DIR"
}

# -------------------- git clone/update --------------------
clone_or_update_repo(){
  log "Cloning/updating repo..."
  if [[ -d "$APP_DIR/.git" ]]; then
    sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && git fetch --all --prune && git checkout '$GIT_BRANCH' && git pull --ff-only origin '$GIT_BRANCH'"
  else
    sudo -u "$APP_USER" bash -lc "git clone --branch '$GIT_BRANCH' --depth 1 '$GIT_REPO' '$APP_DIR'"
  fi
}

# -------------------- mysql setup --------------------
mysql_exec_root(){
  sudo mysql -u root "$@"
}

create_database_and_user(){
  log "Creating database + user locally..."

  mysql_exec_root -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

  local user_exists
  user_exists="$(mysql_exec_root -N -e "SELECT COUNT(*) FROM mysql.user WHERE user='${DB_USER}' AND host='%';" 2>/dev/null || echo 0)"
  if [[ "$user_exists" == "0" ]]; then
    mysql_exec_root -e "CREATE USER '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';"
  else
    warn "MySQL user ${DB_USER}@% already exists; leaving its password unchanged."
  fi

  mysql_exec_root -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%'; FLUSH PRIVILEGES;"
}

# -------------------- .env generation (100% from install.sh) --------------------
write_full_env(){
  local env_path="$APP_DIR/.env"

  local esc_app_name esc_app_url esc_db_host esc_db_port esc_db_name esc_db_user esc_db_pass
  esc_app_name="$(env_escape "$APP_NAME")"
  esc_app_url="$(env_escape "$APP_URL")"
  esc_db_host="$(env_escape "$DB_HOST")"
  esc_db_port="$(env_escape "$DB_PORT")"
  esc_db_name="$(env_escape "$DB_NAME")"
  esc_db_user="$(env_escape "$DB_USER")"
  esc_db_pass="$(env_escape "$DB_PASS")"

  # Preserve existing APP_KEY if present (rerun-safe)
  local existing_key=""
  if [[ -f "$env_path" ]]; then
    existing_key="$(grep -E '^APP_KEY=' "$env_path" | head -n1 | cut -d= -f2- || true)"
  fi

  sudo -u "$APP_USER" bash -lc "cat > '$env_path' <<'EOF'
APP_NAME=\"${esc_app_name}\"
APP_ENV=production
APP_KEY=${existing_key}
APP_DEBUG=false
APP_URL=\"${esc_app_url}\"

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=\"${esc_db_host}\"
DB_PORT=\"${esc_db_port}\"
DB_DATABASE=\"${esc_db_name}\"
DB_USERNAME=\"${esc_db_user}\"
DB_PASSWORD=\"${esc_db_pass}\"

BROADCAST_CONNECTION=log
CACHE_STORE=database
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=\"hello@example.com\"
MAIL_FROM_NAME=\"${esc_app_name}\"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME=\"${esc_app_name}\"
EOF"
}

ensure_storage_dirs(){
  mkdir -p "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
  chown -R "$APP_USER":"$APP_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
}

update_env_keys(){
  # Always enforce env/debug/url/db settings even if you keep existing .env
  local env_path="$APP_DIR/.env"
  local app_name_q app_url_q db_host_q db_port_q db_name_q db_user_q db_pass_q
  app_name_q="\"$(env_escape "$APP_NAME")\""
  app_url_q="\"$(env_escape "$APP_URL")\""
  db_host_q="\"$(env_escape "$DB_HOST")\""
  db_port_q="\"$(env_escape "$DB_PORT")\""
  db_name_q="\"$(env_escape "$DB_NAME")\""
  db_user_q="\"$(env_escape "$DB_USER")\""
  db_pass_q="\"$(env_escape "$DB_PASS")\""

  sudo -u "$APP_USER" bash -lc "php -r '
    \$path = \"$env_path\";
    \$env  = file_exists(\$path) ? file_get_contents(\$path) : \"\";
    \$set = [
      \"APP_NAME\"      => $app_name_q,
      \"APP_ENV\"       => \"production\",
      \"APP_DEBUG\"     => \"false\",
      \"APP_URL\"       => $app_url_q,
      \"DB_CONNECTION\" => \"mysql\",
      \"DB_HOST\"       => $db_host_q,
      \"DB_PORT\"       => $db_port_q,
      \"DB_DATABASE\"   => $db_name_q,
      \"DB_USERNAME\"   => $db_user_q,
      \"DB_PASSWORD\"   => $db_pass_q,
    ];
    foreach (\$set as \$k => \$v) {
      \$pattern = \"/^\\s*#?\\s*\".preg_quote(\$k, \"/\").\"=.*/m\";
      if (preg_match(\$pattern, \$env)) {
        \$env = preg_replace(\$pattern, \$k.\"=\".\$v, \$env);
      } else {
        \$env .= (substr(\$env, -1) === \"\\n\" ? \"\" : \"\\n\") . \$k.\"=\".\$v . \"\\n\";
      }
    }
    file_put_contents(\$path, \$env);
  '"
}

generate_env(){
  log "Generating .env (installer-managed)..."
  local env_path="$APP_DIR/.env"

  ensure_storage_dirs

  if [[ -f "$env_path" ]]; then
    if yesno ".env exists at $env_path. Overwrite it from install.sh inputs?" "n"; then
      write_full_env
    else
      log "Keeping existing .env; enforcing required keys safely..."
      update_env_keys
    fi
  else
    write_full_env
  fi
}

# -------------------- permissions (CRITICAL FIX) --------------------
fix_permissions(){
  log "Fixing permissions for Laravel (storage + cache writable by ${WEB_GROUP})..."

  # Ensure web group exists
  if ! getent group "$WEB_GROUP" >/dev/null 2>&1; then
    warn "Group $WEB_GROUP not found; creating it."
    groupadd "$WEB_GROUP" || true
  fi

  # Make entire app owned by app user + web group (so php-fpm can write)
  chown -R "$APP_USER:$WEB_GROUP" "$APP_DIR"

  # Ensure directories exist
  mkdir -p "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
  mkdir -p "$APP_DIR/storage/framework/views" "$APP_DIR/storage/framework/cache" "$APP_DIR/storage/framework/sessions"

  # Setgid so newly created files keep web group
  find "$APP_DIR/storage" -type d -exec chmod 2775 {} \;
  find "$APP_DIR/storage" -type f -exec chmod 664 {} \;

  find "$APP_DIR/bootstrap/cache" -type d -exec chmod 2775 {} \;
  find "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \;

  # Ensure public dir readable
  chmod -R o+rX "$APP_DIR/public" || true

  # Helpful: allow deploy user to work with web group easily
  usermod -aG "$WEB_GROUP" "$APP_USER" >/dev/null 2>&1 || true
}

# -------------------- app dependencies --------------------
install_app_deps(){
  log "Installing Composer deps..."
  sudo -u "$APP_USER" composer -d "$APP_DIR" install --no-interaction --prefer-dist --optimize-autoloader

  if [[ -f "$APP_DIR/package.json" ]]; then
    log "Installing Node deps..."
    sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && npm install"
    if sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && npm run | grep -qE 'build|prod'"; then
      log "Building frontend assets..."
      sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && npm run build"
    fi
  fi
}

# -------------------- artisan tasks (rerun-safe) --------------------
run_artisan(){
  log "Running artisan tasks..."

  # Ensure .env values are loaded fresh
  sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && php artisan config:clear || true"
  sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && php artisan route:clear || true"
  sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && php artisan view:clear || true"

  # Only generate APP_KEY if missing
  sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && \
    if ! grep -qE '^APP_KEY=base64:' .env; then \
      php artisan key:generate --force; \
    else \
      echo '[INFO] APP_KEY already set; skipping key:generate'; \
    fi"

  # Run migrations FIRST (creates cache/jobs tables if using Laravel defaults)
  sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && php artisan migrate --force"

  # Now it's safe to clear DB-backed cache/session/queue tables
  sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && php artisan cache:clear || true"

  # Create storage symlink (safe to re-run)
  sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && php artisan storage:link || true"

  # Optional: warm caches for production (safe even if you don't want it yet)
  # sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && php artisan config:cache || true"
  # sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && php artisan route:cache || true"
  # sudo -u "$APP_USER" bash -lc "cd '$APP_DIR' && php artisan view:cache || true"
}

# -------------------- nginx config --------------------
nginx_site_path(){
  if [[ "$OS_FAMILY" == "debian" ]]; then
    echo "/etc/nginx/sites-available/${PROJECT_SLUG}.conf"
  else
    echo "/etc/nginx/conf.d/${PROJECT_SLUG}.conf"
  fi
}

write_nginx_http(){
  local site_conf
  site_conf="$(nginx_site_path)"

  log "Configuring Nginx (HTTP)..."
  ensure_nginx_fastcgi_files

  mkdir -p "$(dirname "$site_conf")"
  cat >"$site_conf" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    root ${APP_DIR}/public;
    index index.php index.html;

    client_max_body_size 64m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_index index.php;
        fastcgi_pass unix:${PHP_FPM_SOCK};
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

  if [[ "$OS_FAMILY" == "debian" ]]; then
    ln -sf "$site_conf" "/etc/nginx/sites-enabled/${PROJECT_SLUG}.conf"
    rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
  fi

  nginx -t
  systemctl reload nginx
}

write_nginx_ssl_existing(){
  local site_conf="$1"
  cat >"$site_conf" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${DOMAIN};

    ssl_certificate     ${CERT_FULLCHAIN};
    ssl_certificate_key ${CERT_PRIVKEY};

    root ${APP_DIR}/public;
    index index.php index.html;

    client_max_body_size 64m;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_index index.php;
        fastcgi_pass unix:${PHP_FPM_SOCK};
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF
}

enable_https(){
  if [[ "$ENABLE_HTTPS" != "yes" ]]; then
    warn "HTTPS not enabled."
    return
  fi

  if [[ "$DOMAIN" == "_" || "$DOMAIN" == "localhost" || "$DOMAIN" == "127.0.0.1" ]]; then
    warn "No real domain set; skipping HTTPS."
    return
  fi

  local site_conf
  site_conf="$(nginx_site_path)"

  if [[ "$SSL_MODE" == "existing" ]]; then
    [[ -f "$CERT_FULLCHAIN" ]] || die "Cert fullchain not found: $CERT_FULLCHAIN"
    [[ -f "$CERT_PRIVKEY" ]]   || die "Cert privkey not found: $CERT_PRIVKEY"
    log "Enabling HTTPS using existing certificates..."
    write_nginx_ssl_existing "$site_conf"
    if [[ "$OS_FAMILY" == "debian" ]]; then
      ln -sf "$site_conf" "/etc/nginx/sites-enabled/${PROJECT_SLUG}.conf"
      rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
    fi
    nginx -t
    systemctl reload nginx
  else
    log "Requesting Let's Encrypt certificate via certbot..."
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL"
    systemctl reload nginx
  fi
}

# -------------------- systemd for queue worker --------------------
setup_systemd_queue(){
  log "Configuring systemd queue worker..."

  local svc="/etc/systemd/system/${PROJECT_SLUG}-queue.service"
  cat >"$svc" <<EOF
[Unit]
Description=${PROJECT_SLUG} Laravel Queue Worker
After=network.target ${DB_SERVICE}.service

[Service]
Type=simple
User=${APP_USER}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --timeout=90
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

  systemctl daemon-reload
  systemctl enable "${PROJECT_SLUG}-queue.service" || true
  systemctl restart "${PROJECT_SLUG}-queue.service" || true
}

# -------------------- scheduler via cron --------------------
setup_scheduler_cron(){
  log "Configuring Laravel scheduler cron..."
  local cron_file="/etc/cron.d/${PROJECT_SLUG}-scheduler"
  cat >"$cron_file" <<EOF
* * * * * ${APP_USER} cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
EOF
  chmod 644 "$cron_file"
}

# -------------------- backups (mysqldump cron + retention) --------------------
setup_backups(){
  if ! yesno "Enable daily mysqldump backups with retention?" "y"; then
    warn "Backups not enabled."
    return
  fi

  local retention
  prompt retention "Retention days" "7"

  local backup_dir="/var/backups/${PROJECT_SLUG}/mysql"
  mkdir -p "$backup_dir"
  chown root:root "$backup_dir"
  chmod 700 "$backup_dir"

  local cron_file="/etc/cron.d/${PROJECT_SLUG}-mysqldump"
  # Use root via unix_socket; no password stored in cron
  cat >"$cron_file" <<EOF
0 2 * * * root mkdir -p ${backup_dir} && /usr/bin/mysqldump --databases "${DB_NAME}" --single-transaction --quick --lock-tables=false | gzip > ${backup_dir}/${DB_NAME}_\$(date +\%F).sql.gz
15 2 * * * root find ${backup_dir} -type f -name "*.sql.gz" -mtime +${retention} -delete
EOF
  chmod 644 "$cron_file"

  log "Backups enabled: ${backup_dir} (retention ${retention} days)"
}

# -------------------- summary --------------------
summary(){
  cat <<EOF

✅ Installation complete

App directory: ${APP_DIR}
App user:      ${APP_USER}
Web group:     ${WEB_GROUP}
Domain:        ${DOMAIN}
App URL:       ${APP_URL}
DB:            ${DB_NAME}
DB user:       ${DB_USER}
PHP-FPM:       ${PHP_FPM_SERVICE} (${PHP_FPM_SOCK})
Nginx site:    $(nginx_site_path)

Test:
1) Nginx:
   sudo nginx -t

2) Laravel health:
   sudo -u ${APP_USER} bash -lc "cd ${APP_DIR} && php artisan about"

3) Open:
   ${APP_URL}

EOF
}

# -------------------- main --------------------
require_root
detect_os
log "Laravel All-in-One installer"

prompt PROJECT_SLUG "PROJECT_SLUG (folder/user/service name)" "laravelapp"
prompt GIT_REPO "GitHub repo URL (https or ssh)" ""
[[ -n "$GIT_REPO" ]] || die "GIT_REPO required"
prompt GIT_BRANCH "Git branch" "main"

prompt DOMAIN "Domain (e.g. portal.example.com). Use _ for none yet" "_"
prompt APP_NAME "APP_NAME" "Laravel Portal"

if [[ "$DOMAIN" == "_" ]]; then
  APP_URL="http://127.0.0.1"
else
  APP_URL="http://${DOMAIN}"
fi

prompt DB_HOST "DB host" "127.0.0.1"
prompt DB_PORT "DB port" "3306"
prompt DB_NAME "DB name" "${PROJECT_SLUG}"
prompt DB_USER "DB user" "${PROJECT_SLUG}_user"
prompt DB_PASS "DB password (will not echo)" ""
if [[ -z "$DB_PASS" ]]; then
  read -r -s -p "DB password (hidden): " DB_PASS
  echo
fi
[[ -n "$DB_PASS" ]] || die "DB password required"

if yesno "Enable HTTPS?" "y"; then
  ENABLE_HTTPS="yes"
  if [[ "$DOMAIN" == "_" ]]; then
    warn "You selected HTTPS but domain is _. HTTPS will be skipped."
  else
    if yesno "Use existing SSL certificates?" "n"; then
      SSL_MODE="existing"
      prompt CERT_FULLCHAIN "Path to fullchain.pem" "/etc/ssl/certs/fullchain.pem"
      prompt CERT_PRIVKEY "Path to privkey.pem" "/etc/ssl/private/privkey.pem"
    else
      SSL_MODE="letsencrypt"
      prompt ADMIN_EMAIL "Email for Let's Encrypt (expiry notices)" "admin@${DOMAIN}"
    fi
    APP_URL="https://${DOMAIN}"
  fi
fi

# Install + configure
if [[ "$OS_FAMILY" == "debian" ]]; then
  install_packages_debian
else
  install_packages_rhel
fi

ensure_services
ensure_nginx_fastcgi_files

create_app_user_and_dirs
clone_or_update_repo
create_database_and_user

generate_env
install_app_deps

# Critical: permissions must be correct BEFORE artisan renders views in prod
fix_permissions

write_nginx_http
enable_https

run_artisan

# Permissions again after artisan (it creates files)
fix_permissions

setup_systemd_queue
setup_scheduler_cron
setup_backups

systemctl restart nginx || true
systemctl restart "$PHP_FPM_SERVICE" || true
systemctl restart "$DB_SERVICE" || true

summary
