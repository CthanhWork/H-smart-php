<?php

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WalletTransaction extends Model
{
    protected $fillable = ['reference', 'idempotency_key', 'type', 'status', 'source_type', 'source_id', 'metadata', 'posted_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'posted_at' => 'datetime'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class, 'wallet_transaction_id')->orderBy('id');
    }
}
