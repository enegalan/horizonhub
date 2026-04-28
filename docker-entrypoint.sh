#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views
chown -R nobody:nobody storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

npm update
npm ci
npm run build

php artisan migrate --force
php artisan view:clear
php artisan config:cache --no-interaction
php artisan queue:work &
php artisan schedule:work &

php-fpm -D
exec nginx -g 'daemon off;'
