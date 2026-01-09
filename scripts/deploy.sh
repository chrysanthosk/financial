#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/opt/financial.i-portal.me"
APP_USER="financial.i-portal.me"
BRANCH="master"
PHP_FPM_SERVICE="php8.4-fpm"
NGINX_SERVICE="nginx"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "[ERR ] Run as root: sudo ./deploy.sh"
  exit 1
fi

echo "[INFO] Deploying from origin/${BRANCH} ..."
sudo -u "${APP_USER}" bash -lc "cd '${APP_DIR}' && git fetch origin '${BRANCH}' && git reset --hard 'origin/${BRANCH}'"

echo "[INFO] Composer install (prod)..."
sudo -u "${APP_USER}" bash -lc "cd '${APP_DIR}' && composer install --no-dev --optimize-autoloader"

echo "[INFO] Node install + build..."
sudo -u "${APP_USER}" bash -lc "cd '${APP_DIR}' && npm ci"
sudo -u "${APP_USER}" bash -lc "cd '${APP_DIR}' && npm run build"

echo "[INFO] Laravel migrate..."
sudo -u "${APP_USER}" bash -lc "cd '${APP_DIR}' && php artisan migrate --force"

echo "[INFO] Laravel caches clear..."
sudo -u "${APP_USER}" bash -lc "cd '${APP_DIR}' && php artisan optimize:clear"

echo "[INFO] Permissions (storage + cache)..."
chown -R "${APP_USER}":www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
find "${APP_DIR}/storage" -type d -exec chmod 2775 {} \;
find "${APP_DIR}/storage" -type f -exec chmod 664 {} \;
find "${APP_DIR}/bootstrap/cache" -type d -exec chmod 2775 {} \;
find "${APP_DIR}/bootstrap/cache" -type f -exec chmod 664 {} \;

echo "[INFO] Reload services..."
systemctl reload "${NGINX_SERVICE}"
systemctl restart "${PHP_FPM_SERVICE}"

echo "[OK] Done."
echo "Test: curl -I https://financial.i-portal.me"
