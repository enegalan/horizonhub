#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views
chown -R nobody:nobody storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

npm ci
npm run build

php artisan migrate --force

if [ "${DEMO_SERVICES:-0}" = "1" ]; then
    php artisan db:seed --class=DemoServicesSeeder --force
fi

php-fpm -D
exec nginx -g 'daemon off;'
