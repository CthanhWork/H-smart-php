<?php

namespace App\Modules\Order\Actions;

use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\Payment;
use App\Modules\Product\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateOrderAction
{
    public function execute(int $buyerId, int $offerId, array $address): Order
    {
        return DB::transaction(function () use ($buyerId, $offerId, $address): Order {
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
                ]);
                $expiredOrder->payment()->update(['status' => 'expired']);
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

            $order = Order::create([
                'product_id' => $product->id,
                'buyer_id' => $buyerId,
                'seller_id' => $offer->seller_id,
                'offer_id' => $offer->id,
                'product_amount' => $offer->offer_price,
                'shipping_fee' => 0,
                'platform_fee' => 0,
                'total_amount' => $offer->offer_price,
                'status' => Order::STATUS_PENDING_PAYMENT,
                'shipping_address_snapshot' => $address,
                'expires_at' => now()->addMinutes(30),
            ]);

            Payment::create([
                'order_id' => $order->id,
                'txn_ref' => 'MOCK-'.Str::upper((string) Str::uuid()),
                'provider' => 'mock',
                'amount' => $order->total_amount,
                'status' => 'pending',
            ]);

            $product->update(['status' => Product::STATUS_RESERVED]);
            $offer->update(['status' => Offer::STATUS_ORDERED]);

            return $order->load(['product.images', 'buyer', 'seller', 'offer', 'payment', 'review']);
        });
    }
}
