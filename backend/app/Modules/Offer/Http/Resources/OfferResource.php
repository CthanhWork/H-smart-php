<?php

namespace App\Modules\Offer\Http\Resources;

use App\Modules\Product\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'offer_price' => $this->offer_price,
            'status' => $this->status,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'product' => new ProductResource($this->whenLoaded('product')),
            'buyer' => $this->whenLoaded('buyer', fn () => [
                'id' => $this->buyer->id,
                'full_name' => $this->buyer->full_name,
                'avatar_url' => $this->buyer->avatar_url,
            ]),
            'seller' => $this->whenLoaded('seller', fn () => [
                'id' => $this->seller->id,
                'full_name' => $this->seller->full_name,
                'avatar_url' => $this->seller->avatar_url,
            ]),
        ];
    }
}
