#!/bin/sh
set -eu

if [ ! -f /run/hsmart/.env ]; then
    cp .env.example /run/hsmart/.env
fi

ln -sfn /run/hsmart/.env .env

if ! grep -q '^APP_KEY=base64:' /run/hsmart/.env; then
    php artisan key:generate --force --no-interaction
fi

if ! grep -q '^JWT_SECRET=.' /run/hsmart/.env; then
    secret="$(php -r 'echo bin2hex(random_bytes(32));')"
    sed -i "s/^JWT_SECRET=.*/JWT_SECRET=$secret/" /run/hsmart/.env
fi

php artisan config:clear --no-interaction
php artisan migrate --force --no-interaction
php artisan storage:link --force --no-interaction

exec "$@"
