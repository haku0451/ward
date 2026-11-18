#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/ward-panel"

if [[ ! -f .env ]]; then
  cp .env.example .env
  php artisan key:generate
fi

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan ward:generate-signing-key || true
php artisan make:admin
npm ci
npm run build
php artisan config:cache
php artisan route:cache

echo "Panel install complete. Serve behind HTTPS (php-fpm + nginx/caddy)."
echo "Create an admin with: php artisan make:admin"
