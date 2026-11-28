<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\Wallet\Http\Controllers\WalletController;
use App\Modules\Wallet\Models\WalletEntry;
use App\Modules\Wallet\Models\WithdrawalRequest;
use App\Modules\Wallet\Models\SellerBankAccount;
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
        SellerBankAccount::create(['user_id' => $user->id, 'details_ciphertext' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['bank_name' => 'Ngân hàng thử nghiệm', 'account_holder' => 'Người dùng thử nghiệm', 'account_number' => '1234567890']))]);

        $request = Request::create('/api/v1/wallet/withdraw', 'POST', [
            'amount' => 80_000, 'idempotency_key' => 'withdraw-request-key-0001', 'confirm_bank_details' => true,
        ]);
        $request->setUserResolver(fn () => $user);
        $response = app(WalletController::class)->withdraw($request, $ledger);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(20_000, $ledger->balance((int) $user->id)['available']);
        $this->assertSame(80_000, $ledger->balance((int) $user->id)['withdrawal_hold']);
        $withdrawal = WithdrawalRequest::where('user_id', $user->id)->firstOrFail();
        $this->assertNotSame('1234567890', $withdrawal->bank_details_ciphertext);

        $second = Request::create('/api/v1/wallet/withdraw', 'POST', [
            'amount' => 60_000, 'idempotency_key' => 'withdraw-request-key-0002', 'confirm_bank_details' => true,
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
        SellerBankAccount::create(['user_id' => $user->id, 'details_ciphertext' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['bank_name' => 'Ngân hàng thử nghiệm', 'account_holder' => 'Người dùng thử', 'account_number' => '1234567890']))]);
        $payload = [
            'amount' => 50_000, 'idempotency_key' => 'withdraw-payload-key-001', 'confirm_bank_details' => true,
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

    public function test_bank_account_is_encrypted_masked_and_scoped_to_the_owner(): void
    {
        $owner = User::create(['username' => 'bank-owner', 'email' => 'bank-owner@example.test', 'password_hash' => 'unused']);
        $other = User::create(['username' => 'bank-other', 'email' => 'bank-other@example.test', 'password_hash' => 'unused']);
        $request = Request::create('/api/v1/me/wallet/bank-account', 'PUT', [
            'bank_name' => 'Ngân hàng thử nghiệm', 'account_holder' => 'Người bán thử nghiệm', 'account_number' => '  1234-567  ',
        ]);
        $request->setUserResolver(fn () => $owner);
        $saved = app(WalletController::class)->saveBankAccount($request)->getData(true)['data'];

        $this->assertSame('•••4567', $saved['masked_account_number']);
        $stored = SellerBankAccount::where('user_id', $owner->id)->firstOrFail();
        $this->assertStringNotContainsString('1234567', $stored->details_ciphertext);
        $otherRequest = Request::create('/api/v1/me/wallet/bank-account', 'GET');
        $otherRequest->setUserResolver(fn () => $other);
        $this->assertNull(app(WalletController::class)->bankAccount($otherRequest)->getData(true)['data']);
        $ownerRequest = Request::create('/api/v1/me/wallet/bank-account', 'GET');
        $ownerRequest->setUserResolver(fn () => $owner);
        $this->assertSame($saved, app(WalletController::class)->bankAccount($ownerRequest)->getData(true)['data']);
    }

    public function test_withdrawal_snapshot_is_immutable_and_cancel_returns_hold_only_once(): void
    {
        $user = User::create(['username' => 'snapshot-seller', 'email' => 'snapshot-seller@example.test', 'password_hash' => 'unused']);
        $ledger = app(WalletLedger::class);
        $ledger->post($user->id, 'snapshot-test-topup-001', 'test_credit', [
            ['user_id' => $user->id, 'account_code' => 'available', 'amount' => 120_000],
            ['user_id' => null, 'account_code' => 'test_clearing', 'amount' => -120_000],
        ]);
        SellerBankAccount::create(['user_id' => $user->id, 'details_ciphertext' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['bank_name' => 'Ngân hàng cũ', 'account_holder' => 'Chủ tài khoản cũ', 'account_number' => '12345678901']))]);
        $withdrawRequest = Request::create('/api/v1/me/wallet/withdrawals', 'POST', [
            'amount' => 80_000, 'idempotency_key' => 'snapshot-withdraw-key-001', 'confirm_bank_details' => true,
        ]);
        $withdrawRequest->setUserResolver(fn () => $user);
        $created = app(WalletController::class)->withdraw($withdrawRequest, $ledger)->getData(true)['data'];
        $account = SellerBankAccount::where('user_id', $user->id)->firstOrFail();
        $account->update(['details_ciphertext' => \Illuminate\Support\Facades\Crypt::encryptString(json_encode(['bank_name' => 'Ngân hàng mới', 'account_holder' => 'Chủ tài khoản mới', 'account_number' => '999999999999999']))]);
        $withdrawal = WithdrawalRequest::findOrFail($created['id']);
        $snapshot = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($withdrawal->bank_details_ciphertext), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Ngân hàng cũ', $snapshot['bank_name']);
        $this->assertSame('12345678901', $snapshot['account_number']);

        $cancelRequest = Request::create('/api/v1/me/wallet/withdrawals/'.$withdrawal->id.'/cancel', 'POST');
        $cancelRequest->setUserResolver(fn () => $user);
        $this->assertSame(200, app(WalletController::class)->cancelWithdrawal($cancelRequest, $withdrawal->id, $ledger)->getStatusCode());
        $this->assertSame('cancelled', $withdrawal->fresh()->status);
        $this->assertSame(120_000, $ledger->balance($user->id)['available']);
        $this->assertSame(0, $ledger->balance($user->id)['withdrawal_hold']);
        $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'WITHDRAW_CANCEL_'.$withdrawal->id)->count());
        try {
            app(WalletController::class)->cancelWithdrawal($cancelRequest, $withdrawal->id, $ledger);
            $this->fail('A repeated cancellation must not release the held amount twice.');
        } catch (ConflictHttpException) {
            $this->assertSame(120_000, $ledger->balance($user->id)['available']);
        }
    }
}
