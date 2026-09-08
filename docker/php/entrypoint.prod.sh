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

# public/storage is baked into the image; only the writable tree is checked,
# because a volume mounted with the wrong owner fails later and less clearly.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

if [ ! -w storage/app/public ]; then
    echo "[entrypoint] storage/app/public is not writable by $(id -un)." >&2
    echo "[entrypoint] Uploaded photos would fail. Check the ownership of the volume" >&2
    echo "[entrypoint] mounted at /var/www/html/storage/app/public." >&2
    exit 1
fi

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
