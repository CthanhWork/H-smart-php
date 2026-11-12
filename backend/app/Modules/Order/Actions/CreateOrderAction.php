<?php

namespace App\Modules\Order\Actions;

use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateOrderAction
{
    public function execute(int $buyerId, int $offerId, array $address, string $paymentMethod = 'vnpay'): Order
    {
        return DB::transaction(function () use ($buyerId, $offerId, $address, $paymentMethod): Order {
            $offer = Offer::findOrFail($offerId);
            $product = Product::lockForUpdate()->findOrFail($offer->product_id);

            $expiredOrders = Order::where('product_id', $product->id)
                ->where('status', Order::STATUS_PENDING_PAYMENT)
                ->where('expires_at', '<=', now())
                ->lockForUpdate()
                ->get();
            foreach ($expiredOrders as $expiredOrder) {
                $expiredOrder->update([
                    'status' => Order::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'payment_status' => 'expired',
                ]);
                if ($expiredOrder->offer_id) {
                    Offer::whereKey($expiredOrder->offer_id)->where('status', Offer::STATUS_ORDERED)->update(['status' => Offer::STATUS_ACCEPTED]);
                }
                $product->update(['status' => Product::STATUS_ACTIVE]);
            }

            $offer = Offer::lockForUpdate()->findOrFail($offerId);
            if ($offer->buyer_id !== $buyerId) {
                abort(404);
            }
            if ($offer->status !== Offer::STATUS_ACCEPTED) {
                throw new ConflictHttpException('Chỉ có thể tạo đơn từ đề nghị đã được người bán chấp nhận.');
            }
            if ($product->status !== Product::STATUS_ACTIVE) {
                throw new ConflictHttpException('Sản phẩm hiện không còn khả dụng.');
            }
            if (Order::where('product_id', $product->id)->whereIn('status', Order::HOLDING_STATUSES)->exists()) {
                throw new ConflictHttpException('Sản phẩm đã có đơn hàng đang xử lý.');
            }

            $productAmount = (int) $offer->offer_price;
            $shippingFee = max(0, (int) config('services.marketplace.shipping_fee', 25000));
            $commissionRate = min(100, max(0, (int) config('services.marketplace.commission_rate', 5)));
            $platformFee = intdiv($productAmount * $commissionRate, 100);
            $order = Order::create([
                'product_id' => $product->id,
                'buyer_id' => $buyerId,
                'seller_id' => $offer->seller_id,
                'offer_id' => $offer->id,
                'product_amount' => $offer->offer_price,
                'shipping_fee' => $shippingFee,
                'platform_fee' => $platformFee,
                'commission_rate' => $commissionRate,
                'total_amount' => $productAmount + $shippingFee,
                'status' => Order::STATUS_PENDING_PAYMENT,
                'shipping_address_snapshot' => $address,
                'expires_at' => now()->addMinutes(30),
                'payment_provider' => $paymentMethod,
                'payment_amount' => $productAmount + $shippingFee,
                'payment_status' => 'pending',
                'delivery_status' => $paymentMethod === 'cod' ? 'awaiting_shipment' : null,
            ]);

            $product->update(['status' => Product::STATUS_RESERVED]);
            $offer->update(['status' => Offer::STATUS_ORDERED]);

            return $order->load(['product.images', 'buyer', 'seller', 'offer', 'statusHistory']);
        });
    }
}
