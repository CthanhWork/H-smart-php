<?php

namespace App\Console\Commands;

use App\Modules\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MakeAdminCommand extends Command
{
    protected $signature = 'make:admin {--email=} {--password=}';

    protected $description = 'Create a new admin user';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Admin email');
        $password = $this->option('password') ?: $this->secret('Admin password');

        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
        ], [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        $username = explode('@', $email)[0];
        $baseUsername = $username;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        $admin = User::create([
            'username' => $username,
            'email' => $email,
            'password_hash' => Hash::make($password),
            'role' => 'admin',
            'status' => 'active',
            'full_name' => 'Administrator',
        ]);

        $this->info('Admin user created successfully!');
        $this->table(['ID', 'Username', 'Email', 'Role'], [
            [$admin->id, $admin->username, $admin->email, $admin->role],
        ]);

        return self::SUCCESS;
    }
}
