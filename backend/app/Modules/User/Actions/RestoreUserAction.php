<?php

namespace App\Modules\User\Actions;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class RestoreUserAction
{
    public function execute(User $user): User
    {
        if ($user->status !== 'suspended') {
            throw new \DomainException('User is not suspended');
        }

        return DB::transaction(function () use ($user) {
            $user->lockForUpdate()->find($user->id);
            $user->status = 'active';
            $user->save();
            return $user;
        });
    }
}
