FROM php:8.4-fpm-alpine

RUN apk add --no-cache bash curl curl-dev git icu-dev libzip-dev oniguruma-dev linux-headers $PHPIZE_DEPS \
 && docker-php-ext-install curl intl mbstring pdo_mysql pcntl zip \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

CMD ["php-fpm"]
