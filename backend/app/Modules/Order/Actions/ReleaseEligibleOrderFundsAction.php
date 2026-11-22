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
            ->whereIn('payout_status', ['eligible', 'awaiting_dispute_window', 'awaiting_carrier_settlement'])
            ->whereNotNull('release_eligible_at')->where('release_eligible_at', '<=', now())
            ->orderBy('id')->limit(500)->pluck('id');
        $released = 0;
        foreach ($ids as $id) {
            $sellerId = DB::transaction(function () use ($id, $ledger): ?int {
                $order = Order::lockForUpdate()->find($id);
                if (! $order || $order->payment_status !== 'paid' || $order->payout_status === 'credited_to_wallet'
                    || ! $order->release_eligible_at || $order->release_eligible_at->isFuture()
                    || $order->disputes()->where('status', 'open')->exists()) {
                    return null;
                }
                if ($order->payment_method === 'cod' && $order->cod_remaining_collection_type !== 'direct' && ! $order->carrier_settled_at) {
                    return null;
                }
                if (! in_array($order->payout_status, ['eligible', 'awaiting_dispute_window', 'awaiting_carrier_settlement'], true)) {
                    return null;
                }

                $ledger->releaseOrderToWallet($order);
                $releasedAt = now();
                $order->update([
                    'status' => Order::STATUS_COMPLETED,
                    'completed_at' => $order->completed_at ?? $releasedAt,
                    'payout_status' => 'credited_to_wallet',
                    'seller_paid_at' => $releasedAt,
                    'wallet_credited_at' => $releasedAt,
                ]);

                return (int) $order->seller_id;
            }, 3);
            if ($sellerId) {
                $released++;
                $order = Order::with(['buyer', 'seller'])->find($id);
                if ($order) {
                    $notifications->send($order->seller, null, 'order_wallet_credited', 'Tiền bán hàng đã vào số dư', 'Tiền thuần của đơn #'.$order->id.' đã được ghi vào số dư H-Smart mô phỏng.', '/me/wallet', ['order_id' => $order->id]);
                    $notifications->send($order->buyer, null, 'order_protection_window_ended', 'Đơn hàng đã hoàn tất', 'Đơn #'.$order->id.' đã đủ điều kiện kết thúc theo xác nhận giao hàng.', '/me/orders', ['order_id' => $order->id]);
                }
            }
        }

        return $released;
    }
}
