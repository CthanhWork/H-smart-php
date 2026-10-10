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
php artisan db:seed --force --no-interaction
php artisan storage:link --force --no-interaction

# Tạo admin user nếu chưa có
php artisan tinker --execute='
$admin = App\Modules\User\Models\User::where("email", "admin@hsmart.local")->first();
if (!$admin) {
    App\Modules\User\Models\User::create([
        "username" => "admin",
        "email" => "admin@hsmart.local",
        "password_hash" => bcrypt("Admin@123456"),
        "role" => "admin",
        "status" => "active"
    ]);
    echo "✅ Admin user created: admin@hsmart.local / Admin@123456\n";
} else {
    echo "ℹ️  Admin user already exists\n";
}
' || echo "⚠️  Could not verify admin user creation"

exec "$@"
