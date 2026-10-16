<?php

namespace App\Modules\Order\Actions;

use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Payment;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;

class ExpirePendingOrdersAction
{
    public function execute(): int
    {
        $orderIds = Order::where('status', Order::STATUS_PENDING_PAYMENT)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');
        $expiredCount = 0;

        foreach ($orderIds as $orderId) {
            $expired = DB::transaction(function () use ($orderId): bool {
                $snapshot = Order::find($orderId);
                if (! $snapshot) {
                    return false;
                }

                $product = Product::lockForUpdate()->find($snapshot->product_id);
                $order = Order::lockForUpdate()->find($orderId);
                if (! $product || ! $order
                    || $order->status !== Order::STATUS_PENDING_PAYMENT
                    || $order->expires_at->isFuture()) {
                    return false;
                }

                $payment = Payment::where('order_id', $order->id)->lockForUpdate()->first();
                if (! $payment || $payment->status !== 'pending') {
                    return false;
                }

                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);
                $payment->update(['status' => 'expired']);
                if ($product->status === Product::STATUS_RESERVED) {
                    $product->update(['status' => Product::STATUS_ACTIVE]);
                }
                if ($order->offer_id) {
                    Offer::whereKey($order->offer_id)
                        ->where('status', Offer::STATUS_ORDERED)
                        ->update(['status' => Offer::STATUS_ACCEPTED]);
                }

                return true;
            });

            $expiredCount += $expired ? 1 : 0;
        }

        return $expiredCount;
    }
}
