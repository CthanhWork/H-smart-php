<?php

namespace Tests\Feature;

use App\Modules\Admin\Http\Controllers\AdminDisputeController;
use App\Modules\Admin\Http\Controllers\AdminOrderController;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Order\Actions\ReleaseEligibleOrderFundsAction;
use App\Modules\Order\Http\Controllers\OrderDisputeController;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderDispute;
use App\Modules\Order\Models\OrderDisputeEvent;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
        $this->assertSame(0, $release->execute(app(WalletLedger::class), app(NotificationService::class)));
        $this->assertSame(0, $release->execute(app(WalletLedger::class), app(NotificationService::class)));
        $this->assertSame(0, app(WalletLedger::class)->balance((int) $seller->id)['available']);
        $this->assertSame(98_000, app(WalletLedger::class)->balance((int) $seller->id)['pending_release']);
        $this->assertSame('eligible', $order->fresh()->payout_status);
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
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('Chưa xác nhận đã thu đủ tiền', $exception->getMessage());
        }

        $this->assertSame('open', $dispute->fresh()->status);
        $this->assertNotSame('credited_to_wallet', $order->fresh()->payout_status);
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_buyer_cannot_open_a_second_dispute_while_first_waits_for_reconciliation(): void
    {
        [, $buyer, , $order, $dispute] = $this->fixture();
        $dispute->update(['status' => 'reconciliation_pending']);
        $request = Request::create('/api/v1/orders/'.$order->id.'/disputes', 'POST', [
            'reason' => 'Vẫn còn vấn đề', 'details' => 'Người mua bổ sung yêu cầu trong lúc hồ sơ đang chờ đối soát.',
        ]);
        $request->setUserResolver(fn () => $buyer);

        try {
            app(OrderDisputeController::class)->store($request, (int) $order->id, app(NotificationService::class));
            $this->fail('A second dispute must not be created while the first is unresolved.');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('đang mở hoặc chờ đối soát', $exception->getMessage());
        }

        $this->assertSame(1, OrderDispute::where('order_id', $order->id)->count());
        $this->assertSame('reconciliation_pending', $dispute->fresh()->status);
        $this->assertSame('held', $order->fresh()->payout_status);
    }

    public function test_admin_wallet_release_is_manual_and_idempotent(): void
    {
        [$admin, , $seller, $order] = $this->fixture();
        $order->update(['release_eligible_at' => now()->subSecond(), 'payout_status' => 'eligible']);
        $order->disputes()->update(['status' => 'resolved']);
        $ledger = app(WalletLedger::class);
        $ledger->bookOrderEscrow($order, 'vnpay_mock');
        $request = Request::create('/api/v1/admin/orders/'.$order->id.'/release-to-wallet', 'POST', ['note' => 'Đã rà soát thời hạn và đối soát.']);
        $request->setUserResolver(fn () => $admin);
        $response = app(AdminOrderController::class)->release($request, (int) $order->id, $ledger, app(AdminAuditLogger::class));
        $this->assertSame('credited_to_wallet', $order->fresh()->payout_status);
        $this->assertSame(98_000, $ledger->balance((int) $seller->id)['available']);
        $this->assertSame(1, DB::table('admin_audit_logs')->where('action', 'order_funds_released_to_wallet')->count());
        $retry = Request::create('/api/v1/admin/orders/'.$order->id.'/release-to-wallet', 'POST', ['note' => 'Gửi lại request lần hai.']);
        $retry->setUserResolver(fn () => $admin);
        try {
            app(AdminOrderController::class)->release($retry, (int) $order->id, $ledger, app(AdminAuditLogger::class));
            $this->fail('Repeated admin release succeeded.');
        } catch (ConflictHttpException) {
            $this->assertSame(98_000, $ledger->balance((int) $seller->id)['available']);
        }
    }

    public function test_direct_cod_without_platform_held_deposit_cannot_refund_from_other_orders(): void
    {
        [$admin, $buyer, $seller, $order, $dispute] = $this->fixture();
        $otherOrder = Order::create([
            'buyer_id' => $buyer->id, 'seller_id' => $seller->id, 'product_id' => $order->product_id,
            'product_amount' => 100_000, 'fee_base' => 100_000, 'platform_fee' => 2_000, 'commission_rate' => 2,
            'total_amount' => 100_000, 'status' => 'shipping', 'payment_status' => 'paid',
            'payment_method' => 'vnpay', 'payment_provider' => 'vnpay_mock', 'payment_amount' => 100_000,
            'payout_status' => 'held', 'shipping_address_snapshot' => ['recipient_name' => 'B', 'phone' => '0900000000', 'address' => 'Test'],
            'expires_at' => now()->addDay(),
        ]);
        app(WalletLedger::class)->bookOrderEscrow($otherOrder, 'vnpay_mock');
        $order->update([
            'payment_method' => 'cod', 'payment_provider' => 'cod', 'payment_amount' => 0,
            'cod_deposit_amount' => 0, 'cod_remaining_amount' => 100_000,
            'cod_remaining_collection_type' => 'direct', 'cod_remaining_collected_at' => now(),
        ]);
        $dispute->update(['buyer_wallet_refund_consent_at' => now()]);
        $sellerBalanceBefore = app(WalletLedger::class)->balance((int) $seller->id);
        $buyerBalanceBefore = app(WalletLedger::class)->balance((int) $buyer->id);
        $request = Request::create('/api/v1/admin/disputes/'.$dispute->id.'/resolve', 'POST', [
            'resolution' => 'refund_to_buyer_wallet', 'note' => 'COD được thu trực tiếp ngoài sàn.',
        ]);
        $request->setUserResolver(fn () => $admin);

        try {
            app(AdminDisputeController::class)->resolve(
                $request, $dispute->id, app(WalletLedger::class), app(AdminAuditLogger::class), app(NotificationService::class),
            );
            $this->fail('Direct COD must not be refunded from funds held for another order.');
        } catch (ConflictHttpException $exception) {
            $this->assertStringContainsString('không có khoản tiền do sàn giữ', $exception->getMessage());
        }

        $this->assertSame('open', $dispute->fresh()->status);
        $this->assertSame('held', $order->fresh()->payout_status);
        $this->assertSame($sellerBalanceBefore, app(WalletLedger::class)->balance((int) $seller->id));
        $this->assertSame($buyerBalanceBefore, app(WalletLedger::class)->balance((int) $buyer->id));
        $this->assertSame(0, DB::table('wallet_transactions')->where('reference', 'ORDER_REFUND_WALLET_'.$order->id)->count());
    }

    public function test_admin_can_filter_open_and_resolved_dispute_history(): void
    {
        [, $buyer, , $order, $open] = $this->fixture();
        OrderDisputeEvent::create([
            'dispute_id' => $open->id, 'actor_id' => $buyer->id,
            'action' => 'opened', 'note' => 'Mô tả khiếu nại đã được ghi nhận.',
        ]);
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
        $this->assertSame('opened', $openResponse->getData(true)['data']['content'][0]['events'][0]['action']);
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
