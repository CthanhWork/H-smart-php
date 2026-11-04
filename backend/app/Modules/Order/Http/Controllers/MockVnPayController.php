<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Models\Order;
use App\Modules\Order\Services\VnPayService;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MockVnPayController
{
    private function order(string $txnRef, VnPayService $vnpay): Order
    {
        abort_unless($vnpay->mockEnabled(), 404);

        return Order::where('payment_txn_ref', $txnRef)->where('payment_provider', 'vnpay_mock')->firstOrFail();
    }

    public function show(Request $request, string $txnRef, VnPayService $vnpay): View
    {
        $order = $this->order($txnRef, $vnpay);

        return view('payments.vnpay-mock', ['order' => $order, 'action' => $request->fullUrl()]);
    }

    public function complete(Request $request, string $txnRef, VnPayService $vnpay, NotificationService $notifications): RedirectResponse
    {
        $order = $this->order($txnRef, $vnpay);
        $data = $request->validate(['outcome' => ['required', 'in:success,failed,cancel']]);
        $processed = $vnpay->processMock($order, $data['outcome']);
        if ($processed === 'newly_paid') {
            $order->load(['buyer', 'seller']);
            $notifications->send($order->seller, $order->buyer, 'order_paid', 'Đơn hàng đã thanh toán mô phỏng', 'Đơn hàng #'.$order->id.' đã thanh toán mô phỏng, không chuyển tiền thật.', '/me/orders', ['order_id' => $order->id]);
        }
        $result = match ($processed) {
            'newly_paid', 'success' => 'mock_success',
            'failed' => 'mock_failed',
            default => 'invalid',
        };
        $url = config('services.vnpay.result_redirect_url') ?: 'http://localhost:5174/payment-result';

        return redirect()->away($url.(str_contains($url, '?') ? '&' : '?').'payment='.$result);
    }
}
