<?php

namespace App\Modules\Wallet\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletVnPayDeposit extends Model
{
    protected $table = 'wallet_vnpay_deposits';

    protected $fillable = [
        'user_id', 'reference', 'idempotency_key', 'amount', 'provider', 'mode', 'status', 'expires_at',
        'paid_at', 'transaction_no', 'bank_code', 'response_code', 'callback_amount', 'callback_received_at',
        'resolution_note', 'wallet_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'callback_amount' => 'integer',
            'expires_at' => 'datetime', 'paid_at' => 'datetime', 'callback_received_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}
