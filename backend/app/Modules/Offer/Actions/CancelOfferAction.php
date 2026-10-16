<?php

namespace App\Modules\Offer\Actions;

use App\Modules\Offer\Models\Offer;
use InvalidArgumentException;

class CancelOfferAction
{
    public function execute(int $offerId, int $buyerId): Offer
    {
        $offer = Offer::with('product')->findOrFail($offerId);

        // Validate offer is pending
        if (! $offer->isPending()) {
            throw new InvalidArgumentException('Only pending offers can be cancelled.');
        }

        // Validate buyer owns the offer
        if ($offer->buyer_id !== $buyerId) {
            throw new InvalidArgumentException('You are not authorized to cancel this offer.');
        }

        $offer->update(['status' => Offer::STATUS_CANCELLED]);

        // TODO: Fire OfferCancelled event for notifications

        return $offer->load(['product.images', 'buyer', 'seller']);
    }
}
