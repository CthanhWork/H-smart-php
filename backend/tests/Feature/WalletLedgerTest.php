<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\Wallet\Http\Controllers\WalletController;
use App\Modules\Wallet\Models\WalletEntry;
use App\Modules\Wallet\Models\WithdrawalRequest;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class WalletLedgerTest extends TestCase
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
        config([
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'app.key' => 'base64:'.base64_encode(str_repeat('w', 32)),
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_ledger_posts_balanced_entries_idempotently_and_blocks_overspending(): void
    {
        $user = User::create(['username' => 'wallet-user', 'email' => 'wallet@example.test', 'password_hash' => 'unused']);
        $ledger = app(WalletLedger::class);
        $lines = [
            ['user_id' => $user->id, 'account_code' => 'available', 'amount' => 100000],
            ['user_id' => null, 'account_code' => 'system_simulation_clearing', 'amount' => -100000],
        ];
        $first = $ledger->post($user->id, 'test-topup-0001', 'simulated_deposit', $lines, metadata: ['amount' => 100000]);
        $retry = $ledger->post($user->id, 'test-topup-0001', 'simulated_deposit', $lines, metadata: ['amount' => 100000]);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(100000, $ledger->balance($user->id)['available']);
        $this->assertSame(0, (int) WalletEntry::where('wallet_transaction_id', $first->id)->sum('amount'));

        $ledger->post($user->id, 'test-withdraw-hold-0001', 'withdrawal_hold', [
            ['user_id' => $user->id, 'account_code' => 'available', 'amount' => -70000],
            ['user_id' => $user->id, 'account_code' => 'withdrawal_hold', 'amount' => 70000],
        ]);
        $this->assertSame(30000, $ledger->balance($user->id)['available']);
        $this->assertSame(70000, $ledger->balance($user->id)['withdrawal_hold']);

        try {
            $ledger->post($user->id, 'test-overspend-0001', 'test_spend', [
                ['user_id' => $user->id, 'account_code' => 'available', 'amount' => -30001],
                ['user_id' => null, 'account_code' => 'system_simulation_clearing', 'amount' => 30001],
            ]);
            $this->fail('The ledger allowed a negative available balance.');
        } catch (ConflictHttpException) {
            $this->assertSame(30000, $ledger->balance($user->id)['available']);
        }
    }

    public function test_reusing_wallet_reference_with_changed_amount_is_rejected(): void
    {
        $user = User::create(['username' => 'wallet-user', 'email' => 'wallet@example.test', 'password_hash' => 'unused']);
        $ledger = app(WalletLedger::class);
        $ledger->post($user->id, 'wallet-payload-key-0001', 'simulated_deposit', [
            ['user_id' => $user->id, 'account_code' => 'available', 'amount' => 100_000],
            ['user_id' => null, 'account_code' => 'system_simulation_clearing', 'amount' => -100_000],
        ], 'simulated_deposit');

        try {
            $ledger->post($user->id, 'wallet-payload-key-0001', 'simulated_deposit', [
                ['user_id' => $user->id, 'account_code' => 'available', 'amount' => 200_000],
                ['user_id' => null, 'account_code' => 'system_simulation_clearing', 'amount' => -200_000],
            ], 'simulated_deposit');
            $this->fail('Changed amount reused an existing wallet reference');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(100_000, $ledger->balance($user->id)['available']);
    }

    public function test_withdrawal_moves_money_to_hold_and_rejects_a_second_request_that_would_overdraw(): void
    {
        $user = User::create(['username' => 'withdraw-user', 'email' => 'withdraw@example.test', 'password_hash' => 'unused']);
        $ledger = app(WalletLedger::class);
        $ledger->post($user->id, 'withdraw-test-topup-0001', 'simulated_deposit', [
            ['user_id' => $user->id, 'account_code' => 'available', 'amount' => 100_000],
            ['user_id' => null, 'account_code' => 'system_simulation_clearing', 'amount' => -100_000],
        ]);
        config(['services.wallet.simulation_enabled' => true]);

        $request = Request::create('/api/v1/wallet/withdraw', 'POST', [
            'amount' => 80_000, 'idempotency_key' => 'withdraw-request-key-0001',
            'bank_name' => 'Ngân hàng thử nghiệm', 'account_holder' => 'Người dùng thử nghiệm', 'account_number' => '1234567890',
        ]);
        $request->setUserResolver(fn () => $user);
        $response = app(WalletController::class)->withdraw($request, $ledger);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(20_000, $ledger->balance((int) $user->id)['available']);
        $this->assertSame(80_000, $ledger->balance((int) $user->id)['withdrawal_hold']);
        $withdrawal = WithdrawalRequest::where('user_id', $user->id)->firstOrFail();
        $this->assertNotSame('1234567890', $withdrawal->bank_details_ciphertext);

        $second = Request::create('/api/v1/wallet/withdraw', 'POST', [
            'amount' => 60_000, 'idempotency_key' => 'withdraw-request-key-0002',
            'bank_name' => 'Ngân hàng thử nghiệm', 'account_holder' => 'Người dùng thử nghiệm', 'account_number' => '9876543210',
        ]);
        $second->setUserResolver(fn () => $user);
        try {
            app(WalletController::class)->withdraw($second, $ledger);
            $this->fail('A second withdrawal larger than the available balance was accepted.');
        } catch (ConflictHttpException) {
            $this->assertSame(1, WithdrawalRequest::where('user_id', $user->id)->count());
            $this->assertSame(20_000, $ledger->balance((int) $user->id)['available']);
            $this->assertSame(80_000, $ledger->balance((int) $user->id)['withdrawal_hold']);
        }
    }

    public function test_withdrawal_idempotency_key_cannot_be_reused_with_changed_amount(): void
    {
        $user = User::create(['username' => 'withdraw-user', 'email' => 'withdraw@example.test', 'password_hash' => 'unused']);
        $ledger = app(WalletLedger::class);
        $ledger->post($user->id, 'withdraw-payload-topup-001', 'simulated_deposit', [
            ['user_id' => $user->id, 'account_code' => 'available', 'amount' => 120_000],
            ['user_id' => null, 'account_code' => 'system_simulation_clearing', 'amount' => -120_000],
        ]);
        config(['services.wallet.simulation_enabled' => true]);
        $payload = [
            'amount' => 50_000, 'idempotency_key' => 'withdraw-payload-key-001',
            'bank_name' => 'Ngân hàng thử nghiệm', 'account_holder' => 'Người dùng thử', 'account_number' => '1234567890',
        ];
        $request = Request::create('/api/v1/me/wallet/withdrawals', 'POST', $payload);
        $request->setUserResolver(fn () => $user);
        $first = app(WalletController::class)->withdraw($request, $ledger);
        $payload['amount'] = 80_000;
        $retry = Request::create('/api/v1/me/wallet/withdrawals', 'POST', $payload);
        $retry->setUserResolver(fn () => $user);

        try {
            app(WalletController::class)->withdraw($retry, $ledger);
            $this->fail('Changed withdrawal amount reused an existing key');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $this->assertSame(201, $first->getStatusCode());
        $this->assertSame(70_000, $ledger->balance($user->id)['available']);
        $this->assertSame(50_000, $ledger->balance($user->id)['withdrawal_hold']);
        $this->assertSame(1, WithdrawalRequest::where('idempotency_key', 'withdraw-payload-key-001')->count());
    }
}
