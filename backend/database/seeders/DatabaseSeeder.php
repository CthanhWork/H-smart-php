<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Modules\User\Models\User;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = env('INITIAL_ADMIN_EMAIL');
        $adminPassword = env('INITIAL_ADMIN_PASSWORD');

        $validAdminCredentials = is_string($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)
            && is_string($adminPassword) && strlen($adminPassword) >= 24
            && ! str_starts_with($adminPassword, 'replace-');

        if ($validAdminCredentials) {
            $attributes = [
                'username' => 'admin',
                'password_hash' => password_hash($adminPassword, PASSWORD_ARGON2ID),
                'role' => 'admin',
                'status' => 'active',
                'full_name' => 'Quản trị viên',
            ];
            $legacyAdmin = User::where('email', 'admin@hsmart.local')->first();

            if ($legacyAdmin && password_verify('Admin@123456', $legacyAdmin->password_hash)) {
                $legacyAdmin->email = $adminEmail;
                $legacyAdmin->fill($attributes)->save();
            } else {
                User::firstOrCreate(['email' => $adminEmail], $attributes);
            }
        } else {
            // The demo SQL snapshot contains a publicly known admin password.
            // Disable that account unless the operator supplies a private password.
            $legacyAdmin = User::where('email', 'admin@hsmart.local')->first();
            if ($legacyAdmin && password_verify('Admin@123456', $legacyAdmin->password_hash)) {
                $legacyAdmin->status = 'suspended';
                $legacyAdmin->save();
            }
        }

        $this->call(DemoProductSeeder::class);
    }
}
