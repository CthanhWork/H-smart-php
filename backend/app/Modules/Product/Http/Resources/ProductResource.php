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
            'rating_average' => $this->when(isset($this->reviews_avg_rating), $this->reviews_avg_rating === null ? null : round((float) $this->reviews_avg_rating, 1)),
            'review_count' => $this->when(isset($this->reviews_count), (int) $this->reviews_count),
            'condition' => $this->condition,
            'negotiable' => (bool) $this->negotiable,
            'min_price' => $this->min_price,
            'status' => $this->status,
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
