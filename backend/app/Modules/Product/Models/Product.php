<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SOLD = 'sold';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_DRAFT = 'draft';

    public const STATUSES = ['draft', 'pending_review', 'active', 'reserved', 'sold', 'hidden'];

    public const CONDITIONS = ['new', 'like_new', 'good', 'fair'];

    protected $fillable = [
        'seller_id', 'category_id', 'title', 'description', 'price', 'condition',
        'negotiable', 'min_price', 'status', 'province', 'district',
    ];

    protected function casts(): array
    {
        return ['price' => 'integer', 'negotiable' => 'boolean', 'min_price' => 'integer'];
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }
}
