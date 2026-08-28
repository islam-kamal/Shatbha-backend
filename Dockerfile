FROM richarvey/nginx-php-fpm:3.1.6

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

WORKDIR /var/www/html

# Image config
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1

# Laravel config
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV SESSION_DRIVER=database
ENV CACHE_STORE=database
ENV QUEUE_CONNECTION=sync

# Allow composer to run as root
ENV COMPOSER_ALLOW_SUPERUSER=1

# --no-scripts: artisan is not usable at build time (no APP_KEY yet)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache

EXPOSE 80 8000

CMD ["/start.sh"]
