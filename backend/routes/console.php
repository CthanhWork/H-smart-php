<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('make:admin {email} {password=Admin@123456}', function (string $email, string $password) {
    $user = \App\Modules\User\Models\User::where('email', $email)->first();
    if ($user) {
        $user->role = 'admin';
        $user->status = 'active';
        $user->save();
        $this->info("Đã nâng cấp tài khoản [{$email}] thành Admin thành công!");
    } else {
        $baseUsername = explode('@', $email)[0];
        $username = $baseUsername;
        $counter = 1;
        while (\App\Modules\User\Models\User::where('username', $username)->exists()) {
            $username = $baseUsername . '_' . $counter++;
        }

        $user = \App\Modules\User\Models\User::create([
            'email' => $email,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'role' => 'admin',
            'status' => 'active',
            'full_name' => 'Quản trị viên',
        ]);
        $this->info("Tạo mới tài khoản Admin thành công!");
        $this->line("• Email: {$email}");
        $this->line("• Mật khẩu: {$password}");
    }
})->purpose('Tạo mới hoặc nâng cấp tài khoản Admin');
