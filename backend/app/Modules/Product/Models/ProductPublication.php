<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPublication extends Model
{
    protected $fillable = ['product_id', 'seller_id', 'month_key', 'fee_amount', 'used_free_quota', 'status', 'reference', 'quoted_at', 'published_at', 'paid_at'];

    protected function casts(): array
    {
        return ['fee_amount' => 'integer', 'used_free_quota' => 'boolean', 'quoted_at' => 'datetime', 'published_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
