#!/bin/sh
set -eu

composer install --no-interaction
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
php artisan migrate --force
