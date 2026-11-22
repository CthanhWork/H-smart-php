<?php

namespace App\Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDisputeEvent extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['dispute_id', 'actor_id', 'action', 'note', 'metadata', 'created_at'];

    protected function casts(): array { return ['metadata' => 'array', 'created_at' => 'datetime']; }

    public function dispute(): BelongsTo { return $this->belongsTo(OrderDispute::class); }
}
