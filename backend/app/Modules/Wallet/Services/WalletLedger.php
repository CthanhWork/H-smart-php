<?php

namespace App\Modules\Wallet\Services;

use App\Modules\Order\Models\Order;
use App\Modules\Wallet\Models\WalletEntry;
use App\Modules\Wallet\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class WalletLedger
{
    public function ensureAccount(int $userId): void
    {
        DB::table('wallet_accounts')->insertOrIgnore(['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** @param array<int, array{user_id: int|null, account_code: string, amount: int}> $lines */
    public function post(int $userId, string $reference, string $type, array $lines, ?string $sourceType = null, ?int $sourceId = null, array $metadata = []): WalletTransaction
    {
        if (array_sum(array_column($lines, 'amount')) !== 0 || count($lines) < 2) {
            throw new \InvalidArgumentException('Bút toán phải có ít nhất hai dòng và tổng bằng 0.');
        }

        try {
            return DB::transaction(function () use ($userId, $reference, $type, $lines, $sourceType, $sourceId, $metadata): WalletTransaction {
                $affectedUserIds = collect($lines)->pluck('user_id')->filter(fn ($id) => $id !== null)->map(fn ($id) => (int) $id)->unique()->sort()->values();
                foreach ($affectedUserIds as $affectedUserId) {
                    $this->ensureAccount($affectedUserId);
                }
                DB::table('wallet_accounts')->whereIn('user_id', $affectedUserIds)->orderBy('user_id')->lockForUpdate()->get();
                $existing = WalletTransaction::where('reference', $reference)->first();
                if ($existing) {
                    return $this->replayExisting($existing, $userId, $type, $lines, $sourceType, $sourceId);
                }

                $balances = WalletEntry::query()->whereIn('user_id', $affectedUserIds)->whereIn('account_code', ['available', 'withdrawal_hold', 'pending_release'])
                    ->join('wallet_transactions', 'wallet_transactions.id', '=', 'wallet_entries.wallet_transaction_id')
                    ->where('wallet_transactions.status', 'posted')
                    ->select('user_id', 'account_code', DB::raw('SUM(amount) as balance'))->groupBy('user_id', 'account_code')->get()
                    ->mapWithKeys(fn ($row) => [$row->user_id.':'.$row->account_code => (int) $row->balance]);
                foreach ($affectedUserIds as $affectedUserId) {
                    foreach (['available', 'withdrawal_hold', 'pending_release'] as $accountCode) {
                        $delta = collect($lines)->filter(fn ($line) => (int) ($line['user_id'] ?? 0) === $affectedUserId && $line['account_code'] === $accountCode)->sum('amount');
                        if ((int) ($balances[$affectedUserId.':'.$accountCode] ?? 0) + (int) $delta < 0) {
                            throw new ConflictHttpException('Số dư khả dụng hoặc tiền đang giữ không đủ.');
                        }
                    }
                }

                $transaction = WalletTransaction::create([
                    'reference' => $reference, 'idempotency_key' => $reference, 'type' => $type, 'status' => 'posted',
                    'source_type' => $sourceType, 'source_id' => $sourceId,
                    'metadata' => $metadata ?: null, 'posted_at' => now(),
                ]);
                foreach ($lines as $line) {
                    WalletEntry::create([
                        'wallet_transaction_id' => $transaction->id,
                        'user_id' => $line['user_id'], 'account_code' => $line['account_code'], 'amount' => $line['amount'],
                    ]);
                }

                return $transaction->load('entries');
            }, 3);
        } catch (QueryException $exception) {
            $existing = WalletTransaction::where('reference', $reference)->first();
            if ($existing) {
                return $this->replayExisting($existing, $userId, $type, $lines, $sourceType, $sourceId);
            }
            throw $exception;
        }
    }

    /** @param array<int, array{user_id: int|null, account_code: string, amount: int}> $lines */
    private function replayExisting(WalletTransaction $existing, int $userId, string $type, array $lines, ?string $sourceType, ?int $sourceId): WalletTransaction
    {
        abort_unless($existing->entries()->where('user_id', $userId)->exists(), 404);

        $expected = collect($lines)->map(fn (array $line): array => [
            'user_id' => isset($line['user_id']) ? (int) $line['user_id'] : null,
            'account_code' => $line['account_code'],
            'amount' => (int) $line['amount'],
        ])->sortBy(fn (array $line): string => json_encode($line, JSON_THROW_ON_ERROR))->values()->all();
        $actual = $existing->entries->map(fn (WalletEntry $entry): array => [
            'user_id' => $entry->user_id === null ? null : (int) $entry->user_id,
            'account_code' => $entry->account_code,
            'amount' => (int) $entry->amount,
        ])->sortBy(fn (array $line): string => json_encode($line, JSON_THROW_ON_ERROR))->values()->all();

        if ($existing->type !== $type || $existing->source_type !== $sourceType
            || (int) ($existing->source_id ?? 0) !== (int) ($sourceId ?? 0) || $expected !== $actual) {
            throw new ConflictHttpException('Mã giao dịch đã được dùng với nội dung khác. Hãy tạo yêu cầu mới.');
        }

        return $existing->load('entries');
    }

    public function reference(string $prefix): string
    {
        return $prefix.'_'.Str::uuid()->toString();
    }

    public function balance(int $userId): array
    {
        $balances = WalletEntry::query()->where('user_id', $userId)
            ->join('wallet_transactions', 'wallet_transactions.id', '=', 'wallet_entries.wallet_transaction_id')
            ->where('wallet_transactions.status', 'posted')
            ->select('account_code', DB::raw('SUM(amount) as balance'))->groupBy('account_code')->pluck('balance', 'account_code');

        return [
            'available' => (int) ($balances['available'] ?? 0),
            'withdrawal_hold' => (int) ($balances['withdrawal_hold'] ?? 0),
            'pending_release' => (int) ($balances['pending_release'] ?? 0),
        ];
    }

    public function bookOrderEscrow(Order $order, string $provider): WalletTransaction
    {
        $sellerProceeds = $order->product_amount - $order->platform_fee;
        if ($sellerProceeds < 0) {
            throw new ConflictHttpException('Hoa hồng vượt tiền sản phẩm; cần quản trị viên đối soát đơn này.');
        }

        return $this->post((int) $order->seller_id, 'ORDER_ESCROW_'.$order->id, 'order_escrow_received', [
            ['user_id' => (int) $order->seller_id, 'account_code' => 'pending_release', 'amount' => $sellerProceeds],
            ['user_id' => null, 'account_code' => 'platform_revenue', 'amount' => (int) $order->platform_fee],
            ['user_id' => null, 'account_code' => 'shipping_payable', 'amount' => (int) $order->shipping_fee],
            ['user_id' => null, 'account_code' => 'provider_clearing', 'amount' => -(int) $order->total_amount],
        ], 'order', (int) $order->id, ['provider' => $provider, 'amount' => (int) $order->total_amount]);
    }

    public function bookCodDeposit(Order $order, string $provider): WalletTransaction
    {
        $deposit = (int) $order->cod_deposit_amount;
        $sellerDeposit = $deposit - (int) $order->platform_fee;
        if ($sellerDeposit < 0) {
            throw new ConflictHttpException('Tiền cọc thấp hơn hoa hồng; cần quản trị viên đối soát đơn này.');
        }

        return $this->post((int) $order->seller_id, 'ORDER_COD_DEPOSIT_'.$order->id, 'cod_deposit_received', [
            ['user_id' => (int) $order->seller_id, 'account_code' => 'pending_release', 'amount' => $sellerDeposit],
            ['user_id' => null, 'account_code' => 'platform_revenue', 'amount' => (int) $order->platform_fee],
            ['user_id' => null, 'account_code' => 'provider_clearing', 'amount' => -$deposit],
        ], 'order', (int) $order->id, ['provider' => $provider, 'amount' => $deposit, 'direct_cod_remaining' => (int) $order->cod_remaining_amount]);
    }

    public function releaseOrderToWallet(Order $order): WalletTransaction
    {
        $codGross = (int) $order->cod_deposit_amount > 0 ? (int) $order->cod_deposit_amount : (int) $order->product_amount;
        $amount = max(0, ($order->payment_method === 'cod' ? $codGross : (int) $order->product_amount) - (int) $order->platform_fee);

        return $this->post((int) $order->seller_id, 'ORDER_RELEASE_'.$order->id, 'order_proceeds_released', [
            ['user_id' => (int) $order->seller_id, 'account_code' => 'pending_release', 'amount' => -$amount],
            ['user_id' => (int) $order->seller_id, 'account_code' => 'available', 'amount' => $amount],
        ], 'order', (int) $order->id, ['amount' => $amount]);
    }

    public function refundOrderToBuyerWallet(Order $order, int $adminId, string $reason): WalletTransaction
    {
        $codGross = (int) $order->cod_deposit_amount > 0 ? (int) $order->cod_deposit_amount : (int) $order->product_amount;
        $grossRefund = $order->payment_method === 'cod' ? $codGross : (int) $order->total_amount;
        $sellerProceeds = $order->payment_method === 'cod' ? $codGross - (int) $order->platform_fee : (int) $order->product_amount - (int) $order->platform_fee;
        if ($sellerProceeds < 0) {
            throw new ConflictHttpException('Số tiền phân bổ của đơn không hợp lệ.');
        }
        $lines = [
            ['user_id' => (int) $order->seller_id, 'account_code' => 'pending_release', 'amount' => -$sellerProceeds],
            ['user_id' => null, 'account_code' => 'platform_revenue', 'amount' => -(int) $order->platform_fee],
            ['user_id' => (int) $order->buyer_id, 'account_code' => 'available', 'amount' => $grossRefund],
        ];
        if ($order->payment_method !== 'cod') {
            $lines[] = ['user_id' => null, 'account_code' => 'shipping_payable', 'amount' => -(int) $order->shipping_fee];
        }

        return $this->post((int) $order->buyer_id, 'ORDER_REFUND_WALLET_'.$order->id, 'order_refund_to_wallet', $lines,
            'order', (int) $order->id, ['reason' => $reason, 'admin_id' => $adminId, 'amount' => $grossRefund]);
    }

    public function refundCodDepositToBuyer(Order $order, int $adminId, string $reason): WalletTransaction
    {
        $deposit = (int) $order->cod_deposit_amount;
        if ($deposit <= 0 || $deposit < (int) $order->platform_fee) {
            throw new ConflictHttpException('Không thể tự động hoàn cọc COD cũ hoặc khoản cọc không đủ phân bổ; cần đối soát thủ công.');
        }
        $sellerAmount = $deposit - (int) $order->platform_fee;

        return $this->post((int) $order->buyer_id, 'ORDER_COD_DEPOSIT_REFUND_'.$order->id, 'cod_deposit_refunded', [
            ['user_id' => (int) $order->seller_id, 'account_code' => 'pending_release', 'amount' => -$sellerAmount],
            ['user_id' => null, 'account_code' => 'platform_revenue', 'amount' => -(int) $order->platform_fee],
            ['user_id' => (int) $order->buyer_id, 'account_code' => 'available', 'amount' => $deposit],
        ], 'order', (int) $order->id, ['reason' => $reason, 'admin_id' => $adminId, 'amount' => $deposit]);
    }
}
