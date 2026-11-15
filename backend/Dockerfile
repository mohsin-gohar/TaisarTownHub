# G-15 — API image (Laravel 12, PHP 8.3).
#
# Runs `php artisan serve` (equivalent to the dev-serving path used in this
# repo); for high-traffic production place php-fpm + nginx in front or scale
# replicas — the image itself is stateless. Configuration arrives exclusively
# through environment variables (compose/CI), never baked in.
FROM php:8.3-cli-alpine

# Runtime extensions: MySQL, intl, bcmath, zip, gd (image metadata), opcache.
RUN apk add --no-cache icu-dev libzip-dev libpng-dev oniguruma-dev curl \
    && docker-php-ext-install pdo_mysql bcmath intl zip gd opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first (layer cached).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Application code + autoloader.
COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

# The private document vault must stay OUTSIDE anything web-served; the
# volume mount point is prepared here and configured via DOCUMENTS disk env.
RUN mkdir -p storage/app/private/documents storage/app/public storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    && chown -R www-data:www-data storage

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
