#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
  storage/app/private \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
  if [ -f storage/app/app_key ]; then
    APP_KEY="$(cat storage/app/app_key)"
    export APP_KEY
  else
    APP_KEY="$(php artisan key:generate --show)"
    export APP_KEY
    printf '%s' "$APP_KEY" > storage/app/app_key
  fi
fi

i=0
until php -r '
$host = getenv("DB_HOST") ?: "postgres";
$port = getenv("DB_PORT") ?: "5432";
$db = getenv("DB_DATABASE") ?: "ward";
$user = getenv("DB_USERNAME") ?: "ward";
$pass = getenv("DB_PASSWORD") ?: "ward";
new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass);
'; do
  i=$((i + 1))
  if [ "$i" -ge 30 ]; then
    echo "postgres is not reachable" >&2
    exit 1
  fi
  sleep 2
done

php artisan migrate --force
php artisan ward:generate-signing-key || true

if [ -n "${WARD_ADMIN_EMAIL:-}" ] && [ -n "${WARD_ADMIN_PASSWORD:-}" ]; then
  php artisan make:admin \
    --name="${WARD_ADMIN_NAME:-Ward Admin}" \
    --email="$WARD_ADMIN_EMAIL" \
    --password="$WARD_ADMIN_PASSWORD" || true
fi

chown -R www-data:www-data storage bootstrap/cache || true

exec runuser -u www-data -- php artisan serve --host=0.0.0.0 --port=8000
