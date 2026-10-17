<?php

namespace App\Modules\Order\Actions;

use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SimulatePaymentAction
{
    public function execute(int $orderId, int $buyerId): Order
    {
        return DB::transaction(function () use ($orderId, $buyerId): Order {
            $snapshot = Order::findOrFail($orderId);
            $product = Product::lockForUpdate()->findOrFail($snapshot->product_id);
            $order = Order::lockForUpdate()->findOrFail($orderId);
            abort_unless($order->buyer_id === $buyerId, 404);
            if ($order->status !== Order::STATUS_PENDING_PAYMENT || $order->payment_status !== 'pending') {
                throw new ConflictHttpException('Đơn hàng không còn chờ thanh toán.');
            }
            if ($order->expires_at->isPast()) {
                $order->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now(), 'payment_status' => 'expired']);
                $product->update(['status' => Product::STATUS_ACTIVE]);
                if ($order->offer_id) {
                    Offer::whereKey($order->offer_id)->update(['status' => Offer::STATUS_ACCEPTED]);
                }

                return $order->load(['product.images', 'buyer', 'seller', 'offer']);
            }

            if ($product->status !== Product::STATUS_RESERVED) {
                throw new ConflictHttpException('Sản phẩm không còn được giữ cho đơn này.');
            }

            $paidAt = now();
            $order->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => $paidAt,
                'payment_status' => 'paid',
                'payment_paid_at' => $paidAt,
                'payment_response_code' => '00',
            ]);
            $product->update(['status' => Product::STATUS_SOLD]);
            if ($order->offer_id) {
                Offer::where('product_id', $product->id)
                    ->where('status', Offer::STATUS_PENDING)
                    ->update(['status' => Offer::STATUS_REJECTED]);
            }

            return $order->load(['product.images', 'buyer', 'seller', 'offer']);
        });
    }
}
