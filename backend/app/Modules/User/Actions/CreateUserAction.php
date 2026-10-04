<?php

namespace App\Modules\User\Actions;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function execute(string $email, string $password, ?string $fullName): User
    {
        return User::create([
            'username' => $email,
            'email' => $email,
            'password_hash' => Hash::make($password),
            'role' => 'member',
            'full_name' => $fullName,
            'status' => 'pending_verification',
        ]);
    }
}
