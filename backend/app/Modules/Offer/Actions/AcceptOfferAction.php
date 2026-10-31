<?php

namespace App\Modules\Offer\Actions;

use App\Modules\Offer\Models\Offer;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AcceptOfferAction
{
    public function execute(int $offerId, int $sellerId): Offer
    {
        return DB::transaction(function () use ($offerId, $sellerId): Offer {
            $offer = Offer::with('product')->lockForUpdate()->findOrFail($offerId);

            // Validate offer is pending
            if (! $offer->isPending()) {
                throw new InvalidArgumentException('Only pending offers can be accepted.');
            }

            // Validate offer is not expired
            if ($offer->isExpired()) {
                throw new InvalidArgumentException('This offer has expired.');
            }

            // Validate seller owns the product
            if ($offer->seller_id !== $sellerId) {
                throw new InvalidArgumentException('You are not authorized to accept this offer.');
            }

            // Validate product is still active
            if ($offer->product->status !== Product::STATUS_ACTIVE) {
                throw new InvalidArgumentException('Product is no longer available.');
            }

            $anotherAcceptedOfferExists = Offer::where('product_id', $offer->product_id)
                ->where('status', Offer::STATUS_ACCEPTED)
                ->whereKeyNot($offer->id)
                ->exists();

            if ($anotherAcceptedOfferExists) {
                throw new InvalidArgumentException('Another offer has already been accepted for this product.');
            }

            // Accept the offer
            $offer->update(['status' => Offer::STATUS_ACCEPTED]);


            return $offer->load(['product.images', 'buyer', 'seller']);
        });
    }
}
