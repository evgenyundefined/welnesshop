#!/bin/sh
set -e

cd /var/www/html

say() {
    echo "[entrypoint] $1"
}

if [ -z "${APP_KEY}" ]; then
    echo "[entrypoint] APP_KEY is not set. Generate one with 'php artisan key:generate --show'" >&2
    echo "[entrypoint] and set it as an environment variable, or every redeploy invalidates" >&2
    echo "[entrypoint] all sessions and encrypted cookies." >&2
    exit 1
fi

say "waiting for the database"
until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    sleep 1
done

say "running migrations"
php artisan migrate --force

# Uploaded photos live on a volume, so the link is remade on every start.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
[ -e public/storage ] || php artisan storage:link

say "seeding the administrator"
php artisan db:seed --class=AdminSeeder --force

if [ "${SEED_CATALOG}" = "true" ]; then
    say "seeding the demo catalog"
    php artisan db:seed --class=CatalogSeeder --force
fi

say "caching configuration, routes and views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

say "ready"

exec "$@"
