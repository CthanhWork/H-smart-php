<?php

namespace App\Modules\User\Actions;

use App\Modules\User\Models\User;

class ActivateUserAction
{
    public function execute(int $userId): bool
    {
        return User::whereKey($userId)->where('status', 'pending_verification')->update(['status' => 'active']) === 1;
    }
}
