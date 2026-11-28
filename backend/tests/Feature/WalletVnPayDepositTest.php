<?php

namespace Tests\Feature;

use App\Modules\Order\Services\VnPayService;
use App\Modules\User\Models\User;
use App\Modules\Wallet\Http\Controllers\WalletVnPayController;
use App\Modules\Wallet\Models\WalletEntry;
use App\Modules\Wallet\Models\WalletVnPayDeposit;
use App\Modules\Wallet\Services\WalletLedger;
use App\Modules\Wallet\Services\WalletVnPayService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class WalletVnPayDepositTest extends TestCase
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
        $this->app->instance('env', 'testing');
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('v', 32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
            'cache.default' => 'array',
            'services.vnpay.mode' => 'gateway',
            'services.vnpay.tmn_code' => 'TESTTMN',
            'services.vnpay.hash_secret' => 'test-secret',
            'services.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    public function test_signed_callback_credits_once_and_wrong_signature_is_ignored(): void
    {
        $deposit = $this->deposit();
        $service = app(WalletVnPayService::class);
        $callback = $this->signedCallback($deposit->reference, (int) $deposit->amount);
        $badSignature = $callback;
        $badSignature['vnp_SecureHash'] = str_repeat('0', 128);

        $this->assertSame('invalid', $service->processCallback($badSignature));
        $this->assertSame('newly_paid', $service->processCallback($callback));
        $this->assertSame('success', $service->processCallback($callback));
        $this->assertSame('paid', $deposit->fresh()->status);
        $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'VNPAY_WALLET_'.$deposit->reference)->count());
        $this->assertSame(100_000, app(WalletLedger::class)->balance((int) $deposit->user_id)['available']);
        $this->assertSame(2, WalletEntry::query()->where('wallet_transaction_id', $deposit->fresh()->wallet_transaction_id)->count());
    }

    public function test_amount_mismatch_and_late_or_closed_callbacks_are_reconciled_without_credit(): void
    {
        $service = app(WalletVnPayService::class);
        $mismatch = $this->deposit('HWS-MISMATCH');
        $this->assertSame('amount_mismatch', $service->processCallback($this->signedCallback($mismatch->reference, 99_999)));
        $this->assertSame('reconciliation_required', $mismatch->fresh()->status);

        $late = $this->deposit('HWS-LATE', expiresAt: now()->subMinute());
        $this->assertSame('late', $service->processCallback($this->signedCallback($late->reference, (int) $late->amount)));
        $this->assertSame('reconciliation_required', $late->fresh()->status);

        $closed = $this->deposit('HWS-CLOSED', status: 'failed');
        $this->assertSame('late', $service->processCallback($this->signedCallback($closed->reference, (int) $closed->amount)));
        $this->assertSame('reconciliation_required', $closed->fresh()->status);
        $this->assertSame(0, WalletEntry::query()->count());
    }

    public function test_wallet_reference_is_not_accepted_as_an_order_callback(): void
    {
        $deposit = $this->deposit();
        $signedWalletCallback = $this->signedCallback($deposit->reference, (int) $deposit->amount);
        $this->assertSame('invalid', app(VnPayService::class)->processIpn($signedWalletCallback));
        $this->assertSame('pending', $deposit->fresh()->status);

        $signedOrderCallback = $this->signedCallback('HS123ORDERREFERENCE', (int) $deposit->amount);
        $this->assertSame('invalid', app(WalletVnPayService::class)->processCallback($signedOrderCallback));
    }

    public function test_gateway_url_uses_vnpay_url_encoded_signature_canonicalization(): void
    {
        config(['services.vnpay.wallet_return_url' => 'http://localhost:8001/api/v1/payments/vnpay/wallet/return']);
        $request = Request::create('http://localhost:8001/api/v1/me/wallet/vnpay-deposits', 'POST');
        $url = app(VnPayService::class)->createGatewayUrl(
            10_000, 'HWS-URL-SIGNATURE', 'Nap so du H-Smart HWS-URL-SIGNATURE', now()->addMinutes(15),
            $request, 'http://localhost:8001/api/v1/payments/vnpay/wallet/return',
        );
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $secureHash = $params['vnp_SecureHash'];
        unset($params['vnp_SecureHash']);
        ksort($params);
        $expected = hash_hmac('sha512', collect($params)->map(fn ($value, $key) => urlencode((string) $key).'='.urlencode((string) $value))->implode('&'), 'test-secret');
        $rawValueHash = hash_hmac('sha512', collect($params)->map(fn ($value, $key) => $key.'='.$value)->implode('&'), 'test-secret');

        $this->assertSame($expected, $secureHash);
        $this->assertNotSame($rawValueHash, $secureHash);
        $this->assertTrue(app(VnPayService::class)->verify([...$params, 'vnp_SecureHash' => $secureHash]));
    }

    public function test_production_gateway_hosts_are_rejected_for_checkout_and_callbacks(): void
    {
        config(['services.vnpay.payment_url' => 'https://pay.vnpay.vn/vpcpay.html']);
        $this->assertFalse(app(WalletVnPayService::class)->sandboxAvailable());
        try {
            app(VnPayService::class)->createGatewayUrl(
                10_000, 'HS-PRODUCTION-BLOCK', 'Sandbox scope check', now()->addMinutes(15),
                Request::create('/api/v1/orders/1/payment', 'POST'), 'http://localhost:8001/api/v1/payments/vnpay/return',
            );
            $this->fail('A production VNPay host must never receive checkout requests.');
        } catch (ServiceUnavailableHttpException) {
            $this->assertSame('invalid', app(VnPayService::class)->processIpn($this->signedCallback('HS-PRODUCTION-BLOCK', 10_000)));
        }

        $deposit = $this->deposit('HWS-PRODUCTION-BLOCK');
        $this->assertSame('invalid', app(WalletVnPayService::class)->processCallback($this->signedCallback($deposit->reference, (int) $deposit->amount)));
        $this->assertSame('pending', $deposit->fresh()->status);
    }

    public function test_shared_merchant_ipn_route_dispatches_wallet_reference_to_wallet_ledger(): void
    {
        $deposit = $this->deposit();
        $query = http_build_query($this->signedCallback($deposit->reference, (int) $deposit->amount));

        $this->get('/api/v1/payments/vnpay/ipn?'.$query)->assertOk()->assertJson(['RspCode' => '00']);
        $this->get('/api/v1/payments/vnpay/ipn?'.$query)->assertOk()->assertJson(['RspCode' => '00']);

        $this->assertSame(100_000, app(WalletLedger::class)->balance((int) $deposit->user_id)['available']);
        $this->assertSame(1, DB::table('wallet_transactions')->where('reference', 'VNPAY_WALLET_'.$deposit->reference)->count());
    }

    public function test_wallet_return_uses_frontend_route_when_result_url_is_blank(): void
    {
        config(['services.vnpay.wallet_result_url' => '', 'services.vnpay.sandbox_return_fallback' => false]);
        $request = Request::create('/api/v1/payments/vnpay/wallet/return?vnp_TxnRef=HWSRETURNTEST', 'GET');
        $response = app(WalletVnPayController::class)
            ->return($request, app(WalletVnPayService::class));

        $this->assertSame('http://localhost:5174/me/wallet?depositRef=HWSRETURNTEST', $response->getTargetUrl());
    }

    private function deposit(string $reference = 'HWS-TEST-REFERENCE', ?string $status = null, $expiresAt = null): WalletVnPayDeposit
    {
        static $sequence = 0;
        $sequence++;
        $user = User::create(['username' => 'wallet_user_'.$sequence, 'email' => 'wallet'.$sequence.'@example.test', 'password_hash' => 'unused']);

        return WalletVnPayDeposit::create([
            'user_id' => $user->id,
            'reference' => $reference,
            'idempotency_key' => 'wallet-topup-key-'.$sequence,
            'amount' => 100_000,
            'provider' => 'vnpay',
            'mode' => 'sandbox',
            'status' => $status ?? 'pending',
            'expires_at' => $expiresAt ?? now()->addMinutes(15),
        ]);
    }

    private function signedCallback(string $reference, int $amount): array
    {
        $query = [
            'vnp_Amount' => (string) ($amount * 100),
            'vnp_BankCode' => 'TESTBANK',
            'vnp_CurrCode' => 'VND',
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'TESTTMN',
            'vnp_TransactionNo' => 'TXN-'.$reference,
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => $reference,
        ];
        ksort($query);
        $query['vnp_SecureHash'] = hash_hmac('sha512', collect($query)->map(fn ($value, $key) => urlencode((string) $key).'='.urlencode((string) $value))->implode('&'), 'test-secret');

        return $query;
    }
}
