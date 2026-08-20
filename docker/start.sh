#!/bin/sh
set -eu

cd /var/www/html

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

rm -f bootstrap/cache/*.php
php artisan config:clear
php artisan route:clear
php artisan view:clear

exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
