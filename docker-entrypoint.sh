#!/bin/sh
set -e

cd /var/www/html

RUN_USER=www-data

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views database
chown -R $RUN_USER:$RUN_USER storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

: "${DB_CONNECTION:=sqlite}"
: "${DB_DATABASE:=/var/www/html/database/database.sqlite}"
: "${CACHE_STORE:=database}"
: "${SESSION_DRIVER:=database}"
: "${QUEUE_CONNECTION:=database}"
: "${BROADCAST_CONNECTION:=null}"

# Generate a key on first boot when none is provided. Persist it (or set it via
# env) for stable encryption across container recreations.
if [ -z "$APP_KEY" ]; then
    APP_KEY=$(php artisan key:generate --force --show)
fi

export DB_CONNECTION DB_DATABASE CACHE_STORE SESSION_DRIVER QUEUE_CONNECTION BROADCAST_CONNECTION APP_KEY

if [ "$DB_CONNECTION" = "sqlite" ]; then
    touch "$DB_DATABASE"
fi

php artisan migrate --force
php artisan view:clear
php artisan config:cache --no-interaction

# Make the data directories writable by the php-fpm worker.
chown -R $RUN_USER:$RUN_USER storage bootstrap/cache database
chmod -R 775 storage bootstrap/cache database

php artisan queue:work &
php artisan schedule:work &

php-fpm -D
exec nginx -g 'daemon off;'
