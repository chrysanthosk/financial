#!/usr/bin/env bash
set -euo pipefail

#############################################
# Defaults (overridable via env)
#############################################
BRANCH_DEFAULT="${BRANCH_DEFAULT:-master}"

PHP_FPM_SERVICE_DEFAULT="${PHP_FPM_SERVICE_DEFAULT:-php8.4-fpm}"
NGINX_SERVICE_DEFAULT="${NGINX_SERVICE_DEFAULT:-nginx}"

NPM_BIN="${NPM_BIN:-npm}"
COMPOSER_BIN="${COMPOSER_BIN:-/usr/local/bin/composer}"
PHP_BIN="${PHP_BIN:-php}"

# Web group (Debian/Ubuntu: www-data, RHEL: nginx)
WEB_GROUP="${WEB_GROUP:-www-data}"

# Root folder holding projects
OPT_ROOT="${OPT_ROOT:-/opt}"

# Backups OUTSIDE the repo (best practice)
BACKUP_ROOT="${BACKUP_ROOT:-/var/backups}"

# Seeder behavior:
# - "changed": run only seeders changed in this deploy (recommended)
# - "none": do not run any seeders
# - "all": run DatabaseSeeder (db:seed --force) (not recommended if it overwrites settings)
SEED_MODE="${SEED_MODE:-changed}"

#############################################
# Helpers
#############################################
log()  { echo -e "\033[1;32m[INFO]\033[0m $*"; }
warn() { echo -e "\033[1;33m[WARN]\033[0m $*"; }
err()  { echo -e "\033[1;31m[ERR ]\033[0m $*" >&2; }

require_root() {
  if [[ "$(id -u)" -ne 0 ]]; then
    err "Please run as root (sudo)."
    exit 1
  fi
}

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

# Discover candidate projects in /opt:
# - must be a directory
# - must contain .git
# - must contain artisan
discover_projects() {
  local d
  for d in "${OPT_ROOT}"/*; do
    [[ -d "$d" ]] || continue
    [[ -d "$d/.git" ]] || continue
    [[ -f "$d/artisan" ]] || continue
    echo "$d"
  done
}

select_project() {
  mapfile -t projects < <(discover_projects || true)

  if [[ "${#projects[@]}" -eq 0 ]]; then
    err "No Laravel projects found under ${OPT_ROOT} (need .git + artisan)."
    err "If your projects are elsewhere, run: OPT_ROOT=/path/to/projects sudo $0"
    exit 1
  fi

  log "Found ${#projects[@]} project(s) under ${OPT_ROOT}:"
  local i=1
  for p in "${projects[@]}"; do
    echo "  [$i] $(basename "$p")  ($p)"
    i=$((i+1))
  done

  local choice=""
  while true; do
    read -r -p "Select project number: " choice
    [[ "$choice" =~ ^[0-9]+$ ]] || { warn "Please enter a number."; continue; }
    (( choice >= 1 && choice <= ${#projects[@]} )) || { warn "Out of range."; continue; }
    APP_DIR="${projects[$((choice-1))]}"
    PROJECT_SLUG="$(basename "$APP_DIR")"
    break
  done
}

run_as_app() {
  sudo -u "$APP_USER" bash -lc "$*"
}

artisan() {
  run_as_app "cd '$APP_DIR' && ${PHP_BIN} artisan $*"
}

# Minimal class exists check (no Laravel bootstrap)
class_exists_in_app() {
  local fqcn="$1"
  run_as_app "cd '$APP_DIR' && ${PHP_BIN} -r 'require \"vendor/autoload.php\"; \$c=\$argv[1] ?? \"\"; echo class_exists(\$c) ? \"1\" : \"0\";' -- \"${fqcn}\""
}

seed_if_exists() {
  local fqcn="$1"   # e.g. Database\Seeders\SmtpSettingSeeder (single backslashes!)
  local label="$2"

  if [[ ! -d "$APP_DIR/vendor" ]]; then
    warn "vendor/ not present yet; cannot check ${label} seeder."
    return 0
  fi

  if [[ "$(class_exists_in_app "$fqcn")" == "1" ]]; then
    log "Running ${label} seeder ..."
    artisan "db:seed --class='${fqcn}' --force"
  else
    warn "${label} seeder not found (${fqcn}). Skipping."
  fi
}

detect_app_user() {
  if id -u "$PROJECT_SLUG" >/dev/null 2>&1; then
    APP_USER="$PROJECT_SLUG"
    return
  fi

  local owner
  owner="$(stat -c '%U' "$APP_DIR" 2>/dev/null || true)"
  if [[ -n "$owner" && "$owner" != "root" && "$owner" != "UNKNOWN" ]]; then
    warn "User '${PROJECT_SLUG}' not found. Using directory owner '${owner}' as APP_USER."
    APP_USER="$owner"
    return
  fi

  err "Could not determine APP_USER."
  err "Fix: create user '${PROJECT_SLUG}', or run with APP_USER=youruser sudo $0"
  exit 1
}

pick_branch() {
  BRANCH="$BRANCH_DEFAULT"
  if yesno "Deploy which branch?" "y"; then
    prompt BRANCH "Branch name" "$BRANCH_DEFAULT"
  fi
}

pick_services() {
  PHP_FPM_SERVICE="$PHP_FPM_SERVICE_DEFAULT"
  NGINX_SERVICE="$NGINX_SERVICE_DEFAULT"

  if yesno "Use default services? (PHP-FPM: ${PHP_FPM_SERVICE}, Nginx: ${NGINX_SERVICE})" "y"; then
    return
  fi

  prompt PHP_FPM_SERVICE "PHP-FPM systemd service name" "$PHP_FPM_SERVICE_DEFAULT"
  prompt NGINX_SERVICE "Nginx systemd service name" "$NGINX_SERVICE_DEFAULT"
}

# Convert changed seeder file paths to FQCN list
# database/seeders/FooSeeder.php -> Database\Seeders\FooSeeder
compute_changed_seeders() {
  local old_sha="$1" new_sha="$2"
  local files fqcn class
  mapfile -t files < <(run_as_app "cd '$APP_DIR' && git diff --name-only '$old_sha' '$new_sha' -- 'database/seeders/*.php' 'database/seeders/**/*.php' || true")

  if [[ "${#files[@]}" -eq 0 ]]; then
    echo ""
    return 0
  fi

  local out=()
  for f in "${files[@]}"; do
    class="$(basename "$f")"
    class="${class%.php}"
    fqcn="Database\\Seeders\\${class}"
    out+=("$fqcn")
  done

  printf "%s\n" "${out[@]}"
}

#############################################
# Main
#############################################
require_root
select_project

APP_USER="${APP_USER:-}"
if [[ -z "${APP_USER}" ]]; then
  detect_app_user
fi
BACKUP_DIR="${BACKUP_DIR:-${BACKUP_ROOT}/${PROJECT_SLUG}}"

pick_branch
pick_services

log "Selected project:"
echo "  APP_DIR:    ${APP_DIR}"
echo "  PROJECT:    ${PROJECT_SLUG}"
echo "  APP_USER:   ${APP_USER}"
echo "  BACKUP_DIR: ${BACKUP_DIR}"
echo "  BRANCH:     ${BRANCH}"
echo "  PHP-FPM:    ${PHP_FPM_SERVICE}"
echo "  NGINX:      ${NGINX_SERVICE}"
echo "  Composer:   ${COMPOSER_BIN}"
echo "  Seed mode:  ${SEED_MODE}"
echo

[[ -d "$APP_DIR/.git" ]] || { err "No git repo found at $APP_DIR"; exit 1; }
[[ -f "$APP_DIR/.env" ]] || { err ".env not found at ${APP_DIR}/.env (run install.sh first)"; exit 1; }

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR" || true

TS="$(date +%F_%H%M%S)"
ENV_BAK="${BACKUP_DIR}/.env.${TS}.bak"
log "Backing up .env -> ${ENV_BAK} ..."
cp -a "$APP_DIR/.env" "$ENV_BAK"
chmod 600 "$ENV_BAK" || true

log "Deploying ${APP_DIR} from origin/${BRANCH} ..."

# Record current SHA before update
OLD_SHA="$(run_as_app "cd '$APP_DIR' && git rev-parse HEAD")"

log "Ensuring git remote fetch refspec includes all branches ..."
run_as_app "cd '$APP_DIR' && \
  (git config --get-all remote.origin.fetch | grep -q 'refs/heads/\\*:refs/remotes/origin/\\*' || \
   (git config --unset-all remote.origin.fetch >/dev/null 2>&1 || true; \
    git config --add remote.origin.fetch '+refs/heads/*:refs/remotes/origin/*'))"

log "Fetching origin (prune) ..."
run_as_app "cd '$APP_DIR' && git fetch --prune origin"

if ! run_as_app "cd '$APP_DIR' && git show-ref --verify --quiet 'refs/remotes/origin/${BRANCH}'"; then
  err "Remote branch origin/${BRANCH} not found."
  run_as_app "cd '$APP_DIR' && git branch -r || true"
  exit 1
fi

log "Resetting working tree to origin/${BRANCH} ..."
run_as_app "cd '$APP_DIR' && git reset --hard 'origin/${BRANCH}'"
run_as_app "cd '$APP_DIR' && git checkout -B '${BRANCH}' 'origin/${BRANCH}'"

NEW_SHA="$(run_as_app "cd '$APP_DIR' && git rev-parse HEAD")"

log "Cleaning untracked files (git clean -fd) ..."
run_as_app "cd '$APP_DIR' && git clean -fd -e '.env'"

if [[ ! -f "$APP_DIR/.env" ]]; then
  warn ".env missing after git operations — restoring from ${ENV_BAK} ..."
  cp -a "$ENV_BAK" "$APP_DIR/.env"
  chown "$APP_USER":"$APP_USER" "$APP_DIR/.env" || true
  chmod 600 "$APP_DIR/.env" || true
fi

[[ -f "$APP_DIR/composer.lock" ]] || { err "composer.lock missing. Refusing to deploy."; exit 1; }

log "Removing old vendor/node/build/cache artifacts ..."
run_as_app "cd '$APP_DIR' && rm -rf vendor node_modules public/build bootstrap/cache/*.php"

if [[ ! -x "$COMPOSER_BIN" ]]; then
  warn "Composer not found at ${COMPOSER_BIN}. Falling back to 'composer' in PATH."
  COMPOSER_BIN="composer"
fi

log "Installing PHP dependencies (composer install --no-dev) ..."
run_as_app "cd '$APP_DIR' && ${COMPOSER_BIN} clear-cache >/dev/null 2>&1 || true"
run_as_app "cd '$APP_DIR' && ${COMPOSER_BIN} install --no-dev --prefer-dist --optimize-autoloader --no-interaction"

if run_as_app "command -v ${NPM_BIN} >/dev/null 2>&1"; then
  log "Installing Node dependencies ..."
  if [[ -f "$APP_DIR/package-lock.json" ]]; then
    run_as_app "cd '$APP_DIR' && ${NPM_BIN} ci"
  else
    warn "package-lock.json not found. Using 'npm install'."
    run_as_app "cd '$APP_DIR' && ${NPM_BIN} install"
  fi
  log "Building assets (npm run build) ..."
  run_as_app "cd '$APP_DIR' && ${NPM_BIN} run build"
else
  warn "npm not found. Skipping frontend build."
fi

log "Running migrations ..."
artisan "migrate --force"

# ✅ Seeder strategy
if [[ "$SEED_MODE" == "all" ]]; then
  warn "Running DatabaseSeeder (this can overwrite user settings if seeders upsert defaults)."
  artisan "db:seed --force"
elif [[ "$SEED_MODE" == "changed" ]]; then
  log "Detecting changed seeders between:"
  echo "  OLD: $OLD_SHA"
  echo "  NEW: $NEW_SHA"

  mapfile -t changed_seeders < <(compute_changed_seeders "$OLD_SHA" "$NEW_SHA" || true)

  if [[ "${#changed_seeders[@]}" -eq 0 ]] || [[ -z "${changed_seeders[0]:-}" ]]; then
    log "No seeder files changed. Skipping seeding."
  else
    log "Changed seeders:"
    printf "  - %s\n" "${changed_seeders[@]}"
    for s in "${changed_seeders[@]}"; do
      seed_if_exists "$s" "$s"
    done
  fi
else
  log "SEED_MODE=none -> skipping all seeding."
fi

log "Clearing optimized caches ..."
artisan "optimize:clear"

log "Caching config ..."
artisan "config:cache"

log "Caching routes (best-effort) ..."
if ! artisan "route:cache"; then
  warn "route:cache failed (likely closure routes). Falling back to route:clear."
  artisan "route:clear" || true
fi

log "Caching views (safe) ..."
artisan "view:cache" || true

log "Fixing permissions (storage + bootstrap/cache) ..."
chown -R "$APP_USER":"$WEB_GROUP" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 2775 {} \; || true
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \; || true

log "Restarting PHP-FPM + reloading Nginx ..."
systemctl restart "$PHP_FPM_SERVICE" || true
systemctl reload "$NGINX_SERVICE" || true

log "Done."
log "Quick test:"
echo "  sudo -u ${APP_USER} bash -lc 'cd ${APP_DIR} && php artisan about'"
echo "  .env backup: ${ENV_BAK}"
