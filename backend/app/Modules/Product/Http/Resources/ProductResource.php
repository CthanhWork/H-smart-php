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
            'condition' => $this->condition,
            'status' => $this->status,
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
