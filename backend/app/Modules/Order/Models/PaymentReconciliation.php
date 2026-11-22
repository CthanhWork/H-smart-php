<?php

namespace App\Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReconciliation extends Model
{
    protected $fillable = ['provider', 'payment_reference', 'bank_reference', 'amount', 'status', 'order_id', 'reconciled_by', 'evidence_note', 'reconciled_at'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'reconciled_at' => 'datetime'];
    }
}
