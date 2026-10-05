FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev libonig-dev unzip \
    && docker-php-ext-install pdo_pgsql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker-uploads.ini /usr/local/etc/php/conf.d/docker-uploads.ini

WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress

COPY docker-entrypoint.sh /usr/local/bin/hsmart-entrypoint
RUN chmod +x /usr/local/bin/hsmart-entrypoint

EXPOSE 8000
ENTRYPOINT ["hsmart-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
