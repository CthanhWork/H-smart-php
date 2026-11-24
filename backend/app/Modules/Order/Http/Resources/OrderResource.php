<?php

namespace App\Modules\Order\Http\Resources;

use App\Modules\Product\Http\Resources\ProductResource;
use App\Modules\Order\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $canSeeSellerFinance = $user && ($user->role === 'admin' || (int) $user->id === (int) $this->seller_id);
        $isBuyer = $user && (int) $user->id === (int) $this->buyer_id;
        $hasOpenDispute = $isBuyer && ($this->resource->relationLoaded('disputes')
            ? $this->disputes->contains(fn ($dispute) => $dispute->status === 'open')
            : $this->disputes()->where('status', 'open')->exists());

        return [
            'id' => $this->id,
            'product_amount' => $this->product_amount,
            'shipping_fee' => $this->shipping_fee,
            'purchase_type' => $this->purchase_type,
            'policy_version' => $this->policy_version,
            'policy_accepted_at' => $this->policy_accepted_at?->toIso8601String(),
            ...($canSeeSellerFinance ? ['seller_financials' => [
                'fee_base' => (int) $this->fee_base,
                'commission_rate' => (int) $this->commission_rate,
                'platform_fee' => (int) $this->platform_fee,
                'seller_net_amount' => max(0, (int) $this->product_amount - (int) $this->platform_fee),
                'payout_status' => $this->payout_status,
                'release_eligible_at' => $this->release_eligible_at?->toIso8601String(),
                'carrier_settled_at' => $this->carrier_settled_at?->toIso8601String(),
                'seller_paid_at' => $this->seller_paid_at?->toIso8601String(),
                'wallet_credited_at' => $this->wallet_credited_at?->toIso8601String(),
                'cod_remaining_collection_type' => $this->cod_remaining_collection_type,
            ]] : []),
            ...($isBuyer ? ['can_open_dispute' => in_array($this->status, [Order::STATUS_SHIPPING, Order::STATUS_COMPLETED], true)
                && $this->payment_status === 'paid' && $this->payout_status !== 'credited_to_wallet'
                && $this->seller_paid_at === null && ! $hasOpenDispute] : []),
            'total_amount' => $this->total_amount,
            'cod_collected_at' => $this->cod_collected_at?->toIso8601String(),
            'cod_deposit_percent' => $this->cod_deposit_percent,
            'cod_deposit_amount' => $this->cod_deposit_amount,
            'cod_remaining_amount' => $this->cod_remaining_amount,
            'cod_remaining_collected_at' => $this->cod_remaining_collected_at?->toIso8601String(),
            'cod_deposit_wallet_consent_at' => $this->cod_deposit_wallet_consent_at?->toIso8601String(),
            ...($canSeeSellerFinance ? [
                'cod_remaining_collection_type' => $this->cod_remaining_collection_type,
                'carrier_settled_at' => $this->carrier_settled_at?->toIso8601String(),
            ] : []),
            'disputes' => $this->whenLoaded('disputes', fn () => $this->disputes->map(fn ($dispute) => [
                'id' => $dispute->id, 'opened_by' => $dispute->opened_by, 'status' => $dispute->status,
                'reason' => $dispute->reason, 'details' => $dispute->details, 'evidence_notes' => $dispute->evidence_notes,
                'resolution' => $dispute->resolution, 'resolution_note' => $dispute->resolution_note,
                'buyer_wallet_refund_consent_at' => $dispute->buyer_wallet_refund_consent_at?->toIso8601String(),
                'created_at' => $dispute->created_at?->toIso8601String(), 'resolved_at' => $dispute->resolved_at?->toIso8601String(),
                'events' => $dispute->events->map(fn ($event) => ['action' => $event->action, 'note' => $event->note, 'created_at' => $event->created_at?->toIso8601String()])->values(),
            ])->values()),
            'delivery_failed_reason' => $this->delivery_failed_reason,
            'status' => $this->status,
            'shipping_address' => $this->shipping_address_snapshot,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'shipped_at' => $this->shipped_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'shipping_carrier' => $this->shipping_carrier,
            'tracking_number' => $this->tracking_number,
            'delivery_status' => $this->delivery_status,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($event) => [
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'changed_at' => $event->changed_at?->toIso8601String(),
            ])->values()),
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
            'review' => $this->review_rating === null ? null : [
                'id' => $this->id, 'rating' => $this->review_rating, 'comment' => $this->review_comment,
            ],
            'payment' => $this->payment_status === null ? null : [
                'id' => $this->id,
                'txn_ref' => $this->payment_txn_ref,
                'provider' => $this->payment_provider,
                'method' => $this->payment_method,
                'amount' => $this->payment_amount,
                'status' => $this->payment_status,
                'paid_at' => $this->payment_paid_at?->toIso8601String(),
            ],
        ];
    }
}
