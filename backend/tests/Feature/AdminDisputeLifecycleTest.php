<?php

namespace Tests\Feature;

use App\Modules\Admin\Http\Controllers\AdminDisputeController;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Actions\ReleaseEligibleOrderFundsAction;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderDispute;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDisputeLifecycleTest extends TestCase
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

    public function test_admin_release_resolution_is_logged_notified_and_releases_once(): void
    {
        [$admin, $buyer, $seller, $order, $dispute] = $this->fixture();
        app(WalletLedger::class)->bookOrderEscrow($order, 'vnpay_mock');
        $request = Request::create('/api/v1/admin/disputes/'.$dispute->id.'/resolve', 'POST', [
            'resolution' => 'release_to_seller', 'note' => 'Đã xác minh giao hàng và bằng chứng.',
        ]);
        $request->setUserResolver(fn () => $admin);

        $response = app(AdminDisputeController::class)->resolve(
            $request, $dispute->id, app(WalletLedger::class), app(AdminAuditLogger::class), app(NotificationService::class),
        );

        $this->assertSame('resolved', $dispute->fresh()->status);
        $this->assertSame('eligible', $order->fresh()->payout_status);
        $this->assertSame(1, $dispute->events()->count());
        $this->assertSame(1, DB::table('admin_audit_logs')->where('subject_type', 'order_dispute')->where('subject_id', $dispute->id)->count());
        $this->assertSame(2, DB::table('notifications')->where('type', 'order_dispute_resolved')->count());
        $this->assertSame('success', $response->getData(true)['status']);

        $release = app(ReleaseEligibleOrderFundsAction::class);
        $this->assertSame(1, $release->execute(app(WalletLedger::class), app(NotificationService::class)));
        $this->assertSame(0, $release->execute(app(WalletLedger::class), app(NotificationService::class)));
        $this->assertSame(98_000, app(WalletLedger::class)->balance((int) $seller->id)['available']);
        $this->assertSame('credited_to_wallet', $order->fresh()->payout_status);
    }

    public function test_unpaid_dispute_cannot_be_resolved_to_release(): void
    {
        [$admin, , , $order, $dispute] = $this->fixture(paymentStatus: 'pending');
        $request = Request::create('/api/v1/admin/disputes/'.$dispute->id.'/resolve', 'POST', [
            'resolution' => 'release_to_seller', 'note' => 'Đã xem hồ sơ nhưng chưa thu đủ tiền.',
        ]);
        $request->setUserResolver(fn () => $admin);

        try {
            app(AdminDisputeController::class)->resolve(
                $request, $dispute->id, app(WalletLedger::class), app(AdminAuditLogger::class), app(NotificationService::class),
            );
            $this->fail('Expected an unpaid order to remain held.');
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $exception) {
            $this->assertStringContainsString('Chưa xác nhận đã thu đủ tiền', $exception->getMessage());
        }

        $this->assertSame('open', $dispute->fresh()->status);
        $this->assertNotSame('credited_to_wallet', $order->fresh()->payout_status);
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_admin_can_filter_open_and_resolved_dispute_history(): void
    {
        [, $buyer, , $order, $open] = $this->fixture();
        $resolved = OrderDispute::create([
            'order_id' => $order->id, 'opened_by' => $buyer->id, 'status' => 'resolved',
            'reason' => 'Đã xử lý', 'details' => 'Hồ sơ đã được giải quyết.',
            'resolution' => 'release_to_seller', 'resolution_note' => 'Đủ điều kiện.', 'resolved_at' => now(),
        ]);
        $controller = app(AdminDisputeController::class);
        $openResponse = $controller->index(Request::create('/api/v1/admin/disputes', 'GET'));
        $resolvedResponse = $controller->index(Request::create('/api/v1/admin/disputes?status=resolved', 'GET'));
        $allResponse = $controller->index(Request::create('/api/v1/admin/disputes?status=all', 'GET'));

        $this->assertSame([$open->id], array_column($openResponse->getData(true)['data']['content'], 'id'));
        $this->assertSame([$resolved->id], array_column($resolvedResponse->getData(true)['data']['content'], 'id'));
        $this->assertCount(2, $allResponse->getData(true)['data']['content']);
    }

    private function fixture(string $paymentStatus = 'paid'): array
    {
        $admin = User::create(['username' => 'admin', 'email' => 'admin@example.test', 'password_hash' => 'unused', 'role' => 'admin']);
        $buyer = User::create(['username' => 'buyer', 'email' => 'buyer@example.test', 'password_hash' => 'unused']);
        $seller = User::create(['username' => 'seller', 'email' => 'seller@example.test', 'password_hash' => 'unused']);
        $product = Product::create(['seller_id' => $seller->id, 'title' => 'Sản phẩm tranh chấp', 'price' => 100_000, 'condition' => 'good', 'status' => 'sold']);
        $order = Order::create([
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_id' => $product->id,
            'product_amount' => 100_000, 'fee_base' => 100_000, 'platform_fee' => 2_000, 'commission_rate' => 2,
            'total_amount' => 100_000, 'status' => 'shipping', 'payment_status' => $paymentStatus,
            'payment_method' => 'vnpay', 'payment_provider' => 'vnpay_mock', 'payment_amount' => 100_000,
            'payout_status' => 'held', 'shipping_address_snapshot' => ['recipient_name' => 'B', 'phone' => '0900000000', 'address' => 'Test'],
            'expires_at' => now()->addDay(),
        ]);
        $dispute = OrderDispute::create([
            'order_id' => $order->id, 'opened_by' => $buyer->id, 'status' => 'open',
            'reason' => 'Hàng sai mô tả', 'details' => 'Người mua đã gửi ảnh và mô tả đầy đủ.',
        ]);

        return [$admin, $buyer, $seller, $order, $dispute];
    }
}
