#!/bin/sh
set -eu

install -d -o www-data -g www-data \
    /var/www/html/bootstrap/cache \
    /var/www/html/storage/app/private \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs

php artisan migrate --force
php artisan db:seed --force
php artisan napi:bootstrap-admin
php artisan optimize

exec /usr/bin/supervisord --configuration=/etc/supervisor/conf.d/napi.conf

