<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Wallet\Models\WalletTransaction;
use App\Modules\Wallet\Models\WithdrawalRequest;
use App\Modules\Wallet\Services\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class WalletController
{
    public function show(Request $request, WalletLedger $ledger): JsonResponse
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
                'created_at' => $transaction->posted_at?->toIso8601String(),
            ]);
        $withdrawals = WithdrawalRequest::query()->where('user_id', $userId)->latest('id')->limit(20)->get()->map(fn (WithdrawalRequest $item) => [
            'id' => $item->id, 'amount' => $item->amount, 'status' => $item->status,
            'reference' => $item->reference, 'created_at' => $item->created_at?->toIso8601String(),
            'decision_reason' => $item->decision_reason,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Số dư ví H-Smart.', 'data' => [
            ...$ledger->balance($userId), 'transactions' => $transactions, 'withdrawals' => $withdrawals,
            'mode' => 'simulation',
        ]]);
    }

    public function simulatedDeposit(Request $request, WalletLedger $ledger): JsonResponse
    {
        $this->assertSimulationAllowed();
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:10000', 'max:10000000'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:80', 'alpha_dash'],
        ]);
        $userId = (int) $request->user()->id;
        $reference = 'SIMTOPUP_'.substr(hash('sha256', $userId.':'.$data['idempotency_key']), 0, 64);
        $transaction = $ledger->post($userId, $reference, 'simulated_deposit', [
            ['user_id' => $userId, 'account_code' => 'available', 'amount' => (int) $data['amount']],
            ['user_id' => null, 'account_code' => 'system_simulation_clearing', 'amount' => -(int) $data['amount']],
        ], 'simulated_deposit', null, ['environment' => app()->environment(), 'amount' => (int) $data['amount']]);

        return response()->json(['status' => 'success', 'message' => 'Đã cộng số dư mô phỏng; đây không phải khoản tiền thật.', 'data' => [
            'reference' => $transaction->reference, 'balance' => $ledger->balance($userId), 'mode' => 'simulation',
        ]], 201);
    }

    public function withdraw(Request $request, WalletLedger $ledger): JsonResponse
    {
        $this->assertSimulationAllowed();
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:50000', 'max:10000000'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:80', 'alpha_dash'],
            'bank_name' => ['required', 'string', 'min:2', 'max:100'],
            'account_holder' => ['required', 'string', 'min:2', 'max:150'],
            'account_number' => ['required', 'string', 'min:5', 'max:40', 'regex:/^[0-9A-Za-z -]+$/'],
        ]);
        $userId = (int) $request->user()->id;
        $ledger->ensureAccount($userId);
        $reference = 'WITHDRAW_'.$userId.'_'.Str::uuid()->toString();
        $withdrawal = DB::transaction(function () use ($data, $userId, $reference, $ledger): WithdrawalRequest {
            DB::table('wallet_accounts')->where('user_id', $userId)->lockForUpdate()->first();
            $existing = WithdrawalRequest::where('user_id', $userId)->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                $storedBankDetails = json_decode(Crypt::decryptString($existing->bank_details_ciphertext), true, flags: JSON_THROW_ON_ERROR);
                $requestedBankDetails = [
                    'bank_name' => trim($data['bank_name']),
                    'account_holder' => trim($data['account_holder']),
                    'account_number' => preg_replace('/\s+/', '', $data['account_number']),
                ];
                if ((int) $existing->amount !== (int) $data['amount'] || $storedBankDetails !== $requestedBankDetails) {
                    throw new ConflictHttpException('Mã yêu cầu rút đã được dùng với thông tin khác. Hãy tạo yêu cầu mới.');
                }

                return $existing;
            }
            $withdrawal = WithdrawalRequest::create([
                'user_id' => $userId, 'amount' => (int) $data['amount'],
                'idempotency_key' => $data['idempotency_key'],
                'bank_details_ciphertext' => Crypt::encryptString(json_encode([
                    'bank_name' => trim($data['bank_name']), 'account_holder' => trim($data['account_holder']),
                    'account_number' => preg_replace('/\s+/', '', $data['account_number']),
                ], JSON_THROW_ON_ERROR)),
                'status' => 'pending_review', 'reference' => $reference,
            ]);
            $ledger->post($userId, $reference, 'withdrawal_hold', [
                ['user_id' => $userId, 'account_code' => 'available', 'amount' => -(int) $data['amount']],
                ['user_id' => $userId, 'account_code' => 'withdrawal_hold', 'amount' => (int) $data['amount']],
            ], 'withdrawal_request', $withdrawal->id, ['simulation' => true, 'amount' => (int) $data['amount']]);

            return $withdrawal;
        }, 3);

        return response()->json(['status' => 'success', 'message' => 'Yêu cầu rút đang chờ quản trị viên duyệt; chưa có khoản tiền nào được chuyển.', 'data' => [
            'id' => $withdrawal->id, 'reference' => $withdrawal->reference, 'amount' => $withdrawal->amount,
            'status' => $withdrawal->status, 'balance' => $ledger->balance($userId),
        ]], 201);
    }

    private function assertSimulationAllowed(): void
    {
        if (! in_array(app()->environment(), ['local', 'testing'], true) || ! config('services.wallet.simulation_enabled')) {
            throw new ConflictHttpException('Chức năng tiền mô phỏng chỉ bật trong môi trường local/testing có cấu hình cho phép.');
        }
    }
}
