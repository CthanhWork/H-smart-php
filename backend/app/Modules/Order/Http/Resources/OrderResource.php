<?php

namespace App\Modules\Order\Http\Resources;

use App\Modules\Product\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_amount' => $this->product_amount,
            'shipping_fee' => $this->shipping_fee,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'shipping_address' => $this->shipping_address_snapshot,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'product' => new ProductResource($this->whenLoaded('product')),
            'buyer' => $this->whenLoaded('buyer', fn () => [
                'id' => $this->buyer->id,
                'full_name' => $this->buyer->full_name,
            ]),
            'seller' => $this->whenLoaded('seller', fn () => [
                'id' => $this->seller->id,
                'full_name' => $this->seller->full_name,
            ]),
            'offer_id' => $this->offer_id,
            'review' => $this->whenLoaded('review', fn () => $this->review ? [
                'id' => $this->review->id, 'rating' => $this->review->rating, 'comment' => $this->review->comment,
            ] : null),
            'payment' => $this->whenLoaded('payment', fn () => [
                'id' => $this->payment->id,
                'txn_ref' => $this->payment->txn_ref,
                'provider' => $this->payment->provider,
                'amount' => $this->payment->amount,
                'status' => $this->payment->status,
                'paid_at' => $this->payment->paid_at?->toIso8601String(),
            ]),
        ];
    }
}
