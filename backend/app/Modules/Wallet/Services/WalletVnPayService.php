<?php

namespace App\Modules\Wallet\Services;

use App\Modules\Order\Services\VnPayService;
use App\Modules\Wallet\Models\WalletVnPayDeposit;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class WalletVnPayService
{
    public function __construct(private VnPayService $vnpay, private WalletLedger $ledger) {}

    public function sandboxAvailable(): bool
    {
        return config('services.vnpay.mode') === 'gateway'
            && parse_url((string) config('services.vnpay.payment_url'), PHP_URL_HOST) === 'sandbox.vnpayment.vn'
            && filled(config('services.vnpay.tmn_code'))
            && filled(config('services.vnpay.hash_secret'));
    }

    public function create(int $userId, int $amount, string $idempotencyKey, Request $request): array
    {
        if (! $this->sandboxAvailable()) {
            throw new ServiceUnavailableHttpException(null, 'Nạp ví VNPay Sandbox chưa được cấu hình. Ví mô phỏng vẫn dùng riêng cho QA local.');
        }

        try {
            $deposit = DB::transaction(function () use ($userId, $amount, $idempotencyKey): WalletVnPayDeposit {
                $existing = WalletVnPayDeposit::where('user_id', $userId)->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing) {
                    if ((int) $existing->amount !== $amount) {
                        throw new ConflictHttpException('Mã yêu cầu nạp đã được dùng với số tiền khác. Hãy tạo yêu cầu mới.');
                    }

                    return $existing;
                }

                return WalletVnPayDeposit::create([
                    'user_id' => $userId,
                    'reference' => 'HWS'.Str::upper(Str::random(24)),
                    'idempotency_key' => $idempotencyKey,
                    'amount' => $amount,
                    'provider' => 'vnpay',
                    'mode' => 'sandbox',
                    'status' => 'pending',
                    'expires_at' => now()->addMinutes(15),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            $deposit = WalletVnPayDeposit::where('user_id', $userId)->where('idempotency_key', $idempotencyKey)->first();
            if (! $deposit) throw $exception;
            if ((int) $deposit->amount !== $amount) {
                throw new ConflictHttpException('Mã yêu cầu nạp đã được dùng với số tiền khác. Hãy tạo yêu cầu mới.');
            }
        }

        if ($deposit->status !== 'pending' || $deposit->expires_at->isPast()) {
            throw new ConflictHttpException('Yêu cầu nạp này không còn chờ thanh toán. Hãy tạo yêu cầu mới.');
        }

        $returnUrl = (string) config('services.vnpay.wallet_return_url')
            ?: rtrim(config('app.url'), '/').'/api/v1/payments/vnpay/wallet/return';
        $paymentUrl = $this->vnpay->createGatewayUrl(
            (int) $deposit->amount,
            $deposit->reference,
            'Nap so du H-Smart '.$deposit->reference,
            $deposit->expires_at,
            $request,
            $returnUrl,
        );

        return ['deposit' => $deposit, 'payment_url' => $paymentUrl];
    }

    /** @return 'newly_paid'|'success'|'failed'|'cancelled'|'late'|'amount_mismatch'|'invalid' */
    public function processCallback(array $query): string
    {
        if (! str_starts_with((string) ($query['vnp_TxnRef'] ?? ''), 'HWS')) {
            return 'invalid';
        }

        if (! $this->vnpay->verify($query) || ($query['vnp_TmnCode'] ?? null) !== config('services.vnpay.tmn_code')) {
            return 'invalid';
        }

        return DB::transaction(function () use ($query): string {
            $deposit = WalletVnPayDeposit::where('reference', (string) ($query['vnp_TxnRef'] ?? ''))->lockForUpdate()->first();
            if (! $deposit || $deposit->provider !== 'vnpay' || $deposit->mode !== 'sandbox') {
                return 'invalid';
            }

            if (($query['vnp_CurrCode'] ?? null) !== 'VND') {
                return 'invalid';
            }

            if ($deposit->status === 'paid') {
                return 'success';
            }

            $amount = filter_var($query['vnp_Amount'] ?? null, FILTER_VALIDATE_INT);
            if ($amount === false || $amount !== (int) $deposit->amount * 100) {
                $deposit->update([
                    'status' => 'reconciliation_required',
                    'callback_amount' => $amount === false ? null : (int) $amount,
                    'transaction_no' => $this->nullableString($query['vnp_TransactionNo'] ?? null, 64),
                    'bank_code' => $this->nullableString($query['vnp_BankCode'] ?? null, 32),
                    'response_code' => $this->nullableString($query['vnp_ResponseCode'] ?? null, 8),
                    'callback_received_at' => now(),
                    'resolution_note' => 'VNPay đã gửi callback có chữ ký hợp lệ nhưng số tiền không khớp yêu cầu nạp.',
                ]);

                return 'amount_mismatch';
            }

            $successful = ($query['vnp_ResponseCode'] ?? null) === '00'
                && ($query['vnp_TransactionStatus'] ?? null) === '00';
            $callback = [
                'callback_amount' => (int) $amount,
                'transaction_no' => $this->nullableString($query['vnp_TransactionNo'] ?? null, 64),
                'bank_code' => $this->nullableString($query['vnp_BankCode'] ?? null, 32),
                'response_code' => $this->nullableString($query['vnp_ResponseCode'] ?? null, 8),
                'callback_received_at' => now(),
            ];

            if (! $successful) {
                if ($deposit->status === 'pending') {
                    $deposit->update([...$callback, 'status' => ($query['vnp_ResponseCode'] ?? null) === '24' ? 'cancelled' : 'failed']);
                }

                return ($query['vnp_ResponseCode'] ?? null) === '24' ? 'cancelled' : 'failed';
            }

            if (in_array($deposit->status, ['expired', 'failed', 'cancelled'], true) || $deposit->expires_at->isPast()) {
                $deposit->update([...$callback, 'status' => 'reconciliation_required', 'resolution_note' => 'Thanh toán VNPay có chữ ký hợp lệ đến sau khi yêu cầu nạp hết hạn hoặc đã được đánh dấu kết thúc; chưa ghi có số dư.']);

                return 'late';
            }

            if ($deposit->status !== 'pending') {
                return 'invalid';
            }

            $transaction = $this->ledger->post((int) $deposit->user_id, 'VNPAY_WALLET_'.$deposit->reference, 'vnpay_sandbox_deposit', [
                ['user_id' => (int) $deposit->user_id, 'account_code' => 'available', 'amount' => (int) $deposit->amount],
                ['user_id' => null, 'account_code' => 'provider_clearing', 'amount' => -(int) $deposit->amount],
            ], 'wallet_deposit', (int) $deposit->id, [
                'provider' => 'vnpay', 'mode' => 'sandbox', 'amount' => (int) $deposit->amount,
                'transaction_no' => $callback['transaction_no'],
            ]);

            $deposit->update([...$callback, 'status' => 'paid', 'paid_at' => now(), 'wallet_transaction_id' => $transaction->id]);

            return 'newly_paid';
        }, 3);
    }

    public function expirePending(): int
    {
        return WalletVnPayDeposit::where('status', 'pending')->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);
    }

    private function nullableString(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) return null;
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
