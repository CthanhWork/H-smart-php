<?php

namespace App\Modules\Offer\Policies;

use App\Modules\Offer\Models\Offer;
use App\Modules\User\Models\User;

class OfferPolicy
{
    public function view(User $user, Offer $offer): bool
    {
        // User can view if they are the buyer or seller
        return $offer->buyer_id === $user->id || $offer->seller_id === $user->id;
    }

    public function accept(User $user, Offer $offer): bool
    {
        // Let the action return a validation error for expired or non-pending offers.
        return $offer->seller_id === $user->id;
    }

    public function reject(User $user, Offer $offer): bool
    {
        // Only the seller can reject
        return $offer->seller_id === $user->id && $offer->isPending();
    }

    public function cancel(User $user, Offer $offer): bool
    {
        // Only the buyer can cancel
        return $offer->buyer_id === $user->id && $offer->isPending();
    }
}
