#!/bin/sh
set -e

cd /var/www/html

say() {
    echo "[entrypoint] $1"
}

if [ ! -f .env ]; then
    say "creating .env from .env.example"
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    say "installing composer dependencies"
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    say "generating the application key"
    php artisan key:generate --force
fi

say "waiting for the database"
until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    sleep 1
done

say "running migrations"
php artisan migrate --force

if [ ! -e public/storage ]; then
    say "linking the public storage directory"
    php artisan storage:link
fi

# The catalog seeder upserts by slug, so re-running it on every start is safe.
say "seeding the catalog"
php artisan db:seed --force

if [ ! -f public/build/manifest.json ]; then
    say "building frontend assets"
    npm install
    npm run build
fi

say "ready"

exec "$@"
