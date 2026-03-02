#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views
chown -R nobody:nobody storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi

php artisan migrate --force

exec supervisord -c /etc/supervisord.conf
