<?php

namespace App\Modules\Order\Actions;

use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\Admin\Services\MarketplaceSettings;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateOrderAction
{
    public function execute(int $buyerId, int $offerId, array $address, string $paymentMethod = 'vnpay', ?MarketplaceSettings $settings = null, array $expectedPricing = []): Order
    {
        return DB::transaction(function () use ($buyerId, $offerId, $address, $paymentMethod, $settings, $expectedPricing): Order {
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
            $settings ??= app(MarketplaceSettings::class);
            $shippingFee = max(0, $settings->get('shipping_fee'));
            $commissionRate = min(100, max(0, $settings->get('commission_rate')));
            $depositPercent = min(30, max(20, $settings->get('cod_deposit_percent')));
            if ($expectedPricing !== [] && ((int) ($expectedPricing['shipping_fee'] ?? -1) !== $shippingFee
                || (isset($expectedPricing['commission_rate']) && (int) $expectedPricing['commission_rate'] !== $commissionRate)
                || (int) ($expectedPricing['cod_deposit_percent'] ?? -1) !== $depositPercent)) {
                throw new ConflictHttpException('Phí hoặc tỷ lệ đặt cọc đã thay đổi. Vui lòng kiểm tra báo giá mới và xác nhận lại.');
            }
            $codDeposit = $paymentMethod === 'cod' ? (int) ceil($productAmount * $depositPercent / 100) : 0;
            $feeBase = $productAmount + $shippingFee;
            $platformFee = intdiv($feeBase * $commissionRate, 100);
            if ($platformFee > $productAmount) {
                throw new ConflictHttpException('Giá sản phẩm thấp hơn mức cần thiết để trừ phí sàn; không thể tạo đơn này.');
            }
            if ($paymentMethod === 'cod' && $codDeposit < $platformFee) {
                throw new ConflictHttpException('Tiền cọc COD thấp hơn hoa hồng của đơn; hãy dùng phương thức thanh toán khác.');
            }
            $order = Order::create([
                'product_id' => $product->id,
                'buyer_id' => $buyerId,
                'seller_id' => $offer->seller_id,
                'offer_id' => $offer->id,
                'purchase_type' => 'offer',
                'product_amount' => $offer->offer_price,
                'shipping_fee' => $shippingFee,
                'fee_base' => $feeBase,
                'platform_fee' => $platformFee,
                'commission_rate' => $commissionRate,
                'total_amount' => $productAmount + $shippingFee,
                'status' => Order::STATUS_PENDING_PAYMENT,
                'shipping_address_snapshot' => $address,
                'expires_at' => now()->addMinutes(30),
                'payment_provider' => $paymentMethod,
                'payment_amount' => $paymentMethod === 'cod' ? $codDeposit : $productAmount + $shippingFee,
                'payment_status' => 'pending',
                'payment_method' => $paymentMethod,
                'cod_deposit_percent' => $paymentMethod === 'cod' ? $depositPercent : null,
                'cod_deposit_amount' => $codDeposit,
                'cod_remaining_amount' => $paymentMethod === 'cod' ? $productAmount - $codDeposit : 0,
                'policy_version' => config('services.marketplace.policy_version', 'commerce-v1'),
                'policy_accepted_at' => now(),
                'delivery_status' => $paymentMethod === 'cod' ? 'awaiting_shipment' : null,
            ]);

            $product->update(['status' => Product::STATUS_RESERVED]);
            $offer->update(['status' => Offer::STATUS_ORDERED]);

            return $order->load(['product.images', 'buyer', 'seller', 'offer', 'statusHistory']);
        });
    }
}
