<?php

namespace App\Modules\Wallet\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    protected $fillable = ['user_id', 'amount', 'bank_details_ciphertext', 'status', 'reference', 'idempotency_key', 'decision_reason', 'reviewed_by', 'reviewed_at', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'reviewed_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
