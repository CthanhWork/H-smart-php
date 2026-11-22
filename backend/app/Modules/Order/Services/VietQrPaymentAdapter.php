<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\Order;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class VietQrPaymentAdapter
{
    /**
     * Creates a payment instruction only. VietQR image generation is not proof of funds;
     * settlement must come from a verified bank/provider feed before an order is marked paid.
     */
    public function instruction(Order $order): array
    {
        $bankId = trim((string) config('services.vietqr.bank_id'));
        $accountNumber = trim((string) config('services.vietqr.account_number'));
        $accountName = trim((string) config('services.vietqr.account_name'));
        if ($bankId === '' || $accountNumber === '' || $accountName === '') {
            throw new ServiceUnavailableHttpException(null, 'VietQR chưa có thông tin tài khoản nhận tiền.');
        }
        if ($order->payment_txn_ref !== null && $order->payment_status === 'pending' && $order->payment_provider !== 'vietqr') {
            throw new ConflictHttpException('Đơn đã có yêu cầu thanh toán đang chờ xác minh qua nhà cung cấp khác. Hãy chờ kết quả hoặc để yêu cầu hết hạn trước khi thử lại.');
        }

        $reference = $order->payment_txn_ref ?: 'HS'.(string) $order->id.Str::upper(Str::random(16));
        $order->forceFill(['payment_provider' => 'vietqr', 'payment_txn_ref' => $reference, 'payment_amount' => $order->payment_method === 'cod' ? $order->cod_deposit_amount : $order->total_amount, 'payment_status' => 'pending'])->save();
        $query = http_build_query(['amount' => $order->payment_amount, 'addInfo' => $reference, 'accountName' => $accountName]);

        return [
            'provider' => 'vietqr', 'reference' => $reference, 'amount' => (int) $order->payment_amount,
            'bank_id' => $bankId, 'account_number' => $accountNumber, 'account_name' => $accountName,
            'qr_url' => 'https://img.vietqr.io/image/'.rawurlencode($bankId).'-'.rawurlencode($accountNumber).'-compact2.png?'.$query,
            'confirmation' => 'pending_verified_bank_statement',
        ];
    }
}
