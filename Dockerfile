FROM composer:2.8 AS composer

FROM php:8.4.27-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        libcurl4-openssl-dev \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        supervisor \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        curl \
        dom \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        xml \
        zip \
    && a2enmod headers rewrite \
    && a2dissite 000-default \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY . .
COPY docker/apache-vhost.conf /etc/apache2/sites-available/napi.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-napi-production.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/napi.conf
COPY docker/entrypoint.sh /usr/local/bin/napi-entrypoint

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --classmap-authoritative \
    --prefer-dist \
    && a2ensite napi \
    && chmod +x /usr/local/bin/napi-entrypoint \
    && install -d -o www-data -g www-data \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data bootstrap/cache storage

EXPOSE 80

ENTRYPOINT ["napi-entrypoint"]

