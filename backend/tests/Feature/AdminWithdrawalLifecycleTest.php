<?php

namespace Tests\Feature;

use App\Modules\Admin\Http\Controllers\AdminWithdrawalController;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Http\Controllers\WalletController;
use App\Modules\Wallet\Models\SellerBankAccount;
use App\Modules\Wallet\Models\WithdrawalRequest;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminWithdrawalLifecycleTest extends TestCase
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'app.key' => 'base64:'.base64_encode(str_repeat('p', 32))]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_uncertain_outcome_keeps_hold_and_manual_success_settles_once(): void
    {
        [$admin, $withdrawal, $ledger] = $this->fixture();
        $controller = app(AdminWithdrawalController::class);
        $processing = Request::create('/api/v1/admin/withdrawals/'.$withdrawal->id.'/processing', 'POST');
        $processing->setUserResolver(fn () => $admin);
        $controller->markProcessing($processing, $withdrawal->id, app(AdminAuditLogger::class));

        $uncertain = Request::create('/api/v1/admin/withdrawals/'.$withdrawal->id.'/outcome', 'POST', [
            'outcome' => 'uncertain', 'note' => 'Chưa xác định được kết quả thử nghiệm.',
        ]);
        $uncertain->setUserResolver(fn () => $admin);
        $controller->confirmOutcome($uncertain, $withdrawal->id, $ledger, app(AdminAuditLogger::class));
        $this->assertSame('reconciliation_pending', $withdrawal->fresh()->status);
        $this->assertSame(80_000, $ledger->balance((int) $withdrawal->user_id)['withdrawal_hold']);
        $this->assertSame(40_000, $ledger->balance((int) $withdrawal->user_id)['available']);

        $missingReference = Request::create('/api/v1/admin/withdrawals/'.$withdrawal->id.'/outcome', 'POST', [
            'outcome' => 'succeeded', 'note' => 'Đã đối chiếu kết quả thử nghiệm.',
        ]);
        $missingReference->setUserResolver(fn () => $admin);
        try {
            $controller->confirmOutcome($missingReference, $withdrawal->id, $ledger, app(AdminAuditLogger::class));
            $this->fail('Success must require a test reference or demo record.');
        } catch (ValidationException) {
            $this->assertSame(80_000, $ledger->balance((int) $withdrawal->user_id)['withdrawal_hold']);
        }

        $success = Request::create('/api/v1/admin/withdrawals/'.$withdrawal->id.'/outcome', 'POST', [
            'outcome' => 'succeeded', 'note' => 'Đã đối chiếu biên bản thử nghiệm.', 'sandbox_reference' => 'DEMO-REC-001',
        ]);
        $success->setUserResolver(fn () => $admin);
        $controller->confirmOutcome($success, $withdrawal->id, $ledger, app(AdminAuditLogger::class));
        $this->assertSame('succeeded', $withdrawal->fresh()->status);
        $this->assertSame(0, $ledger->balance((int) $withdrawal->user_id)['withdrawal_hold']);
        $this->assertSame(40_000, $ledger->balance((int) $withdrawal->user_id)['available']);
        $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'WITHDRAW_PAID_'.$withdrawal->id)->count());

        try {
            $controller->confirmOutcome($success, $withdrawal->id, $ledger, app(AdminAuditLogger::class));
            $this->fail('A repeated success must not post the wallet ledger twice.');
        } catch (ConflictHttpException) {
            $this->assertSame(40_000, $ledger->balance((int) $withdrawal->user_id)['available']);
        }
    }

    public function test_admin_rejection_returns_held_money_once(): void
    {
        [$admin, $withdrawal, $ledger] = $this->fixture();
        $controller = app(AdminWithdrawalController::class);
        $request = Request::create('/api/v1/admin/withdrawals/'.$withdrawal->id.'/reject', 'POST', ['reason' => 'Thông tin thử nghiệm chưa hợp lệ.']);
        $request->setUserResolver(fn () => $admin);
        $controller->reject($request, $withdrawal->id, $ledger, app(AdminAuditLogger::class));

        $this->assertSame('rejected', $withdrawal->fresh()->status);
        $this->assertSame(120_000, $ledger->balance((int) $withdrawal->user_id)['available']);
        $this->assertSame(0, $ledger->balance((int) $withdrawal->user_id)['withdrawal_hold']);
        $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'WITHDRAW_REJECT_'.$withdrawal->id)->count());

        try {
            $controller->reject($request, $withdrawal->id, $ledger, app(AdminAuditLogger::class));
            $this->fail('A repeated rejection must not return the held amount twice.');
        } catch (ConflictHttpException) {
            $this->assertSame(120_000, $ledger->balance((int) $withdrawal->user_id)['available']);
            $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'WITHDRAW_REJECT_'.$withdrawal->id)->count());
        }
    }

    public function test_confirmed_failure_returns_held_money_once(): void
    {
        [$admin, $withdrawal, $ledger] = $this->fixture();
        $controller = app(AdminWithdrawalController::class);
        $processing = Request::create('/api/v1/admin/withdrawals/'.$withdrawal->id.'/processing', 'POST');
        $processing->setUserResolver(fn () => $admin);
        $controller->markProcessing($processing, $withdrawal->id, app(AdminAuditLogger::class));
        $failed = Request::create('/api/v1/admin/withdrawals/'.$withdrawal->id.'/outcome', 'POST', [
            'outcome' => 'failed', 'note' => 'Kết quả thử nghiệm xác nhận thất bại.',
        ]);
        $failed->setUserResolver(fn () => $admin);
        $controller->confirmOutcome($failed, $withdrawal->id, $ledger, app(AdminAuditLogger::class));

        $this->assertSame('failed', $withdrawal->fresh()->status);
        $this->assertSame(120_000, $ledger->balance((int) $withdrawal->user_id)['available']);
        $this->assertSame(0, $ledger->balance((int) $withdrawal->user_id)['withdrawal_hold']);
        $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'WITHDRAW_FAIL_'.$withdrawal->id)->count());

        try {
            $controller->confirmOutcome($failed, $withdrawal->id, $ledger, app(AdminAuditLogger::class));
            $this->fail('A repeated failure result must not return the held amount twice.');
        } catch (ConflictHttpException) {
            $this->assertSame(120_000, $ledger->balance((int) $withdrawal->user_id)['available']);
            $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'WITHDRAW_FAIL_'.$withdrawal->id)->count());
        }
    }

    private function fixture(): array
    {
        $admin = User::create(['username' => 'payout-admin', 'email' => 'payout-admin@example.test', 'password_hash' => 'unused', 'role' => 'admin']);
        $seller = User::create(['username' => 'payout-seller', 'email' => 'payout-seller@example.test', 'password_hash' => 'unused']);
        $ledger = app(WalletLedger::class);
        $ledger->post($seller->id, 'payout-test-topup-001', 'test_credit', [
            ['user_id' => $seller->id, 'account_code' => 'available', 'amount' => 120_000],
            ['user_id' => null, 'account_code' => 'test_clearing', 'amount' => -120_000],
        ]);
        SellerBankAccount::create(['user_id' => $seller->id, 'details_ciphertext' => Crypt::encryptString(json_encode([
            'bank_name' => 'Ngân hàng thử nghiệm', 'account_holder' => 'Người bán thử nghiệm', 'account_number' => '9876543210123',
        ], JSON_THROW_ON_ERROR))]);
        $request = Request::create('/api/v1/me/wallet/withdrawals', 'POST', [
            'amount' => 80_000, 'idempotency_key' => 'admin-payout-test-key-001', 'confirm_bank_details' => true,
        ]);
        $request->setUserResolver(fn () => $seller);
        $created = app(WalletController::class)->withdraw($request, $ledger)->getData(true)['data'];

        return [$admin, WithdrawalRequest::findOrFail($created['id']), $ledger];
    }
}
