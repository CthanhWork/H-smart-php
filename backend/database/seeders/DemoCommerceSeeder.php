<?php

namespace Database\Seeders;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderDispute;
use App\Modules\Order\Models\OrderDisputeEvent;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Modules\Product\Models\CartItem;
use App\Modules\Product\Models\Category;
use App\Modules\Product\Models\Product;
use App\Modules\Product\Models\ProductPromotion;
use App\Modules\Product\Models\ProductPublication;
use App\Modules\Product\Services\ProductPromotionService;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Models\WithdrawalRequest;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DemoCommerceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Commerce demo data may only be seeded in local/testing.');
        }

        $password = (string) env('DEMO_USER_PASSWORD', '');
        if (strlen($password) < 16 || str_starts_with($password, 'replace-')) {
            throw new \RuntimeException('Set DEMO_USER_PASSWORD to a private password of at least 16 characters in the ignored .env file.');
        }

        $seller = User::where('email', 'demo-seller@hsmart.local')->firstOrFail();
        $seller->forceFill(['password_hash' => Hash::make($password), 'role' => 'member', 'status' => 'active'])->save();
        $buyer = User::firstOrCreate(['email' => 'demo-buyer@hsmart.local'], [
            'username' => 'demo-buyer', 'password_hash' => Hash::make($password), 'role' => 'member', 'status' => 'active', 'full_name' => 'Người mua mẫu',
        ]);
        if ($buyer->wasRecentlyCreated === false) $buyer->forceFill(['password_hash' => Hash::make($password), 'role' => 'member', 'status' => 'active'])->save();

        $category = Category::where('slug', 'do-gia-dung')->first() ?? Category::first();
        if (! $category) throw new \RuntimeException('Seed categories before commerce demo data.');

        $listings = Product::where('seller_id', $seller->id)->orderBy('id')->limit(5)->get();
        if ($listings->count() < 5) throw new \RuntimeException('Seed demo products before commerce demo data.');
        $monthKey = now('Asia/Ho_Chi_Minh')->format('Y-m');
        foreach ($listings as $product) {
            ProductPublication::updateOrCreate(['product_id' => $product->id], [
                'seller_id' => $seller->id, 'month_key' => $monthKey, 'fee_amount' => 0,
                'used_free_quota' => true, 'status' => 'published',
                'reference' => 'DEMO-PUBLICATION-'.$product->id,
                'quoted_at' => now(), 'published_at' => now(),
            ]);
        }

        $activeVipProduct = $listings[0];
        $expiredVipProduct = $listings[1];
        $packages = app(ProductPromotionService::class)->packages();
        ProductPromotion::updateOrCreate(['seller_id' => $seller->id, 'idempotency_key' => 'demo-vip-active'], [
            'product_id' => $activeVipProduct->id, 'package_type' => 'vip', 'fee_amount' => $packages['vip']['fee'],
            'status' => 'active', 'reference' => 'DEMO-VIP-ACTIVE-'.$seller->id,
            'starts_at' => now()->subDay(), 'expires_at' => now()->addDays(max(1, $packages['vip']['days'] - 1)),
        ]);
        ProductPromotion::updateOrCreate(['seller_id' => $seller->id, 'idempotency_key' => 'demo-vip-expired'], [
            'product_id' => $expiredVipProduct->id, 'package_type' => 'vip', 'fee_amount' => $packages['vip']['fee'],
            'status' => 'expired', 'reference' => 'DEMO-VIP-EXPIRED-'.$seller->id,
            'starts_at' => now()->subDays(12), 'expires_at' => now()->subDays(5),
        ]);

        $searchProduct = $this->product($seller, $category, 'Nồi cơm điện mini 1.2L · mẫu demo', 320000);
        $cartProduct = $this->product($seller, $category, 'Bàn học gỗ gấp gọn · mẫu demo', 450000);
        CartItem::firstOrCreate(['user_id' => $buyer->id, 'product_id' => $cartProduct->id]);

        $settings = app(\App\Modules\Admin\Services\MarketplaceSettings::class);
        $shipping = max(0, $settings->get('shipping_fee'));
        $rate = min(100, max(0, $settings->get('commission_rate')));
        $depositPercent = min(30, max(20, $settings->get('cod_deposit_percent')));
        $ledger = app(WalletLedger::class);

        $codProduct = $this->product($seller, $category, 'Nồi cơm điện · đơn COD mẫu', 1000000, Product::STATUS_RESERVED);
        $this->seedOrder($seller, $buyer, $codProduct, 'DEMO-COD-DEPOSIT-'.$buyer->id, [
            'payment_method' => 'cod', 'payment_provider' => 'vnpay_mock', 'payment_status' => 'paid',
            'payment_amount' => (int) ceil($codProduct->price * $depositPercent / 100),
            'cod_deposit_percent' => $depositPercent,
            'cod_deposit_amount' => (int) ceil($codProduct->price * $depositPercent / 100),
            'cod_remaining_amount' => $codProduct->price - (int) ceil($codProduct->price * $depositPercent / 100),
            'status' => Order::STATUS_SHIPPING, 'delivery_status' => 'in_transit', 'payout_status' => 'awaiting_delivery',
            'ledger' => 'cod',
        ], $shipping, $rate, $ledger);

        $waitingProduct = $this->product($seller, $category, 'Màn hình máy tính · đơn chờ 48 giờ mẫu', 600000, Product::STATUS_SOLD);
        $this->seedOrder($seller, $buyer, $waitingProduct, 'DEMO-48H-'.$buyer->id, [
            'payment_method' => 'vnpay', 'payment_provider' => 'vnpay_mock', 'payment_status' => 'paid',
            'payment_amount' => 600000 + $shipping, 'status' => Order::STATUS_COMPLETED,
            'delivery_status' => 'delivered', 'payout_status' => 'awaiting_dispute_window',
            'delivered_at' => now()->subHours(24), 'release_eligible_at' => now()->addHours(24),
            'ledger' => 'full',
        ], $shipping, $rate, $ledger);

        $disputedProduct = $this->product($seller, $category, 'Điện thoại · đơn có khiếu nại mẫu', 850000, Product::STATUS_SOLD);
        $disputedOrder = $this->seedOrder($seller, $buyer, $disputedProduct, 'DEMO-DISPUTE-'.$buyer->id, [
            'payment_method' => 'vnpay', 'payment_provider' => 'vnpay_mock', 'payment_status' => 'paid',
            'payment_amount' => 850000 + $shipping, 'status' => Order::STATUS_COMPLETED,
            'delivery_status' => 'delivered', 'payout_status' => 'held',
            'delivered_at' => now()->subHours(6), 'release_eligible_at' => now()->addHours(42),
            'ledger' => 'full',
        ], $shipping, $rate, $ledger);
        $dispute = OrderDispute::firstOrCreate(['order_id' => $disputedOrder->id, 'status' => 'open'], [
            'opened_by' => $buyer->id, 'reason' => 'Hàng khác mô tả',
            'details' => 'Dữ liệu demo để trình diễn quy trình khiếu nại; không phải khiếu nại thật.',
            'evidence_notes' => ['Bằng chứng minh họa đã được tạo bởi seeder demo.'],
        ]);
        OrderDisputeEvent::firstOrCreate(['dispute_id' => $dispute->id, 'action' => 'opened'], [
            'actor_id' => $buyer->id, 'note' => 'Sự kiện minh họa được tạo bởi DemoCommerceSeeder.',
        ]);

        $ledger->post((int) $seller->id, 'DEMO-WALLET-FUNDING-'.$seller->id, 'demo_wallet_funding', [
            ['user_id' => (int) $seller->id, 'account_code' => 'available', 'amount' => 500000],
            ['user_id' => null, 'account_code' => 'provider_clearing', 'amount' => -500000],
        ], 'demo', (int) $seller->id, ['simulation' => true]);
        $ledger->post((int) $seller->id, 'DEMO-WITHDRAWAL-HOLD-'.$seller->id, 'demo_withdrawal_hold', [
            ['user_id' => (int) $seller->id, 'account_code' => 'available', 'amount' => -100000],
            ['user_id' => (int) $seller->id, 'account_code' => 'withdrawal_hold', 'amount' => 100000],
        ], 'demo', (int) $seller->id, ['simulation' => true]);
        WithdrawalRequest::firstOrCreate(['user_id' => $seller->id, 'idempotency_key' => 'demo-pending-withdrawal'], [
            'amount' => 100000, 'bank_details_ciphertext' => Crypt::encryptString('TÀI KHOẢN GIẢ LẬP - KHÔNG CHI TRẢ'),
            'status' => 'pending_review', 'reference' => 'DEMO-WITHDRAWAL-'.$seller->id,
        ]);

        $this->command?->info('Commerce demo seeded. Accounts: demo-seller@hsmart.local and demo-buyer@hsmart.local; use DEMO_USER_PASSWORD.');
    }

    private function product(User $seller, Category $category, string $title, int $price, string $status = Product::STATUS_ACTIVE): Product
    {
        $product = Product::firstOrCreate(['seller_id' => $seller->id, 'title' => $title], [
            'category_id' => $category->id, 'description' => 'Sản phẩm mẫu được tạo để trình diễn các luồng thương mại H-Smart.',
            'price' => $price, 'condition' => 'good', 'negotiable' => false,
            'province' => 'TP. Hồ Chí Minh', 'district' => 'Quận 10', 'status' => $status,
        ]);
        if ($product->status !== $status) $product->update(['status' => $status]);
        return $product;
    }

    private function seedOrder(User $seller, User $buyer, Product $product, string $reference, array $state, int $shipping, int $rate, WalletLedger $ledger): Order
    {
        $amount = (int) $product->price;
        $fee = intdiv(($amount + $shipping) * $rate, 100);
        $codDeposit = (int) ($state['cod_deposit_amount'] ?? 0);
        if ($state['ledger'] === 'cod' && $codDeposit < $fee) throw new ConflictHttpException('Demo COD deposit must cover the single commission deduction.');
        $order = Order::firstOrCreate(['payment_txn_ref' => $reference], [
            'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'purchase_type' => 'listed_price', 'idempotency_key' => $reference,
            'product_amount' => $amount, 'shipping_fee' => $shipping, 'fee_base' => $amount + $shipping,
            'platform_fee' => $fee, 'commission_rate' => $rate, 'total_amount' => $amount + $shipping,
            'status' => $state['status'], 'shipping_address_snapshot' => ['recipient_name' => $buyer->full_name, 'phone' => '0900000000', 'address' => 'Địa chỉ demo, Quận 10, TP. Hồ Chí Minh'],
            'expires_at' => now()->addDays(3), 'paid_at' => $state['payment_status'] === 'paid' ? now()->subDays(1) : null,
            'payment_provider' => $state['payment_provider'], 'payment_method' => $state['payment_method'],
            'payment_amount' => $state['payment_amount'], 'payment_status' => $state['payment_status'],
            'payment_paid_at' => $state['payment_status'] === 'paid' ? now()->subDays(1) : null,
            'cod_deposit_percent' => $state['cod_deposit_percent'] ?? null,
            'cod_deposit_amount' => $codDeposit, 'cod_remaining_amount' => $state['cod_remaining_amount'] ?? 0,
            'delivery_status' => $state['delivery_status'], 'payout_status' => $state['payout_status'],
            'delivered_at' => $state['delivered_at'] ?? null, 'release_eligible_at' => $state['release_eligible_at'] ?? null,
            'shipped_at' => $state['status'] === Order::STATUS_SHIPPING ? now()->subHours(4) : null,
            'completed_at' => $state['status'] === Order::STATUS_COMPLETED ? ($state['delivered_at'] ?? now()->subHours(24)) : null,
            'policy_version' => config('services.marketplace.policy_version', 'commerce-v1'), 'policy_accepted_at' => now()->subDays(2),
        ]);

        OrderStatusHistory::firstOrCreate(['order_id' => $order->id, 'to_status' => $order->status], [
            'from_status' => null, 'changed_by' => null, 'changed_at' => $order->created_at ?? now(),
        ]);

        if ($state['ledger'] === 'cod') $ledger->bookCodDeposit($order, 'vnpay_mock');
        elseif ($state['ledger'] === 'full') $ledger->bookOrderEscrow($order, 'vnpay_mock');
        return $order;
    }
}
