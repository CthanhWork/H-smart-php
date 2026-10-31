<?php

namespace App\Modules\User\Actions;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class SuspendUserAction
{
    public function execute(User $user, string $reason): User
    {
        if ($user->status !== 'active') {
            throw new \DomainException('User is not active');
        }

        return DB::transaction(function () use ($user, $reason) {
            $user->lockForUpdate()->find($user->id);
            $user->status = 'suspended';
            $user->auth_version = ($user->auth_version ?? 0) + 1;
            $user->save();

            // TODO: Store suspend_reason in admin_actions table when implemented

            return $user;
        });
    }
}
