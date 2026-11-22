<?php

namespace App\Modules\Order\Models;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderDispute extends Model
{
    protected $fillable = ['order_id', 'opened_by', 'resolved_by', 'status', 'reason', 'details', 'evidence_notes', 'resolution', 'resolution_note', 'buyer_wallet_refund_consent_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['evidence_notes' => 'array', 'buyer_wallet_refund_consent_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function events(): HasMany { return $this->hasMany(OrderDisputeEvent::class, 'dispute_id')->orderBy('id'); }
}
