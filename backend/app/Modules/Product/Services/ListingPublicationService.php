<?php

namespace App\Modules\Product\Services;

use App\Modules\Admin\Services\MarketplaceSettings;

use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductPublication;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ListingPublicationService
{
    public function monthKey(): string
    {
        return now('Asia/Ho_Chi_Minh')->format('Y-m');
    }

    public function quote(int $sellerId): array
    {
        $month = $this->monthKey();
        $used = ProductPublication::where('seller_id', $sellerId)->where('month_key', $month)->where('status', 'published')->where('used_free_quota', true)->count();
        $settings = app(MarketplaceSettings::class);
        $quota = max(0, $settings->get('listing_free_monthly'));
        $free = max(0, $quota - $used);
        return ['month_key' => $month, 'free_quota' => $quota, 'used' => $used, 'remaining' => $free, 'fee' => $free > 0 ? 0 : max(0, $settings->get('listing_fee'))];
    }

    public function approve(Product $product): Product
    {
        app(WalletLedger::class)->ensureAccount((int) $product->seller_id);
        return DB::transaction(function () use ($product): Product {
            $locked = Product::lockForUpdate()->findOrFail($product->id);
            if ($locked->status !== Product::STATUS_PENDING_REVIEW) throw new \DomainException('Product is not pending review');
            $existing = ProductPublication::where('product_id', $locked->id)->first();
            if ($existing?->status === 'published') {
                $locked->update(['status' => Product::STATUS_ACTIVE]);
                return $locked->refresh()->load(['images', 'publication']);
            }

            DB::table('wallet_accounts')->where('user_id', $locked->seller_id)->lockForUpdate()->first();
            $quote = $this->quote((int) $locked->seller_id);
            $month = $quote['month_key'];
            $fee = $quote['fee'];
            if ($existing) {
                $existing->update(['month_key' => $month, 'fee_amount' => $fee, 'used_free_quota' => $fee === 0 && $quote['remaining'] > 0, 'status' => $fee === 0 ? 'published' : 'pending_payment', 'quoted_at' => now(), 'published_at' => $fee === 0 ? now() : null]);
                $publication = $existing->refresh();
            } else {
                $publication = ProductPublication::create([
                    'product_id' => $locked->id, 'seller_id' => $locked->seller_id, 'month_key' => $month,
                    'fee_amount' => $fee, 'used_free_quota' => $fee === 0 && $quote['remaining'] > 0, 'status' => $fee === 0 ? 'published' : 'pending_payment',
                    'reference' => 'LISTING_'.$locked->id, 'quoted_at' => now(), 'published_at' => $fee === 0 ? now() : null,
                ]);
            }
            $locked->update(['status' => $fee === 0 ? Product::STATUS_ACTIVE : Product::STATUS_PENDING_LISTING_PAYMENT]);
            return $locked->refresh()->load(['images', 'publication']);
        }, 3);
    }

    public function pay(int $sellerId, int $productId, int $expectedFee, WalletLedger $ledger): Product
    {
        $ledger->ensureAccount($sellerId);
        return DB::transaction(function () use ($sellerId, $productId, $expectedFee, $ledger): Product {
            $product = Product::where('seller_id', $sellerId)->lockForUpdate()->findOrFail($productId);
            if ($product->status !== Product::STATUS_PENDING_LISTING_PAYMENT) throw new ConflictHttpException('Tin chưa được duyệt hoặc không còn chờ thanh toán phí đăng.');
            DB::table('wallet_accounts')->where('user_id', $sellerId)->lockForUpdate()->first();
            $publication = ProductPublication::where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            if ($publication->status !== 'pending_payment') throw new ConflictHttpException('Khoản phí đăng tin đã được xử lý.');
            $quote = $this->quote($sellerId);
            if ($expectedFee !== $quote['fee']) throw new ConflictHttpException('Hạn mức tháng đã thay đổi. Chưa thu tiền; hãy tải lại báo giá mới là '.(int) $quote['fee'].'đ.');

            if ($quote['fee'] > 0) {
                $ledger->post($sellerId, $publication->reference, 'listing_fee', [
                    ['user_id' => $sellerId, 'account_code' => 'available', 'amount' => -$quote['fee']],
                    ['user_id' => null, 'account_code' => 'platform_revenue', 'amount' => $quote['fee']],
                ], 'product_publication', (int) $publication->id, ['amount' => $quote['fee'], 'month_key' => $quote['month_key']]);
            }
            $publication->update(['month_key' => $quote['month_key'], 'fee_amount' => $quote['fee'], 'used_free_quota' => $quote['fee'] === 0 && $quote['remaining'] > 0, 'status' => 'published', 'paid_at' => $quote['fee'] > 0 ? now() : null, 'published_at' => now()]);
            $product->update(['status' => Product::STATUS_ACTIVE]);
            return $product->refresh()->load(['images', 'publication']);
        }, 3);
    }
}
