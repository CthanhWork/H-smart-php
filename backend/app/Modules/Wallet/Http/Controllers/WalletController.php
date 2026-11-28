<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Models\WithdrawalRequest;
use App\Modules\Wallet\Models\SellerBankAccount;
use App\Modules\Wallet\Services\WalletLedger;
use App\Modules\Wallet\Services\WalletVnPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class WalletController
{
    public function show(Request $request, WalletLedger $ledger, WalletVnPayService $vnpayDeposits): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $ledger->ensureAccount($userId);
        $transactions = WalletTransaction::query()->whereHas('entries', fn ($query) => $query->where('user_id', $userId))
            ->with(['entries' => fn ($query) => $query->where('user_id', $userId)])
            ->latest('id')->limit(50)->get()->map(fn (WalletTransaction $transaction) => [
                'reference' => $transaction->reference,
                'type' => $transaction->type,
                'status' => $transaction->status,
                'amount' => (int) ($transaction->metadata['amount'] ?? $transaction->entries->sum('amount')),
                'mode' => $transaction->metadata['mode'] ?? null,
                'created_at' => $transaction->posted_at?->toIso8601String(),
            ]);
        $withdrawals = WithdrawalRequest::query()->where('user_id', $userId)->latest('id')->limit(20)->get()->map(fn (WithdrawalRequest $item) => [
            'id' => $item->id, 'amount' => $item->amount, 'status' => $item->status,
            'reference' => $item->reference, 'created_at' => $item->created_at?->toIso8601String(),
            'decision_reason' => $item->decision_reason,
        ]);
        $deposits = \App\Modules\Wallet\Models\WalletVnPayDeposit::query()->where('user_id', $userId)->latest('id')->limit(20)->get()->map(fn ($item) => [
            'reference' => $item->reference, 'amount' => (int) $item->amount, 'provider' => 'vnpay', 'mode' => 'sandbox',
            'status' => $item->status, 'expires_at' => $item->expires_at?->toIso8601String(), 'paid_at' => $item->paid_at?->toIso8601String(),
            'resolution_note' => $item->resolution_note,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Số dư ví H-Smart.', 'data' => [
            ...$ledger->balance($userId), 'transactions' => $transactions, 'withdrawals' => $withdrawals, 'deposits' => $deposits,
            'vnpay_sandbox_available' => $vnpayDeposits->sandboxAvailable(),
        ]]);
    }

    public function bankAccount(Request $request): JsonResponse
    {
        $account = SellerBankAccount::where('user_id', $request->user()->id)->first();
        $details = $account ? json_decode(Crypt::decryptString($account->details_ciphertext), true, flags: JSON_THROW_ON_ERROR) : null;
        return response()->json(['status' => 'success', 'message' => 'Tài khoản ngân hàng.', 'data' => $details ? [
            'bank_name' => $details['bank_name'], 'account_holder' => $details['account_holder'],
            'masked_account_number' => str_repeat('•', max(0, strlen($details['account_number']) - 4)).substr($details['account_number'], -4),
        ] : null]);
    }

    public function saveBankAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', 'string', 'min:2', 'max:100'],
            'account_holder' => ['required', 'string', 'min:2', 'max:150'],
            'account_number' => ['required', 'string', 'min:5', 'max:40', 'regex:/^[0-9A-Za-z -]+$/'],
        ]);
        $details = ['bank_name' => trim($data['bank_name']), 'account_holder' => trim($data['account_holder']), 'account_number' => preg_replace('/[ -]+/', '', $data['account_number'])];
        $account = SellerBankAccount::where('user_id', $request->user()->id)->first();
        $before = $account ? json_decode(Crypt::decryptString($account->details_ciphertext), true, flags: JSON_THROW_ON_ERROR) : null;
        SellerBankAccount::updateOrCreate(['user_id' => $request->user()->id], ['details_ciphertext' => Crypt::encryptString(json_encode($details, JSON_THROW_ON_ERROR))]);
        \App\Modules\Admin\Models\AdminAuditLog::create(['admin_id' => null, 'action' => $before ? 'seller_bank_account_updated' : 'seller_bank_account_added', 'subject_type' => 'user', 'subject_id' => $request->user()->id, 'summary' => 'Người bán cập nhật thông tin tài khoản ngân hàng đã mã hóa.', 'details' => ['bank_name' => $details['bank_name'], 'account_number_last4' => substr($details['account_number'], -4)]]);
        return response()->json(['status' => 'success', 'message' => 'Đã lưu thông tin tài khoản ngân hàng.', 'data' => [
            'bank_name' => $details['bank_name'], 'account_holder' => $details['account_holder'],
            'masked_account_number' => str_repeat('•', max(0, strlen($details['account_number']) - 4)).substr($details['account_number'], -4),
        ]]);
    }

    public function withdraw(Request $request, WalletLedger $ledger): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:50000', 'max:10000000'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:80', 'alpha_dash'],
            'confirm_bank_details' => ['required', 'accepted'],
        ]);
        $userId = (int) $request->user()->id;
        $ledger->ensureAccount($userId);
        $reference = 'WITHDRAW_'.$userId.'_'.Str::uuid()->toString();
        $withdrawal = DB::transaction(function () use ($data, $userId, $reference, $ledger): WithdrawalRequest {
            DB::table('wallet_accounts')->where('user_id', $userId)->lockForUpdate()->first();
            $bankAccount = SellerBankAccount::where('user_id', $userId)->first();
            if (! $bankAccount) throw new ConflictHttpException('Hãy lưu thông tin tài khoản ngân hàng trước khi gửi yêu cầu.');
            $requestedBankDetails = json_decode(Crypt::decryptString($bankAccount->details_ciphertext), true, flags: JSON_THROW_ON_ERROR);
            $existing = WithdrawalRequest::where('user_id', $userId)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                if ((int) $existing->amount !== (int) $data['amount']) {
                    throw new ConflictHttpException('Mã yêu cầu rút đã được dùng với số tiền khác. Hãy tạo yêu cầu mới.');
                }

                return $existing;
            }
            $withdrawal = WithdrawalRequest::create([
                'user_id' => $userId, 'amount' => (int) $data['amount'],
                'idempotency_key' => $data['idempotency_key'],
                'bank_details_ciphertext' => Crypt::encryptString(json_encode($requestedBankDetails, JSON_THROW_ON_ERROR)),
                'status' => 'pending_review', 'reference' => $reference,
            ]);
            $ledger->post($userId, $reference, 'withdrawal_hold', [
                ['user_id' => $userId, 'account_code' => 'available', 'amount' => -(int) $data['amount']],
                ['user_id' => $userId, 'account_code' => 'withdrawal_hold', 'amount' => (int) $data['amount']],
            ], 'withdrawal_request', $withdrawal->id, ['amount' => (int) $data['amount']]);

            return $withdrawal;
        }, 3);
        $snapshot = json_decode(Crypt::decryptString($withdrawal->bank_details_ciphertext), true, flags: JSON_THROW_ON_ERROR);

        return response()->json(['status' => 'success', 'message' => 'Yêu cầu rút đang chờ quản trị viên duyệt; chưa có khoản tiền nào được chuyển.', 'data' => [
            'id' => $withdrawal->id, 'reference' => $withdrawal->reference, 'amount' => $withdrawal->amount,
            'status' => $withdrawal->status, 'balance' => $ledger->balance($userId),
            'bank_snapshot' => ['bank_name' => $snapshot['bank_name'], 'account_holder' => $snapshot['account_holder'], 'masked_account_number' => str_repeat('•', max(0, strlen($snapshot['account_number']) - 4)).substr($snapshot['account_number'], -4)],
        ]], 201);
    }

    public function cancelWithdrawal(Request $request, int $id, WalletLedger $ledger): JsonResponse
    {
        $item = DB::transaction(function () use ($request, $id, $ledger): WithdrawalRequest {
            $item = WithdrawalRequest::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($id);
            if ($item->status !== 'pending_review') throw new ConflictHttpException('Chỉ yêu cầu đang chờ duyệt mới có thể hủy.');
            $ledger->post($item->user_id, 'WITHDRAW_CANCEL_'.$item->id, 'withdrawal_cancelled', [
                ['user_id' => $item->user_id, 'account_code' => 'withdrawal_hold', 'amount' => -$item->amount],
                ['user_id' => $item->user_id, 'account_code' => 'available', 'amount' => $item->amount],
            ], 'withdrawal_request', $item->id, ['amount' => $item->amount]);
            $item->update(['status' => 'cancelled', 'decision_reason' => 'Người bán đã hủy yêu cầu trước khi bắt đầu xử lý.']);
            \App\Modules\Admin\Models\AdminAuditLog::create([
                'admin_id' => null, 'action' => 'seller_withdrawal_cancelled', 'subject_type' => 'withdrawal_request',
                'subject_id' => $item->id, 'summary' => 'Người bán hủy yêu cầu chi trả trước khi xử lý; khoản giữ đã được trả lại.',
                'details' => ['reference' => $item->reference, 'amount' => $item->amount],
            ]);
            return $item;
        }, 3);
        return response()->json(['status' => 'success', 'message' => 'Đã hủy yêu cầu và trả tiền đang giữ về số dư khả dụng.', 'data' => ['id' => $item->id, 'status' => $item->status, 'balance' => $ledger->balance((int) $request->user()->id)]]);
    }

}
