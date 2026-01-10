#!/usr/bin/env bash
set -euo pipefail

#############################################
# Config
#############################################
APP_DIR="/opt/financial.i-portal.me"
APP_USER="financial.i-portal.me"
BRANCH="master"

PHP_FPM_SERVICE="php8.4-fpm"
NGINX_SERVICE="nginx"

NPM_BIN="npm"
COMPOSER_BIN="composer"
PHP_BIN="php"

# Backups OUTSIDE the repo (best practice)
BACKUP_DIR="/var/backups/financial.i-portal.me"

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

run_as_app() {
  sudo -u "$APP_USER" bash -lc "$*"
}

artisan() {
  run_as_app "cd '$APP_DIR' && ${PHP_BIN} artisan $*"
}

# Check if a PHP class exists (autoload + app bootstrap)
class_exists_in_app() {
  local fqcn="$1"
  run_as_app "cd '$APP_DIR' && ${PHP_BIN} -r \"require 'vendor/autoload.php'; \$app=require 'bootstrap/app.php'; \$app->make(Illuminate\\\\Contracts\\\\Console\\\\Kernel::class)->bootstrap(); echo class_exists('${fqcn}') ? '1' : '0';\""
}

seed_if_exists() {
  local fqcn="$1"
  local label="$2"

  if [[ ! -d "$APP_DIR/vendor" ]]; then
    warn "vendor/ not present yet; cannot check ${label} seeder."
    return 0
  fi

  if [[ "$(class_exists_in_app "$fqcn")" == "1" ]]; then
    log "Running ${label} seeder (idempotent) ..."
    artisan "db:seed --class=${fqcn} --force"
  else
    warn "${label} seeder not found (${fqcn}). Skipping."
  fi
}

#############################################
# Main
#############################################
require_root

log "Deploying ${APP_DIR} from origin/${BRANCH} ..."

if [[ ! -d "$APP_DIR/.git" ]]; then
  err "No git repo found at $APP_DIR"
  exit 1
fi

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR" || true

if [[ ! -f "$APP_DIR/.env" ]]; then
  err ".env not found at ${APP_DIR}/.env"
  err "Create it once using install.sh before running deploy."
  exit 1
fi

TS="$(date +%F_%H%M%S)"
ENV_BAK="${BACKUP_DIR}/.env.${TS}.bak"
log "Backing up .env -> ${ENV_BAK} ..."
cp -a "$APP_DIR/.env" "$ENV_BAK"
chmod 600 "$ENV_BAK" || true

log "Ensuring git remote fetch refspec includes all branches ..."
run_as_app "cd '$APP_DIR' && \
  (git config --get-all remote.origin.fetch | grep -q 'refs/heads/\\*:refs/remotes/origin/\\*' || \
   (git config --unset-all remote.origin.fetch >/dev/null 2>&1 || true; \
    git config --add remote.origin.fetch '+refs/heads/*:refs/remotes/origin/*'))"

log "Fetching origin (prune) ..."
run_as_app "cd '$APP_DIR' && git fetch --prune origin"

if ! run_as_app "cd '$APP_DIR' && git show-ref --verify --quiet 'refs/remotes/origin/${BRANCH}'"; then
  err "Remote branch origin/${BRANCH} not found."
  err "Available remote branches:"
  run_as_app "cd '$APP_DIR' && git branch -r || true"
  exit 1
fi

log "Resetting working tree to origin/${BRANCH} ..."
run_as_app "cd '$APP_DIR' && git reset --hard 'origin/${BRANCH}'"
run_as_app "cd '$APP_DIR' && git checkout -B '${BRANCH}' 'origin/${BRANCH}'"

if [[ -d "$APP_DIR/_deploy_backups" ]]; then
  log "Removing legacy $APP_DIR/_deploy_backups ..."
  rm -rf "$APP_DIR/_deploy_backups"
fi

log "Cleaning untracked files (git clean -fd) ..."
run_as_app "cd '$APP_DIR' && git clean -fd -e '.env'"

if [[ ! -f "$APP_DIR/.env" ]]; then
  warn ".env missing after git operations — restoring from ${ENV_BAK} ..."
  cp -a "$ENV_BAK" "$APP_DIR/.env"
  chown "$APP_USER":"$APP_USER" "$APP_DIR/.env" || true
  chmod 600 "$APP_DIR/.env" || true
fi

if [[ ! -f "$APP_DIR/composer.lock" ]]; then
  err "composer.lock missing. Refusing to deploy without a lockfile."
  exit 1
fi

log "Removing old vendor/node/build/cache artifacts ..."
run_as_app "cd '$APP_DIR' && rm -rf vendor node_modules public/build bootstrap/cache/*.php"

log "Installing PHP dependencies (composer install --no-dev) ..."
run_as_app "cd '$APP_DIR' && ${COMPOSER_BIN} clear-cache >/dev/null 2>&1 || true"
run_as_app "cd '$APP_DIR' && COMPOSER_ALLOW_SUPERUSER=1 ${COMPOSER_BIN} install --no-dev --prefer-dist --optimize-autoloader --no-interaction"

if run_as_app "command -v ${NPM_BIN} >/dev/null 2>&1"; then
  log "Installing Node dependencies ..."
  if [[ -f "$APP_DIR/package-lock.json" ]]; then
    run_as_app "cd '$APP_DIR' && ${NPM_BIN} ci"
  else
    warn "package-lock.json not found. Using 'npm install'. (Recommended: commit package-lock.json)"
    run_as_app "cd '$APP_DIR' && ${NPM_BIN} install"
  fi

  log "Building assets (npm run build) ..."
  run_as_app "cd '$APP_DIR' && ${NPM_BIN} run build"
else
  warn "npm not found. Skipping frontend build."
fi

log "Running migrations ..."
artisan "migrate --force"

log "Running DatabaseSeeder (php artisan db:seed --force) ..."
artisan "db:seed --force"

# Enforce critical defaults every deploy (prevents missing settings in prod)
seed_if_exists "Database\\Seeders\\SmtpSettingSeeder"   "SmtpSettingSeeder"
seed_if_exists "Database\\Seeders\\IncomeSourceSeeder"  "IncomeSourceSeeder"

log "Clearing optimized caches ..."
artisan "optimize:clear"

log "Caching config ..."
artisan "config:cache"

# Route cache FAILS if you have any route closures.
# We'll try it; if it fails, we fall back safely.
log "Caching routes (best-effort) ..."
if ! artisan "route:cache"; then
  warn "route:cache failed (likely due to closure routes). Falling back to route:clear."
  artisan "route:clear" || true
fi

log "Caching views (safe) ..."
artisan "view:cache" || true

log "Fixing permissions (storage + bootstrap/cache) ..."
chown -R "$APP_USER":www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 2775 {} \; || true
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \; || true

log "Restarting PHP-FPM + reloading Nginx ..."
systemctl restart "$PHP_FPM_SERVICE"
systemctl reload "$NGINX_SERVICE"

log "Done."
log "Quick test:"
echo "  curl -I https://financial.i-portal.me"
echo "  sudo -u ${APP_USER} bash -lc 'cd ${APP_DIR} && php artisan about'"
echo "  .env backup: ${ENV_BAK}"
