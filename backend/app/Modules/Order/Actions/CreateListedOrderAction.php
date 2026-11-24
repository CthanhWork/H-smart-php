<?php

namespace App\Modules\Order\Actions;

use App\Modules\Admin\Services\MarketplaceSettings;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateListedOrderAction
{
    public function execute(int $buyerId, int $productId, int $expectedPrice, string $idempotencyKey, array $address, string $paymentMethod = 'vnpay', ?MarketplaceSettings $settings = null, array $expectedPricing = []): Order
    {
        try {
            return DB::transaction(function () use ($buyerId, $productId, $expectedPrice, $idempotencyKey, $address, $paymentMethod, $settings, $expectedPricing): Order {
                $existing = Order::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $this->replayExisting($existing, $buyerId, $productId, $expectedPrice, $address, $paymentMethod, $expectedPricing);
                }

                $product = Product::lockForUpdate()->findOrFail($productId);
                if ($product->seller_id === $buyerId) {
                    throw new ConflictHttpException('Bạn không thể mua sản phẩm do chính mình đăng.');
                }
                if ($product->status !== Product::STATUS_ACTIVE) {
                    throw new ConflictHttpException('Sản phẩm hiện không còn khả dụng.');
                }
                if ((int) $product->price !== $expectedPrice) {
                    throw new ConflictHttpException('Giá sản phẩm đã thay đổi. Vui lòng kiểm tra giá mới và xác nhận lại trước khi đặt hàng.');
                }
                if (Order::where('product_id', $product->id)->whereIn('status', Order::HOLDING_STATUSES)->lockForUpdate()->exists()) {
                    throw new ConflictHttpException('Sản phẩm đã được người khác đặt mua.');
                }

                $amount = (int) $product->price;
                $settings ??= app(MarketplaceSettings::class);
                $shippingFee = max(0, $settings->get('shipping_fee'));
                $rate = min(100, max(0, $settings->get('commission_rate')));
                $depositPercent = min(30, max(20, $settings->get('cod_deposit_percent')));
                if ($expectedPricing !== [] && ((int) ($expectedPricing['shipping_fee'] ?? -1) !== $shippingFee
                    || (isset($expectedPricing['commission_rate']) && (int) $expectedPricing['commission_rate'] !== $rate)
                    || (int) ($expectedPricing['cod_deposit_percent'] ?? -1) !== $depositPercent)) {
                    throw new ConflictHttpException('Phí hoặc tỷ lệ đặt cọc đã thay đổi. Vui lòng kiểm tra báo giá mới và xác nhận lại.');
                }
                $codDeposit = $paymentMethod === 'cod' ? (int) ceil($amount * $depositPercent / 100) : 0;
                $feeBase = $amount + $shippingFee;
                $fee = intdiv($feeBase * $rate, 100);
                if ($fee > $amount) {
                    throw new ConflictHttpException('Giá sản phẩm thấp hơn mức cần thiết để trừ phí sàn; không thể tạo đơn này.');
                }
                if ($paymentMethod === 'cod' && $codDeposit < $fee) {
                    throw new ConflictHttpException('Tiền cọc COD thấp hơn hoa hồng của đơn; hãy dùng phương thức thanh toán khác.');
                }
                $order = Order::create([
                    'product_id' => $product->id,
                    'buyer_id' => $buyerId,
                    'seller_id' => $product->seller_id,
                    'offer_id' => null,
                    'purchase_type' => 'listed_price',
                    'idempotency_key' => $idempotencyKey,
                    'product_amount' => $amount,
                    'shipping_fee' => $shippingFee,
                    'fee_base' => $feeBase,
                    'platform_fee' => $fee,
                    'commission_rate' => $rate,
                    'total_amount' => $amount + $shippingFee,
                    'status' => Order::STATUS_PENDING_PAYMENT,
                    'shipping_address_snapshot' => $address,
                    'expires_at' => now()->addMinutes(30),
                    'payment_provider' => $paymentMethod,
                    'payment_amount' => $paymentMethod === 'cod' ? $codDeposit : $amount + $shippingFee,
                    'payment_status' => 'pending',
                    'payment_method' => $paymentMethod,
                    'cod_deposit_percent' => $paymentMethod === 'cod' ? $depositPercent : null,
                    'cod_deposit_amount' => $codDeposit,
                    'cod_remaining_amount' => $paymentMethod === 'cod' ? $amount - $codDeposit : 0,
                    'policy_version' => config('services.marketplace.policy_version', 'commerce-v1'),
                    'policy_accepted_at' => now(),
                    'payout_status' => 'not_ready',
                    'delivery_status' => $paymentMethod === 'cod' ? 'awaiting_shipment' : null,
                ]);
                $product->update(['status' => Product::STATUS_RESERVED]);
                DB::table('cart_items')->where('user_id', $buyerId)->where('product_id', $product->id)->delete();

                return $order->load(['product.images', 'buyer', 'seller', 'statusHistory']);
            }, 3);
        } catch (QueryException $exception) {
            $existing = Order::where('idempotency_key', $idempotencyKey)->first();
            if (! $existing) {
                throw $exception;
            }

            return $this->replayExisting($existing, $buyerId, $productId, $expectedPrice, $address, $paymentMethod, $expectedPricing);
        }
    }

    private function replayExisting(Order $existing, int $buyerId, int $productId, int $expectedPrice, array $address, string $paymentMethod, array $expectedPricing): Order
    {
        abort_unless($existing->buyer_id === $buyerId && $existing->product_id === $productId, 404);
        $samePayload = (int) $existing->product_amount === $expectedPrice
            && $existing->payment_method === $paymentMethod
            && $existing->shipping_address_snapshot === $address;
        if ($expectedPricing !== []) {
            $samePayload = $samePayload
                && (int) $existing->shipping_fee === (int) ($expectedPricing['shipping_fee'] ?? -1)
                && (! isset($expectedPricing['commission_rate']) || (int) $existing->commission_rate === (int) $expectedPricing['commission_rate']);
            if ($paymentMethod === 'cod') {
                $samePayload = $samePayload
                    && (int) $existing->cod_deposit_percent === (int) ($expectedPricing['cod_deposit_percent'] ?? -1);
            }
        }
        if (! $samePayload) {
            throw new ConflictHttpException('Mã yêu cầu đã được dùng với thông tin đơn hàng khác. Hãy tạo yêu cầu mới.');
        }

        return $existing->load(['product.images', 'buyer', 'seller', 'statusHistory']);
    }
}
