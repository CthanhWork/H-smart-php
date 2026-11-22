<?php

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;

class WalletEntry extends Model
{
    protected $fillable = ['wallet_transaction_id', 'user_id', 'account_code', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
