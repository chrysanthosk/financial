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

#############################################
# Main
#############################################
require_root

log "Deploying ${APP_DIR} from origin/${BRANCH} ..."

# Safety: ensure repo directory exists
if [[ ! -d "$APP_DIR/.git" ]]; then
  err "No git repo found at $APP_DIR"
  exit 1
fi

# 0) Ensure remote fetch rule is sane (prevents fetch failures caused by old main-only refspec)
log "Ensuring git remote fetch refspec includes all branches ..."
run_as_app "cd '$APP_DIR' && \
  (git config --get-all remote.origin.fetch | grep -q 'refs/heads/\\*:refs/remotes/origin/\\*' || \
   (git config --unset-all remote.origin.fetch >/dev/null 2>&1 || true; \
    git config --add remote.origin.fetch '+refs/heads/*:refs/remotes/origin/*'))"

# 1) Git fetch + hard reset to origin/master (no merges, no rebases)
log "Fetching origin (prune) ..."
run_as_app "cd '$APP_DIR' && git fetch --prune origin"

# Verify the remote branch exists
if ! run_as_app "cd '$APP_DIR' && git show-ref --verify --quiet 'refs/remotes/origin/${BRANCH}'"; then
  err "Remote branch origin/${BRANCH} not found."
  err "Available remote branches:"
  run_as_app "cd '$APP_DIR' && git branch -r || true"
  exit 1
fi

log "Checking out ${BRANCH} and resetting hard to origin/${BRANCH} ..."
# Create/switch local BRANCH to track origin/BRANCH
run_as_app "cd '$APP_DIR' && git checkout -B '${BRANCH}' 'origin/${BRANCH}'"
# Hard reset to exact commit
run_as_app "cd '$APP_DIR' && git reset --hard 'origin/${BRANCH}'"

# Clean any untracked files from previous deploys (safe because repo is authoritative)
log "Cleaning untracked files (git clean -fd) ..."
run_as_app "cd '$APP_DIR' && git clean -fd"

# 1.5) Sanity: ensure .env exists (deploy should never generate secrets)
if [[ ! -f "$APP_DIR/.env" ]]; then
  err ".env not found at ${APP_DIR}/.env"
  err "Create it once (from your install script) before running deploy."
  exit 1
fi

# 2) Clean vendor + cached bootstrap files to prevent broken vendor state
log "Cleaning vendor/ and bootstrap cache ..."
run_as_app "cd '$APP_DIR' && rm -rf vendor/ bootstrap/cache/*.php"

# 3) Composer install (NO UPDATE)
log "Installing PHP dependencies (composer install --no-dev) ..."
run_as_app "cd '$APP_DIR' && ${COMPOSER_BIN} clear-cache >/dev/null 2>&1 || true"
run_as_app "cd '$APP_DIR' && COMPOSER_ALLOW_SUPERUSER=1 ${COMPOSER_BIN} install --no-dev --prefer-dist --optimize-autoloader --no-interaction"

# 4) Front-end build (npm ci preferred)
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

# 5) Migrations
log "Running migrations ..."
run_as_app "cd '$APP_DIR' && ${PHP_BIN} artisan migrate --force"

# 5.5) Seeders (safe / idempotent) - creates first admin if missing
log "Running seeders (safe) ..."
run_as_app "cd '$APP_DIR' && ${PHP_BIN} artisan db:seed --force"

# 6) Clear + cache for production
log "Clearing & caching Laravel config/routes ..."
run_as_app "cd '$APP_DIR' && ${PHP_BIN} artisan optimize:clear"
run_as_app "cd '$APP_DIR' && ${PHP_BIN} artisan config:cache"
run_as_app "cd '$APP_DIR' && ${PHP_BIN} artisan route:cache"

# 7) Permissions (critical for compiled Blade views, cache, logs)
log "Fixing permissions (storage + bootstrap/cache) ..."
chown -R "$APP_USER":www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" || true
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 2775 {} \; || true
find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 664 {} \; || true

# 8) Restart services
log "Restarting PHP-FPM + reloading Nginx ..."
systemctl restart "$PHP_FPM_SERVICE"
systemctl reload "$NGINX_SERVICE"

log "Done."
log "Quick test:"
echo "  curl -I https://financial.i-portal.me"
echo "  sudo -u ${APP_USER} bash -lc 'cd ${APP_DIR} && php artisan about'"
