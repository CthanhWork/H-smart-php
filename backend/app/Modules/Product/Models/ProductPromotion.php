<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\User\Models\User;

class ProductPromotion extends Model
{
    protected $fillable = ['product_id', 'seller_id', 'package_type', 'fee_amount', 'status', 'reference', 'idempotency_key', 'starts_at', 'expires_at'];

    protected function casts(): array
    {
        return ['fee_amount' => 'integer', 'starts_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }
}
