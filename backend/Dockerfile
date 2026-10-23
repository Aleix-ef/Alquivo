FROM composer:2 AS vendor
RUN docker-php-ext-install bcmath
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/keys \
    && composer dump-autoload --optimize --no-dev

FROM php:8.3-fpm-alpine
RUN apk add --no-cache icu-dev libpq-dev libzip-dev poppler-utils util-linux \
    && docker-php-ext-install -j2 bcmath intl opcache pcntl pdo_pgsql zip
WORKDIR /app
COPY --from=vendor /app /app
COPY docker/uploads.ini /usr/local/etc/php/conf.d/99-uploads.ini
# Keep the supported 8.3 patch stream, but refuse a cached image older than the
# critical 2026-10-21 fixes. This is a build check, not a runtime ini_set mitigation.
RUN php -r 'exit(version_compare(PHP_VERSION, "8.3.36", ">=") && !filter_var(ini_get("session.upload_progress.enabled"), FILTER_VALIDATE_BOOL) ? 0 : 1);'
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
