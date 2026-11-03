FROM php:8.4-fpm-alpine

# Installs PHP extensions with their system dependencies in one step
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl zip opcache pcntl

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

EXPOSE 9000
CMD ["php-fpm"]
