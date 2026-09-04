FROM composer:2 AS vendor
RUN docker-php-ext-install bcmath
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.3-fpm-alpine
RUN apk add --no-cache icu-dev libpq-dev libzip-dev \
    && docker-php-ext-install bcmath intl opcache pcntl pdo_pgsql zip
WORKDIR /app
COPY --from=vendor /app /app
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
