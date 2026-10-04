<?php

namespace App\Modules\User\Queries;

use App\Modules\User\Models\User;

class UserIdentityQuery
{
    public function byEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function byId(int $id): ?User
    {
        return User::find($id);
    }
}
