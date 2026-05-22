#!/usr/bin/env bash
# The Playground — deployment script.
# Run from the site root on the server:
#   cd /var/www/theplayground && ./deploy.sh
#
# Assumes:
#   - git remote 'origin' points at github.com:DarthYvonne/theplayground.git
#   - the working tree is on the 'main' branch
#   - composer, php, and node (optional) are on $PATH
#   - .env on the server is configured (DB_*, APP_KEY, APP_URL, MAIL_*)
#   - storage/ and bootstrap/cache/ are writable by the web server user
set -euo pipefail

cd "$(dirname "$0")"

echo "==> Pulling latest from main"
git fetch --all --prune
git reset --hard origin/main

echo "==> Composer install (no-dev, optimized autoload)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Storage symlink (idempotent)"
php artisan storage:link || true

echo "==> Migrations"
php artisan migrate --force

echo "==> Caching config + routes + views"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

if [ -f package.json ]; then
  if command -v npm >/dev/null 2>&1; then
    echo "==> npm ci + build (frontend assets)"
    npm ci --no-audit --no-fund || npm install --no-audit --no-fund
    npm run build || true
  fi
fi

echo "==> Fixing storage permissions"
chmod -R ug+rwX storage bootstrap/cache || true

echo "==> Restarting queue workers (if any)"
php artisan queue:restart || true

echo "==> Done. $(date)"
