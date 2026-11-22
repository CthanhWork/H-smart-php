<?php

namespace Tests\Feature;

use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Actions\AdvanceOrderAction;
use App\Modules\Order\Actions\ReleaseEligibleOrderFundsAction;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderDispute;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OrderReleaseSafetyTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_open_dispute_holds_funds_and_scheduler_releases_only_once_after_resolution(): void
    {
        $buyer = User::create(['username' => 'release-buyer', 'email' => 'release-buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'release-seller', 'email' => 'release-seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Release test item', 'price' => 100_000, 'condition' => 'good', 'status' => Product::STATUS_SOLD]);
        $order = Order::create([
            'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'product_amount' => 100_000, 'shipping_fee' => 0, 'fee_base' => 100_000,
            'platform_fee' => 2_000, 'commission_rate' => 2, 'total_amount' => 100_000,
            'status' => Order::STATUS_COMPLETED, 'shipping_address_snapshot' => [], 'expires_at' => now()->subDay(),
            'payment_provider' => 'vnpay_mock', 'payment_method' => 'vnpay', 'payment_status' => 'paid',
            'payment_amount' => 100_000, 'paid_at' => now()->subDays(3), 'payment_paid_at' => now()->subDays(3),
            'delivered_at' => now()->subDay(), 'release_eligible_at' => now()->subMinute(), 'payout_status' => 'awaiting_dispute_window',
        ]);
        $ledger = app(WalletLedger::class);
        $ledger->bookOrderEscrow($order, 'vnpay_mock');
        $dispute = OrderDispute::create([
            'order_id' => $order->id, 'opened_by' => $buyer->id, 'status' => 'open',
            'reason' => 'Hàng không đúng mô tả', 'details' => 'Kiểm tra giữ tiền trong thời gian tranh chấp.',
        ]);
        $action = app(ReleaseEligibleOrderFundsAction::class);
        $notifications = app(NotificationService::class);

        $this->assertSame(0, $action->execute($ledger, $notifications));
        $this->assertSame(0, $ledger->balance((int) $seller->id)['available']);
        $this->assertSame(98_000, $ledger->balance((int) $seller->id)['pending_release']);

        $dispute->update(['status' => 'resolved']);
        $this->assertSame(1, $action->execute($ledger, $notifications));
        $this->assertSame(0, $action->execute($ledger, $notifications));
        $this->assertSame(98_000, $ledger->balance((int) $seller->id)['available']);
        $this->assertSame(0, $ledger->balance((int) $seller->id)['pending_release']);
        $this->assertSame(1, WalletTransaction::where('reference', 'ORDER_RELEASE_'.$order->id)->count());
        $this->assertNotNull($order->fresh()->wallet_credited_at);
        $this->assertNotNull($order->fresh()->seller_paid_at);
    }

    public function test_buyer_confirmation_does_not_shorten_the_delivery_dispute_window(): void
    {
        $buyer = User::create(['username' => 'window-buyer', 'email' => 'window-buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'window-seller', 'email' => 'window-seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Window test item', 'price' => 100_000, 'condition' => 'good', 'status' => Product::STATUS_SOLD]);
        $deliveredAt = now()->subHours(24);
        $releaseAt = $deliveredAt->copy()->addHours(48);
        $order = Order::create([
            'product_id' => $product->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'product_amount' => 100_000, 'shipping_fee' => 0, 'fee_base' => 100_000,
            'platform_fee' => 2_000, 'commission_rate' => 2, 'total_amount' => 100_000,
            'status' => Order::STATUS_SHIPPING, 'shipping_address_snapshot' => [], 'expires_at' => now()->subDay(),
            'payment_provider' => 'vnpay_mock', 'payment_method' => 'vnpay', 'payment_status' => 'paid',
            'payment_amount' => 100_000, 'paid_at' => now()->subDays(3), 'payment_paid_at' => now()->subDays(3),
            'delivered_at' => $deliveredAt, 'release_eligible_at' => $releaseAt, 'payout_status' => 'awaiting_dispute_window',
        ]);
        $ledger = app(WalletLedger::class);
        $ledger->bookOrderEscrow($order, 'vnpay_mock');

        $confirmed = app(AdvanceOrderAction::class)->execute((int) $order->id, (int) $buyer->id, 'complete');
        $this->assertSame($releaseAt->toDateTimeString(), $confirmed->release_eligible_at->toDateTimeString());

        $action = app(ReleaseEligibleOrderFundsAction::class);
        $notifications = app(NotificationService::class);
        Carbon::setTestNow($releaseAt->copy()->subSecond());
        $this->assertSame(0, $action->execute($ledger, $notifications));
        $this->assertSame(0, $ledger->balance((int) $seller->id)['available']);

        Carbon::setTestNow($releaseAt);
        $this->assertSame(1, $action->execute($ledger, $notifications));
        $this->assertSame(0, $action->execute($ledger, $notifications));
        $this->assertSame(98_000, $ledger->balance((int) $seller->id)['available']);
        $this->assertSame(1, WalletTransaction::where('reference', 'ORDER_RELEASE_'.$order->id)->count());
        Carbon::setTestNow();
    }
}
