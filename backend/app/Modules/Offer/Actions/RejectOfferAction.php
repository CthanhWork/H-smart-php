<?php

namespace App\Modules\Offer\Actions;

use App\Modules\Offer\Models\Offer;
use InvalidArgumentException;

class RejectOfferAction
{
    public function execute(int $offerId, int $sellerId): Offer
    {
        $offer = Offer::with('product')->findOrFail($offerId);

        // Validate offer is pending
        if (! $offer->isPending()) {
            throw new InvalidArgumentException('Only pending offers can be rejected.');
        }

        // Validate seller owns the product
        if ($offer->seller_id !== $sellerId) {
            throw new InvalidArgumentException('You are not authorized to reject this offer.');
        }

        $offer->update(['status' => Offer::STATUS_REJECTED]);


        return $offer->load(['product.images', 'buyer', 'seller']);
    }
}
