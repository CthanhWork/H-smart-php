<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Notification\Services\NotificationService;
use App\Modules\Wallet\Models\WalletVnPayDeposit;
use App\Modules\Wallet\Services\WalletVnPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WalletVnPayController
{
    public function create(Request $request, WalletVnPayService $deposits): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:10000', 'max:10000000'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:80', 'alpha_dash'],
        ]);
        $result = $deposits->create((int) $request->user()->id, (int) $data['amount'], $data['idempotency_key'], $request);
        $deposit = $result['deposit'];

        return response()->json(['status' => 'success', 'message' => 'Đã tạo yêu cầu nạp VNPay Sandbox; số dư chỉ tăng sau callback hợp lệ.', 'data' => [
            'reference' => $deposit->reference, 'amount' => (int) $deposit->amount, 'status' => $deposit->status,
            'mode' => 'sandbox', 'expires_at' => $deposit->expires_at?->toIso8601String(), 'payment_url' => $result['payment_url'],
        ]], 201);
    }

    public function show(Request $request, string $reference): JsonResponse
    {
        $deposit = WalletVnPayDeposit::where('reference', $reference)
            ->where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(['status' => 'success', 'message' => 'Trạng thái yêu cầu nạp VNPay Sandbox.', 'data' => $this->resource($deposit)]);
    }

    public function ipn(Request $request, WalletVnPayService $deposits, NotificationService $notifications): JsonResponse
    {
        $result = $deposits->processCallback($request->query());
        if ($result === 'newly_paid') {
            $deposit = WalletVnPayDeposit::with('user')->where('reference', $request->query('vnp_TxnRef'))->first();
            if ($deposit) $notifications->send($deposit->user, null, 'wallet_vnpay_deposit_paid', 'Nạp VNPay Sandbox thành công', 'Đã ghi có '.number_format((int) $deposit->amount, 0, ',', '.').'đ vào Ví của tôi từ giao dịch VNPay Sandbox.', '/me/wallet', ['reference' => $deposit->reference]);
        }

        $responseCode = match ($result) {
            'invalid' => '97',
            'amount_mismatch' => '04',
            default => '00',
        };

        return response()->json([
            'RspCode' => $responseCode,
            'Message' => $responseCode === '97' ? 'Invalid signature or transaction' : ($responseCode === '04' ? 'Invalid amount' : 'Confirm Success'),
        ]);
    }

    public function return(Request $request, WalletVnPayService $deposits): RedirectResponse
    {
        $query = $request->query();
        $isSandbox = $deposits->sandboxAvailable();
        if ($isSandbox && config('services.vnpay.sandbox_return_fallback')) {
            // The same signature, merchant, reference and amount checks run here as in IPN.
            $deposits->processCallback($query);
        }

        $reference = trim((string) ($query['vnp_TxnRef'] ?? ''));
        $frontendUrl = (string) config('services.vnpay.wallet_result_url')
            ?: rtrim((string) config('services.vnpay.frontend_url', 'http://localhost:5174'), '/').'/me/wallet';

        return redirect()->away($frontendUrl.(str_contains($frontendUrl, '?') ? '&' : '?').'depositRef='.rawurlencode($reference));
    }

    private function resource(WalletVnPayDeposit $deposit): array
    {
        return [
            'reference' => $deposit->reference,
            'amount' => (int) $deposit->amount,
            'provider' => 'vnpay',
            'mode' => 'sandbox',
            'status' => $deposit->status,
            'expires_at' => $deposit->expires_at?->toIso8601String(),
            'paid_at' => $deposit->paid_at?->toIso8601String(),
            'response_code' => $deposit->response_code,
            'resolution_note' => $deposit->resolution_note,
        ];
    }
}
