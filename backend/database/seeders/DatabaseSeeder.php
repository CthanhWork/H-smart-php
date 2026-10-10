<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Modules\User\Models\User::firstOrCreate(
            ['email' => 'admin@hsmart.local'],
            [
                'username' => 'admin',
                'password_hash' => password_hash('Admin@123456', PASSWORD_ARGON2ID),
                'role' => 'admin',
                'status' => 'active',
                'full_name' => 'Quản trị viên',
            ]
        );
    }
}
