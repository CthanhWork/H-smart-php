<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\PaymentReconciliation;
use App\Modules\Offer\Models\Offer;
use App\Modules\Product\Models\Product;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminPaymentReconciliationController extends Controller
{
    public function index(): JsonResponse
    {
        $items = PaymentReconciliation::latest()->paginate(50);
        return response()->json(['status' => 'success', 'data' => $items]);
    }

    public function store(Request $request, WalletLedger $ledger, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:vietqr'],
            'payment_reference' => ['required', 'string', 'min:8', 'max:100'],
            'bank_reference' => ['required', 'string', 'min:3', 'max:100'],
            'amount' => ['required', 'integer', 'min:1'],
            'evidence_note' => ['required', 'string', 'min:8', 'max:1000'],
            'verified_against_bank_statement' => ['required', 'accepted'],
        ]);

        try {
            $result = DB::transaction(function () use ($request, $data, $ledger, $audit): PaymentReconciliation {
            if (PaymentReconciliation::where('provider', $data['provider'])->where('bank_reference', $data['bank_reference'])->exists()) {
                throw new ConflictHttpException('Mã giao dịch ngân hàng này đã được ghi nhận.');
            }
            $order = Order::where('payment_txn_ref', $data['payment_reference'])->lockForUpdate()->first();
            $status = 'unmatched';
            if ($order) {
                if ($order->payment_provider !== 'vietqr' || $order->payment_status !== 'pending' || $order->status !== Order::STATUS_PENDING_PAYMENT) {
                    $status = 'not_payable';
                } elseif ($order->expires_at->isPast()) {
                    $status = 'late';
                } elseif ((int) $order->payment_amount !== (int) $data['amount']) {
                    $status = 'amount_mismatch';
                } else {
                    $status = 'matched';
                    $now = now();
                    $order->update([
                        'status' => Order::STATUS_PAID, 'payment_status' => 'paid', 'payment_paid_at' => $now,
                        'paid_at' => $now, 'payment_transaction_no' => $data['bank_reference'],
                        'payout_status' => 'awaiting_delivery',
                    ]);
                    if ($order->payment_method === 'cod') {
                        $ledger->bookCodDeposit($order, 'vietqr');
                    } else {
                        $ledger->bookOrderEscrow($order, 'vietqr');
                        Product::whereKey($order->product_id)->lockForUpdate()->update(['status' => Product::STATUS_SOLD]);
                    }
                    if ($order->offer_id) Offer::where('product_id', $order->product_id)->where('status', Offer::STATUS_PENDING)->update(['status' => Offer::STATUS_REJECTED]);
                }
            }

            $record = PaymentReconciliation::create([
                'provider' => $data['provider'], 'payment_reference' => $data['payment_reference'],
                'bank_reference' => $data['bank_reference'], 'amount' => $data['amount'], 'status' => $status,
                'order_id' => $order?->id, 'reconciled_by' => $request->user()->id,
                'evidence_note' => trim($data['evidence_note']), 'reconciled_at' => now(),
            ]);
            $audit->record($request->user(), 'payment_reconciled_'.$status, 'payment_reconciliation', $record->id,
                'Quản trị viên đối soát khoản chuyển VietQR dựa trên sao kê ngân hàng.', ['order_id' => $order?->id, 'amount' => $data['amount'], 'bank_reference' => $data['bank_reference']]);
            return $record;
            }, 3);
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') throw new ConflictHttpException('Mã giao dịch ngân hàng này đã được ghi nhận.');
            throw $exception;
        }

        return response()->json(['status' => 'success', 'message' => $result->status === 'matched' ? 'Đã khớp tiền vào; đơn được xác nhận thanh toán.' : 'Đã lưu kết quả đối soát; đơn vẫn chưa được xác nhận thanh toán.', 'data' => $result], $result->status === 'matched' ? 200 : 202);
    }
}
