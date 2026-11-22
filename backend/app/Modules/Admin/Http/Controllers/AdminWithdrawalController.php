<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Wallet\Models\WithdrawalRequest;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AdminWithdrawalController
{
    public function index(): JsonResponse
    {
        $requests = WithdrawalRequest::with('user:id,username,email')->latest('id')->paginate(30);
        return response()->json(['status' => 'success', 'message' => 'Yêu cầu rút tiền mô phỏng.', 'data' => [
            'content' => $requests->getCollection()->map(fn (WithdrawalRequest $item) => $this->resource($item))->values(),
            'page' => $requests->currentPage(), 'total' => $requests->total(),
        ]]);
    }

    public function show(Request $request, int $id, AdminAuditLogger $audit): JsonResponse
    {
        $item = WithdrawalRequest::with('user:id,username,email')->findOrFail($id);
        try { $bank = json_decode(Crypt::decryptString($item->bank_details_ciphertext), true, flags: JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new ConflictHttpException('Không giải mã được thông tin nhận tiền; yêu cầu cần đối soát.'); }
        $audit->record($request->user(), 'withdrawal_bank_details_viewed', 'withdrawal_request', $item->id, 'Quản trị viên xem thông tin nhận tiền đã mã hóa.', ['reference' => $item->reference]);
        return response()->json(['status' => 'success', 'message' => 'Thông tin yêu cầu rút tiền.', 'data' => [
            ...$this->resource($item), 'bank_details' => $bank,
        ]]);
    }

    public function markProcessing(Request $request, int $id, AdminAuditLogger $audit): JsonResponse
    {
        $item = DB::transaction(function () use ($request, $id, $audit): WithdrawalRequest {
            $item = WithdrawalRequest::lockForUpdate()->findOrFail($id);
            if ($item->status !== 'pending_review') throw new ConflictHttpException('Chỉ yêu cầu đang chờ duyệt mới chuyển sang xử lý được.');
            $item->update(['status' => 'processing', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $audit->record($request->user(), 'withdrawal_processing', 'withdrawal_request', $item->id, 'Bắt đầu xử lý yêu cầu rút mô phỏng.', ['reference' => $item->reference]);
            return $item;
        });
        return response()->json(['status' => 'success', 'message' => 'Yêu cầu đang xử lý; số tiền vẫn được giữ.', 'data' => $this->resource($item)]);
    }

    public function markReconciliation(Request $request, int $id, AdminAuditLogger $audit): JsonResponse
    {
        $item = DB::transaction(function () use ($request, $id, $audit): WithdrawalRequest {
            $item = WithdrawalRequest::lockForUpdate()->findOrFail($id);
            if ($item->status !== 'processing') throw new ConflictHttpException('Chỉ yêu cầu đang xử lý mới chuyển sang chờ đối soát được.');
            $item->update(['status' => 'reconciliation_pending']);
            $audit->record($request->user(), 'withdrawal_reconciliation_pending', 'withdrawal_request', $item->id, 'Đặt yêu cầu rút vào trạng thái chờ đối soát.', ['reference' => $item->reference]);
            return $item;
        });
        return response()->json(['status' => 'success', 'message' => 'Giữ nguyên tiền trong lúc chờ đối soát.', 'data' => $this->resource($item)]);
    }

    public function reject(Request $request, int $id, WalletLedger $ledger, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $item = DB::transaction(function () use ($request, $id, $ledger, $audit, $data): WithdrawalRequest {
            $item = WithdrawalRequest::lockForUpdate()->findOrFail($id);
            if ($item->status !== 'pending_review') throw new ConflictHttpException('Chỉ có thể từ chối trước khi bắt đầu chuyển tiền.');
            $ledger->post($item->user_id, 'WITHDRAW_REJECT_'.$item->id, 'withdrawal_rejected', [
                ['user_id' => $item->user_id, 'account_code' => 'withdrawal_hold', 'amount' => -$item->amount],
                ['user_id' => $item->user_id, 'account_code' => 'available', 'amount' => $item->amount],
            ], 'withdrawal_request', $item->id, ['reason' => $data['reason'], 'amount' => $item->amount]);
            $item->update(['status' => 'rejected', 'decision_reason' => $data['reason'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $audit->record($request->user(), 'withdrawal_rejected', 'withdrawal_request', $item->id, 'Từ chối yêu cầu rút và hoàn số dư đang giữ.', ['reference' => $item->reference, 'reason' => $data['reason']]);
            return $item;
        }, 3);
        return response()->json(['status' => 'success', 'message' => 'Đã từ chối và trả số dư về khả dụng.', 'data' => $this->resource($item)]);
    }

    public function simulatePaid(Request $request, int $id, WalletLedger $ledger, AdminAuditLogger $audit): JsonResponse
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true) || ! config('services.wallet.simulation_enabled')) {
            throw new ConflictHttpException('Không thể ghi nhận chi trả mô phỏng ngoài môi trường local/testing đã bật.');
        }
        $item = DB::transaction(function () use ($request, $id, $ledger, $audit): WithdrawalRequest {
            $item = WithdrawalRequest::lockForUpdate()->findOrFail($id);
            if (! in_array($item->status, ['processing', 'reconciliation_pending'], true)) throw new ConflictHttpException('Yêu cầu phải đang xử lý hoặc chờ đối soát.');
            $ledger->post($item->user_id, 'WITHDRAW_SIMPAID_'.$item->id, 'simulated_withdrawal_paid', [
                ['user_id' => $item->user_id, 'account_code' => 'withdrawal_hold', 'amount' => -$item->amount],
                ['user_id' => null, 'account_code' => 'system_simulation_clearing', 'amount' => $item->amount],
            ], 'withdrawal_request', $item->id, ['simulation' => true]);
            $item->update(['status' => 'succeeded_simulated', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'paid_at' => now()]);
            $audit->record($request->user(), 'withdrawal_simulated_paid', 'withdrawal_request', $item->id, 'Ghi nhận kết quả chi trả mô phỏng; không chuyển tiền thật.', ['reference' => $item->reference]);
            return $item;
        }, 3);
        return response()->json(['status' => 'success', 'message' => 'Đã hoàn tất mô phỏng. Không có lệnh chuyển tiền thật.', 'data' => $this->resource($item)]);
    }

    private function resource(WithdrawalRequest $item): array
    {
        return [
            'id' => $item->id, 'user_id' => $item->user_id,
            'username' => $item->user?->username, 'email' => $item->user?->email,
            'amount' => $item->amount, 'status' => $item->status, 'reference' => $item->reference,
            'decision_reason' => $item->decision_reason,
            'created_at' => $item->created_at?->toIso8601String(), 'reviewed_at' => $item->reviewed_at?->toIso8601String(),
            'paid_at' => $item->paid_at?->toIso8601String(),
        ];
    }
}
