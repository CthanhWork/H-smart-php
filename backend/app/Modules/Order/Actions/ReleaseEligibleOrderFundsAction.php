<?php

namespace App\Modules\Order\Actions;

use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Models\Order;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Support\Facades\DB;

class ReleaseEligibleOrderFundsAction
{
    public function execute(WalletLedger $ledger, NotificationService $notifications): int
    {
        $ids = Order::query()->where('payment_status', 'paid')
            ->whereIn('payout_status', ['awaiting_dispute_window', 'awaiting_carrier_settlement'])
            ->whereNotNull('release_eligible_at')->where('release_eligible_at', '<=', now())
            ->orderBy('id')->limit(500)->pluck('id');
        $eligible = 0;
        foreach ($ids as $id) {
            $marked = DB::transaction(function () use ($id): bool {
                $order = Order::lockForUpdate()->find($id);
                if (! $order || $order->payment_status !== 'paid' || $order->payout_status === 'credited_to_wallet'
                    || ! $order->release_eligible_at || $order->release_eligible_at->isFuture()
                    || $order->disputes()->whereIn('status', ['open', 'reconciliation_pending'])->exists()) {
                    return false;
                }
                if ($order->payment_method === 'cod' && $order->cod_remaining_collection_type !== 'direct' && ! $order->carrier_settled_at) {
                    return null;
                }
                if (! in_array($order->payout_status, ['eligible', 'awaiting_dispute_window', 'awaiting_carrier_settlement'], true)) {
                    return false;
                }
                $order->update(['payout_status' => 'eligible']);

                return true;
            }, 3);
            if ($marked) {
                $eligible++;
            }
        }

        return $eligible;
    }
}
