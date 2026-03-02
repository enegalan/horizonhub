FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor libzip-dev icu-dev oniguruma-dev sqlite-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install pdo_sqlite zip intl bcmath pcntl opcache

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY packages/horizon-hub-agent /var/packages/horizon-hub-agent
COPY examples/demo-app/composer.json examples/demo-app/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY examples/demo-app/ ./
RUN composer dump-autoload --optimize

RUN mkdir -p /run/nginx \
    && cp nginx/default.conf /etc/nginx/http.d/default.conf 2>/dev/null || true

COPY examples/demo-app/supervisord.conf /etc/supervisord.conf

COPY examples/demo-app/docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
