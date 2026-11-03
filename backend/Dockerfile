FROM php:8.4-fpm-alpine

# Installs PHP extensions with their system dependencies in one step
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl
# gRPC client for the inventory service. Taken prebuilt from Alpine's package
# (same PHP 8.4 extension API) instead of compiling it from PECL: faster, and works
# where pecl.php.net can't be reached.
RUN apk add --no-cache php84-pecl-grpc \
    && cp /usr/lib/php84/modules/grpc.so "$(php-config --extension-dir)/" \
    && docker-php-ext-enable grpc

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Install dependencies first so this layer is cached until composer files change
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

# Don't announce the PHP version (X-Powered-By header)
RUN echo "expose_php = Off" > /usr/local/etc/php/conf.d/security.ini

# Let PHP-FPM handle more requests at the same time than the default 5
RUN printf "[www]\npm.max_children = 20\npm.start_servers = 4\npm.min_spare_servers = 2\npm.max_spare_servers = 8\n" \
    > /usr/local/etc/php-fpm.d/zz-pool.conf

EXPOSE 9000
CMD ["php-fpm"]
