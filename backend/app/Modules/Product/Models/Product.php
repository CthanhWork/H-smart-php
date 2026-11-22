<?php

namespace App\Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SOLD = 'sold';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_LISTING_PAYMENT = 'pending_listing_payment';

    public const STATUSES = ['draft', 'pending_review', 'pending_listing_payment', 'active', 'reserved', 'sold', 'hidden'];

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\User\Models\User::class, 'seller_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(\App\Modules\Order\Models\Order::class)->whereNotNull('review_rating');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function publication(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductPublication::class);
    }

    public function currentPromotion(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductPromotion::class)->where('status', 'active')
            ->where('starts_at', '<=', now())->where('expires_at', '>', now())
            ->orderByRaw("CASE WHEN package_type = 'vip' THEN 2 ELSE 1 END DESC")->orderByDesc('expires_at');
    }

    public function nextPromotion(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductPromotion::class)->where('status', 'active')->orderByDesc('expires_at');
    }
}
