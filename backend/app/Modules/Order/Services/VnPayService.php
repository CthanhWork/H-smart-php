<?php

namespace App\Modules\Order\Services;

use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class VnPayService
{
    public function createPaymentUrl(Order $order, Request $request): string
    {
        $tmnCode = config('services.vnpay.tmn_code');
        $hashSecret = config('services.vnpay.hash_secret');
        if (! $tmnCode || ! $hashSecret) {
            throw new ServiceUnavailableHttpException(null, 'Thanh toán VNPay chưa được cấu hình. Vui lòng liên hệ quản trị viên.');
        }

        $order = DB::transaction(function () use ($order, $request): Order {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            abort_unless($locked->buyer_id === $request->user()->id, 404);
            if ($locked->status !== Order::STATUS_PENDING_PAYMENT || ! in_array($locked->payment_status, ['pending', 'failed'], true)) {
                throw new ConflictHttpException('Đơn hàng không còn chờ thanh toán.');
            }
            if ($locked->expires_at->isPast()) {
                throw new ConflictHttpException('Đơn hàng đã hết thời gian giữ chỗ.');
            }

            if ($locked->payment_txn_ref === null || $locked->payment_status === 'failed') {
                $locked->payment_txn_ref = 'HS'.$locked->id.Str::upper(Str::random(20));
                $locked->payment_transaction_no = null;
            }
            $locked->payment_provider = 'vnpay';
            $locked->payment_amount = $locked->total_amount;
            $locked->payment_status = 'pending';
            $locked->payment_response_code = null;
            $locked->save();

            return $locked->refresh();
        });

        $createdAt = now()->timezone('Asia/Ho_Chi_Minh');
        $expiresAt = $order->expires_at->timezone('Asia/Ho_Chi_Minh');
        $returnUrl = config('services.vnpay.return_url') ?: rtrim(config('app.url'), '/').'/api/v1/payments/vnpay/return';
        $ipAddress = $request->ip() ?: '127.0.0.1';
        if (str_contains($ipAddress, ',')) {
            $ipAddress = trim(explode(',', $ipAddress)[0]);
        }

        $params = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $tmnCode,
            'vnp_Amount' => (string) ($order->total_amount * 100),
            'vnp_CurrCode' => 'VND',
            'vnp_TxnRef' => $order->payment_txn_ref,
            'vnp_OrderInfo' => 'Thanh toan don hang '.$order->id,
            'vnp_OrderType' => 'other',
            'vnp_Locale' => 'vn',
            'vnp_ReturnUrl' => $returnUrl,
            'vnp_IpAddr' => $ipAddress,
            'vnp_CreateDate' => $createdAt->format('YmdHis'),
            'vnp_ExpireDate' => $expiresAt->format('YmdHis'),
        ];
        ksort($params);
        $signData = http_build_query($params, '', '&', PHP_QUERY_RFC1738);
        $params['vnp_SecureHash'] = hash_hmac('sha512', $signData, $hashSecret);

        return rtrim(config('services.vnpay.payment_url'), '?').'?' . http_build_query($params, '', '&', PHP_QUERY_RFC1738);
    }

    public function verify(array $query): bool
    {
        $hashSecret = config('services.vnpay.hash_secret');
        $secureHash = (string) ($query['vnp_SecureHash'] ?? '');
        if (! $hashSecret || $secureHash === '') {
            return false;
        }

        $params = array_filter($query, fn ($key) => str_starts_with((string) $key, 'vnp_') && ! in_array($key, ['vnp_SecureHash', 'vnp_SecureHashType'], true), ARRAY_FILTER_USE_KEY);
        ksort($params);
        $expectedHash = hash_hmac('sha512', http_build_query($params, '', '&', PHP_QUERY_RFC1738), $hashSecret);

        return hash_equals($expectedHash, $secureHash);
    }

    /** @return 'success'|'newly_paid'|'failed'|'invalid' */
    public function processIpn(array $query): string
    {
        if (! $this->verify($query)) {
            return 'invalid';
        }

        if (($query['vnp_TmnCode'] ?? null) !== config('services.vnpay.tmn_code')) {
            return 'invalid';
        }

        return DB::transaction(function () use ($query): string {
            $order = Order::where('payment_txn_ref', (string) ($query['vnp_TxnRef'] ?? ''))->lockForUpdate()->first();
            if (! $order) {
                return 'invalid';
            }

            $amount = filter_var($query['vnp_Amount'] ?? null, FILTER_VALIDATE_INT);
            if ($amount === false || $amount !== $order->total_amount * 100 || $order->payment_amount !== $order->total_amount) {
                return 'invalid';
            }

            $successful = ($query['vnp_ResponseCode'] ?? null) === '00'
                && ($query['vnp_TransactionStatus'] ?? null) === '00';

            if ($order->payment_status === 'paid') {
                return $successful ? 'success' : 'failed';
            }

            if (! $successful) {
                if ($order->status === Order::STATUS_PENDING_PAYMENT && $order->payment_status === 'pending') {
                    $order->update([
                        'payment_status' => 'failed',
                        'payment_response_code' => (string) ($query['vnp_ResponseCode'] ?? '99'),
                        'payment_bank_code' => $query['vnp_BankCode'] ?? null,
                    ]);
                }

                return 'failed';
            }

            if ($order->status !== Order::STATUS_PENDING_PAYMENT || $order->expires_at->isPast() || $order->payment_status !== 'pending') {
                return 'invalid';
            }

            $product = Product::lockForUpdate()->find($order->product_id);
            if (! $product || $product->status !== Product::STATUS_RESERVED) {
                return 'invalid';
            }

            $paidAt = now();
            $order->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => $paidAt,
                'payment_status' => 'paid',
                'payment_paid_at' => $paidAt,
                'payment_transaction_no' => ($query['vnp_TransactionNo'] ?? '') !== '' ? (string) $query['vnp_TransactionNo'] : null,
                'payment_response_code' => (string) ($query['vnp_ResponseCode'] ?? '00'),
                'payment_bank_code' => $query['vnp_BankCode'] ?? null,
            ]);
            $product->update(['status' => Product::STATUS_SOLD]);
            if ($order->offer_id) {
                Offer::where('product_id', $product->id)
                    ->where('status', Offer::STATUS_PENDING)
                    ->update(['status' => Offer::STATUS_REJECTED]);
            }

            return 'newly_paid';
        });
    }
}
