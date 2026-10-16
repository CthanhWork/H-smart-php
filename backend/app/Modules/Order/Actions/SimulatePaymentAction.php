<?php

namespace App\Modules\Order\Actions;

use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Payment;
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
            $payment = Payment::where('order_id', $order->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== Order::STATUS_PENDING_PAYMENT || $payment->status !== 'pending') {
                throw new ConflictHttpException('Đơn hàng không còn chờ thanh toán.');
            }
            if ($order->expires_at->isPast()) {
                $order->update(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()]);
                $payment->update(['status' => 'expired']);
                $product->update(['status' => Product::STATUS_ACTIVE]);
                if ($order->offer_id) {
                    Offer::whereKey($order->offer_id)->update(['status' => Offer::STATUS_ACCEPTED]);
                }

                return $order->load(['product.images', 'buyer', 'seller', 'offer', 'payment']);
            }

            if ($product->status !== Product::STATUS_RESERVED) {
                throw new ConflictHttpException('Sản phẩm không còn được giữ cho đơn này.');
            }

            $payment->update(['status' => 'paid', 'paid_at' => now(), 'response_code' => '00']);
            $order->update(['status' => Order::STATUS_PAID, 'paid_at' => now()]);
            $product->update(['status' => Product::STATUS_SOLD]);
            if ($order->offer_id) {
                Offer::where('product_id', $product->id)
                    ->where('status', Offer::STATUS_PENDING)
                    ->update(['status' => Offer::STATUS_REJECTED]);
            }

            return $order->load(['product.images', 'buyer', 'seller', 'offer', 'payment']);
        });
    }
}
