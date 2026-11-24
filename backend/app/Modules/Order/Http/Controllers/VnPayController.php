<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\VnPayService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Wallet\Models\WalletVnPayDeposit;
use App\Modules\Wallet\Services\WalletVnPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VnPayController
{
    public function create(Request $request, int $id, VnPayService $vnpay): JsonResponse
    {
        $order = Order::where('buyer_id', $request->user()->id)->findOrFail($id);
        abort_unless($order->payment_method === 'cod' || $order->payment_provider !== 'cod', 409, 'Đơn hàng đã chọn thanh toán COD.');
        $url = $vnpay->createPaymentUrl($order, $request);

        return response()->json([
            'status' => 'success',
            'message' => 'Đang chuyển bạn đến cổng thanh toán VNPay.',
            'data' => ['payment_url' => $url],
        ]);
    }

    public function ipn(Request $request, VnPayService $vnpay, WalletVnPayService $walletDeposits, NotificationService $notifications): JsonResponse
    {
        $txnRef = (string) $request->query('vnp_TxnRef', '');
        if (str_starts_with($txnRef, 'HWS')) {
            $result = $walletDeposits->processCallback($request->query());
            if ($result === 'newly_paid') {
                $deposit = WalletVnPayDeposit::with('user')->where('reference', $txnRef)->first();
                if ($deposit) {
                    $notifications->send($deposit->user, null, 'wallet_vnpay_deposit_paid', 'Nạp VNPay Sandbox thành công', 'Đã ghi có '.number_format((int) $deposit->amount, 0, ',', '.').'đ vào số dư mô phỏng Sandbox.', '/me/wallet', ['reference' => $deposit->reference]);
                }
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

        $result = $vnpay->processIpn($request->query());
        if ($result === 'newly_paid') {
            $order = Order::with(['buyer', 'seller'])->where('payment_txn_ref', $request->query('vnp_TxnRef'))->first();
            if ($order) {
                $notifications->send($order->seller, $order->buyer, 'order_paid', 'Đơn hàng đã thanh toán', 'Đơn hàng #'.$order->id.' đã được thanh toán qua VNPay.', '/me/orders', ['order_id' => $order->id]);
            }
        }

        return response()->json([
            'RspCode' => in_array($result, ['success', 'newly_paid', 'failed'], true) ? '00' : '97',
            'Message' => match ($result) {
                'success', 'newly_paid' => 'Confirm Success',
                'failed' => 'Payment recorded',
                default => 'Invalid signature or transaction',
            },
        ]);
    }

    public function return(Request $request, VnPayService $vnpay, NotificationService $notifications): RedirectResponse
    {
        $query = $request->query();
        $valid = $vnpay->verify($query)
            && ($request->query('vnp_TmnCode') === config('services.vnpay.tmn_code'));
        $isSandbox = parse_url((string) config('services.vnpay.payment_url'), PHP_URL_HOST) === 'sandbox.vnpayment.vn';
        // Local demo browsers can reach localhost; VNPay's server cannot. Keep this fallback sandbox-only.
        $processed = $valid && config('services.vnpay.sandbox_return_fallback') && $isSandbox
            ? $vnpay->processIpn($query)
            : null;

        if ($processed === 'newly_paid') {
            $order = Order::with(['buyer', 'seller'])->where('payment_txn_ref', $request->query('vnp_TxnRef'))->first();
            if ($order) {
                $notifications->send($order->seller, $order->buyer, 'order_paid', 'Đơn hàng đã thanh toán', 'Đơn hàng #'.$order->id.' đã được thanh toán qua VNPay Sandbox.', '/me/orders', ['order_id' => $order->id]);
            }
        }

        $gatewaySucceeded = $request->query('vnp_ResponseCode') === '00'
            && $request->query('vnp_TransactionStatus') === '00';
        $result = ! $valid
            ? 'invalid'
            : (! $gatewaySucceeded
                ? ($processed === 'invalid' ? 'invalid' : 'failed')
                : (config('services.vnpay.sandbox_return_fallback') && $isSandbox
                    ? (in_array($processed, ['newly_paid', 'success'], true) ? 'success' : 'invalid')
                    : 'processing'));
        $frontendUrl = (string) config('services.vnpay.result_redirect_url');

        $separator = str_contains($frontendUrl, '?') ? '&' : '?';

        return redirect()->away($frontendUrl.$separator.'payment='.$result);
    }
}
