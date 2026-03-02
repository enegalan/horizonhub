FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx nodejs npm libzip-dev libpng-dev oniguruma-dev icu-dev libxml2-dev linux-headers \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install pdo_mysql zip gd intl bcmath pcntl opcache

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize

COPY nginx/default.conf /etc/nginx/http.d/default.conf
RUN rm -f /etc/nginx/http.d/default.conf.bak 2>/dev/null; true

RUN mkdir -p /run/nginx

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
