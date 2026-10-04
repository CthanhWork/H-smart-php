<?php

namespace App\Modules\User\Actions;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ReplacePasswordAction
{
    public function withCurrentPassword(int $userId, string $currentPassword, string $newPassword): void
    {
        $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
        if (! password_verify($currentPassword, $user->password_hash)) {
            throw ValidationException::withMessages(['currentPassword' => 'Mật khẩu hiện tại không đúng.']);
        }
        if (password_verify($newPassword, $user->password_hash)) {
            throw ValidationException::withMessages(['password' => 'Mật khẩu mới phải khác mật khẩu hiện tại.']);
        }

        $this->save($user, $newPassword);
    }

    public function afterReset(int $userId, string $newPassword): void
    {
        $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
        if (password_verify($newPassword, $user->password_hash)) {
            throw ValidationException::withMessages(['password' => 'Mật khẩu mới phải khác mật khẩu hiện tại.']);
        }
        $this->save($user, $newPassword);
    }

    public function revokeSessions(int $userId): void
    {
        User::whereKey($userId)->increment('auth_version');
    }

    private function save(User $user, string $password): void
    {
        $user->password_hash = Hash::make($password);
        $user->auth_version = (int) $user->auth_version + 1;
        $user->save();
    }
}
