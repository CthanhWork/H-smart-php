<?php

namespace App\Modules\Product\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'category_id' => $this->category_id,
            'category' => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug] : null,
            'seller' => $this->whenLoaded('seller', fn () => [
                'id' => $this->seller->id,
                'name' => $this->seller->full_name ?: $this->seller->username,
            ]),
            'seller_completed_sales_count' => $this->when(isset($this->seller_completed_sales_count), (int) $this->seller_completed_sales_count),
            'seller_review_average' => $this->when(isset($this->seller_review_average), $this->seller_review_average === null ? null : round((float) $this->seller_review_average, 1)),
            'seller_review_count' => $this->when(isset($this->seller_review_count), (int) $this->seller_review_count),
            'rating_average' => $this->when(isset($this->reviews_avg_rating), $this->reviews_avg_rating === null ? null : round((float) $this->reviews_avg_rating, 1)),
            'review_count' => $this->when(isset($this->reviews_count), (int) $this->reviews_count),
            'condition' => $this->condition,
            'negotiable' => (bool) $this->negotiable,
            'min_price' => $this->min_price,
            'status' => $this->status,
            'publication' => $this->whenLoaded('publication', fn () => $this->publication ? [
                'status' => $this->publication->status,
                'month_key' => $this->publication->month_key,
                'fee_amount' => $this->publication->fee_amount,
                'published_at' => $this->publication->published_at?->toIso8601String(),
            ] : null),
            'promotion' => $this->whenLoaded('currentPromotion', fn () => $this->status === \App\Modules\Product\Models\Product::STATUS_ACTIVE && $this->currentPromotion ? [
                'package_type' => $this->currentPromotion->package_type,
                'fee_amount' => $this->currentPromotion->fee_amount,
                'starts_at' => $this->currentPromotion->starts_at?->toIso8601String(),
                'expires_at' => $this->currentPromotion->expires_at?->toIso8601String(),
            ] : null),
            'scheduled_promotion' => $this->whenLoaded('nextPromotion', fn () => $this->status === \App\Modules\Product\Models\Product::STATUS_ACTIVE && $this->nextPromotion ? [
                'package_type' => $this->nextPromotion->package_type,
                'expires_at' => $this->nextPromotion->expires_at?->toIso8601String(),
                'starts_at' => $this->nextPromotion->starts_at?->toIso8601String(),
            ] : null),
            'is_favorite' => $this->when(isset($this->is_favorite), (bool) $this->is_favorite),
            'images' => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->url,
                'sort_order' => $image->sort_order,
            ])->all(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
