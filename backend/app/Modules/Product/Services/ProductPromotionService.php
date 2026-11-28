<?php

namespace App\Modules\Product\Services;

use App\Modules\Admin\Services\MarketplaceSettings;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductPromotion;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProductPromotionService
{
    public function packages(): array
    {
        $settings = app(MarketplaceSettings::class);

        return [
            'boost' => ['fee' => max(0, $settings->get('boost_price')), 'days' => max(1, $settings->get('boost_days')), 'scope' => 'Danh mục'],
            'vip' => ['fee' => max(0, $settings->get('vip_price')), 'days' => max(1, $settings->get('vip_days')), 'scope' => 'Trang chủ và danh mục'],
        ];
    }

    public function purchase(int $sellerId, int $productId, string $packageType, int $expectedFee, string $idempotencyKey, WalletLedger $ledger): array
    {
        $ledger->ensureAccount($sellerId);

        return DB::transaction(function () use ($sellerId, $productId, $packageType, $expectedFee, $idempotencyKey, $ledger): array {
            DB::table('wallet_accounts')->where('user_id', $sellerId)->lockForUpdate()->first();
            $existing = ProductPromotion::where('seller_id', $sellerId)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->seller_id !== $sellerId || (int) $existing->product_id !== $productId || $existing->package_type !== $packageType) {
                    throw new ConflictHttpException('Mã yêu cầu đã được dùng cho một giao dịch khác.');
                }
                if ((int) $existing->fee_amount !== $expectedFee) {
                    throw new ConflictHttpException('Mã yêu cầu đã được dùng với mức phí khác. Hãy tạo yêu cầu mới.');
                }
                $product = Product::where('seller_id', $sellerId)->with(['images', 'category', 'seller:id,username,full_name', 'currentPromotion', 'nextPromotion'])->findOrFail($productId);

                return ['promotion' => $existing, 'product' => $product];
            }
            $product = Product::where('seller_id', $sellerId)->lockForUpdate()->findOrFail($productId);
            if ($product->status !== Product::STATUS_ACTIVE) {
                throw new ConflictHttpException('Chỉ có thể đẩy tin đang được duyệt và đang bán.');
            }
            $package = $this->packages()[$packageType] ?? null;
            if (! $package) {
                throw new ConflictHttpException('Gói ưu tiên không hợp lệ.');
            }
            if ($expectedFee !== $package['fee']) {
                throw new ConflictHttpException('Giá gói đã thay đổi. Chưa thu tiền; hãy xem lại giá hiện tại.');
            }
            $active = ProductPromotion::where('product_id', $product->id)->where('package_type', $packageType)
                ->where('status', 'active')->where('expires_at', '>', now())->orderByDesc('expires_at')->lockForUpdate()->first();
            $startsAt = $active && $active->expires_at->isFuture() ? $active->expires_at->copy() : now();
            $expiresAt = $startsAt->copy()->addDays($package['days']);
            $reference = 'PROMO_'.substr(hash('sha256', $sellerId.':'.$idempotencyKey), 0, 64);
            $ledger->post($sellerId, $reference, 'product_promotion', [
                ['user_id' => $sellerId, 'account_code' => 'available', 'amount' => -$package['fee']],
                ['user_id' => null, 'account_code' => 'platform_revenue', 'amount' => $package['fee']],
            ], 'product_promotion', (int) $product->id, ['package_type' => $packageType, 'amount' => $package['fee'], 'starts_at' => $startsAt->toIso8601String(), 'expires_at' => $expiresAt->toIso8601String()]);
            $promotion = ProductPromotion::create([
                'product_id' => $product->id, 'seller_id' => $sellerId, 'package_type' => $packageType,
                'fee_amount' => $package['fee'], 'status' => 'active', 'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'starts_at' => $startsAt, 'expires_at' => $expiresAt,
            ]);

            return ['promotion' => $promotion, 'product' => $product->load(['images', 'category', 'seller:id,username,full_name', 'currentPromotion', 'nextPromotion'])];
        }, 3);
    }

    public function expire(): int
    {
        return ProductPromotion::where('status', 'active')->where('expires_at', '<=', now())->update(['status' => 'expired', 'updated_at' => now()]);
    }
}
