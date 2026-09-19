#!/bin/sh
set -e

cd /var/www/html

RUN_USER=www-data

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views
chown -R $RUN_USER:$RUN_USER storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

[ -f vendor/autoload.php ] || composer install --no-interaction --no-progress
[ -d node_modules ] || npm ci --no-audit --no-fund
[ -d public/build ] || npm run build

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is required. Add APP_KEY=... to your project .env file (generate one with: php artisan key:generate --force --show)." >&2
    exit 1
fi
export APP_KEY

php artisan migrate --force
php artisan view:clear

chown -R $RUN_USER:$RUN_USER storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan queue:work &
php artisan schedule:work &

php-fpm -D
exec nginx -g 'daemon off;'
