<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;

class AccountToken extends Model
{
    protected $fillable = ['user_id', 'type', 'token_hash', 'expires_at', 'used_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
