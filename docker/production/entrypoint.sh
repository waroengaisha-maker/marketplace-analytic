#!/usr/bin/env bash
set -e

cd /var/www/html

php artisan package:discover --ansi
php artisan storage:link --quiet || true

exec "$@"
