<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Wallet\Models\WalletEntry;
use App\Support\Reporting\RevenuePeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerRevenueController
{
    public function __invoke(Request $request, RevenuePeriod $period): JsonResponse
    {
        $range = $period->resolve($request);
        $sellerId = (int) $request->user()->id;
        $completed = DB::table('orders')->where('seller_id', $sellerId)->where('status', 'completed')
            ->whereNotNull('completed_at')->where('completed_at', '>=', $range['from_utc'])->where('completed_at', '<', $range['to_utc_exclusive']);
        $gross = (int) (clone $completed)->sum('product_amount');
        $commission = (int) (clone $completed)->sum('platform_fee');
        $completedCount = (int) (clone $completed)->count();
        $cancelledCount = DB::table('orders')->where('seller_id', $sellerId)->where('status', 'cancelled')
            ->whereNotNull('cancelled_at')->where('cancelled_at', '>=', $range['from_utc'])->where('cancelled_at', '<', $range['to_utc_exclusive'])->count();

        $refundTransactions = DB::table('wallet_transactions as t')->where('t.source_type', 'order')
            ->whereIn('t.type', ['order_refund_to_wallet', 'cod_deposit_refunded'])
            ->where('t.status', 'posted')->whereNotNull('t.posted_at')
            ->where('t.posted_at', '>=', $range['from_utc'])->where('t.posted_at', '<', $range['to_utc_exclusive'])
            ->whereIn('t.source_id', DB::table('orders')->select('id')->where('seller_id', $sellerId));
        $refundCount = (int) (clone $refundTransactions)->distinct('t.source_id')->count('t.source_id');
        $refundAmount = (int) DB::table('wallet_entries as e')->join('wallet_transactions as t', 't.id', '=', 'e.wallet_transaction_id')
            ->join('orders as o', fn ($join) => $join->on('o.id', '=', 't.source_id')->where('o.seller_id', '=', $sellerId))
            ->where('t.source_type', 'order')->whereIn('t.type', ['order_refund_to_wallet', 'cod_deposit_refunded'])
            ->where('t.status', 'posted')->where('t.posted_at', '>=', $range['from_utc'])->where('t.posted_at', '<', $range['to_utc_exclusive'])
            ->where('e.user_id', $sellerId)->where('e.account_code', 'pending_release')->where('e.amount', '<', 0)->sum(DB::raw('ABS(e.amount)'));

        $balance = (int) WalletEntry::query()->where('wallet_entries.user_id', $sellerId)->where('account_code', 'pending_release')
            ->join('wallet_transactions', 'wallet_transactions.id', '=', 'wallet_entries.wallet_transaction_id')
            ->where('wallet_transactions.status', 'posted')->sum('wallet_entries.amount');
        $credited = (int) DB::table('wallet_entries as e')->join('wallet_transactions as t', 't.id', '=', 'e.wallet_transaction_id')
            ->where('e.user_id', $sellerId)->where('e.account_code', 'available')->where('t.type', 'order_proceeds_released')
            ->where('t.status', 'posted')->where('t.posted_at', '>=', $range['from_utc'])->where('t.posted_at', '<', $range['to_utc_exclusive'])->sum('e.amount');
        $directCod = (int) DB::table('orders')->where('seller_id', $sellerId)->where('payment_method', 'cod')
            ->where('cod_remaining_collection_type', 'direct')->whereNotNull('cod_remaining_collected_at')
            ->where('cod_remaining_collected_at', '>=', $range['from_utc'])->where('cod_remaining_collected_at', '<', $range['to_utc_exclusive'])
            ->sum('cod_remaining_amount');

        $bucket = $period->bucketExpression('completed_at', $range['grain']);
        $salesRows = DB::table('orders')->where('seller_id', $sellerId)->where('status', 'completed')
            ->whereNotNull('completed_at')->where('completed_at', '>=', $range['from_utc'])->where('completed_at', '<', $range['to_utc_exclusive'])
            ->selectRaw("{$bucket} as period, SUM(product_amount - platform_fee) as amount")->groupBy('period')->get()->keyBy('period');
        $refundBucket = $period->bucketExpression('t.posted_at', $range['grain']);
        $refundRows = DB::table('wallet_entries as e')->join('wallet_transactions as t', 't.id', '=', 'e.wallet_transaction_id')
            ->join('orders as o', fn ($join) => $join->on('o.id', '=', 't.source_id')->where('o.seller_id', '=', $sellerId))
            ->where('t.source_type', 'order')->whereIn('t.type', ['order_refund_to_wallet', 'cod_deposit_refunded'])
            ->where('t.status', 'posted')->where('t.posted_at', '>=', $range['from_utc'])->where('t.posted_at', '<', $range['to_utc_exclusive'])
            ->where('e.user_id', $sellerId)->where('e.account_code', 'pending_release')->where('e.amount', '<', 0)
            ->selectRaw("{$refundBucket} as period, SUM(ABS(e.amount)) as amount")->groupBy('period')->get()->keyBy('period');
        $creditBucket = $period->bucketExpression('t.posted_at', $range['grain']);
        $creditRows = DB::table('wallet_entries as e')->join('wallet_transactions as t', 't.id', '=', 'e.wallet_transaction_id')
            ->where('e.user_id', $sellerId)->where('e.account_code', 'available')->where('t.type', 'order_proceeds_released')
            ->where('t.status', 'posted')->where('t.posted_at', '>=', $range['from_utc'])->where('t.posted_at', '<', $range['to_utc_exclusive'])
            ->selectRaw("{$creditBucket} as period, SUM(e.amount) as amount")->groupBy('period')->get()->keyBy('period');
        $saleMap = $salesRows->map(fn ($row) => (int) $row->amount)->all();
        $refundMap = $refundRows->map(fn ($row) => (int) $row->amount)->all();
        $creditMap = $creditRows->map(fn ($row) => (int) $row->amount)->all();
        $series = $period->fillSeries($range, $saleMap, 'sales_net');
        foreach ($series as &$point) {
            $point['refunds'] = $refundMap[$point['period']] ?? 0;
            $point['credited_to_wallet'] = $creditMap[$point['period']] ?? 0;
            $point['sales_net'] -= $point['refunds'];
        }
        unset($point);

        $orderDetails = DB::table('orders as o')->leftJoin('products as p', 'p.id', '=', 'o.product_id')
            ->where('o.seller_id', $sellerId)->whereIn('o.status', ['completed', 'cancelled'])
            ->where(function (Builder $query) use ($range): void {
                $query->where(fn (Builder $q) => $q->where('o.status', 'completed')->where('o.completed_at', '>=', $range['from_utc'])->where('o.completed_at', '<', $range['to_utc_exclusive']))
                    ->orWhere(fn (Builder $q) => $q->where('o.status', 'cancelled')->where('o.cancelled_at', '>=', $range['from_utc'])->where('o.cancelled_at', '<', $range['to_utc_exclusive']));
            })
            ->selectRaw("COALESCE(o.completed_at, o.cancelled_at) as occurred_at, 'order' as event_type, o.id as related_order_id, NULL as reference, o.status, o.product_amount as amount, p.title as description");
        $ledgerDetails = DB::table('wallet_transactions as t')->join('wallet_entries as e', 'e.wallet_transaction_id', '=', 't.id')
            ->where('e.user_id', $sellerId)->where('t.status', 'posted')->whereNotNull('t.posted_at')
            ->where('t.posted_at', '>=', $range['from_utc'])->where('t.posted_at', '<', $range['to_utc_exclusive'])
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $q) => $q->where('t.type', 'order_proceeds_released')->where('e.account_code', 'available'))
                    ->orWhere(fn (Builder $q) => $q->whereIn('t.type', ['order_refund_to_wallet', 'cod_deposit_refunded'])->where('e.account_code', 'pending_release'));
            })
            ->selectRaw("t.posted_at as occurred_at, t.type as event_type, t.source_id as related_order_id, t.reference, t.status, e.amount, t.type as description");
        $events = $orderDetails->unionAll($ledgerDetails);
        $details = DB::query()->fromSub($events, 'financial_events')->orderByDesc('occurred_at')->orderByDesc('related_order_id')->paginate(20, ['*'], 'page', $range['page']);

        return response()->json(['status' => 'success', 'message' => 'Thống kê doanh thu người bán.', 'data' => [
            'period' => $this->periodResource($range),
            'metrics' => [
                'completed_product_amount' => $gross,
                'commission_amount' => $commission,
                'refund_amount' => $refundAmount,
                'net_sales_revenue' => $gross - $commission - $refundAmount,
                'pending_release_balance' => max(0, $balance),
                'credited_to_wallet' => $credited,
                'direct_cod_received' => $directCod,
                'completed_orders' => $completedCount,
                'cancelled_orders' => (int) $cancelledCount,
                'refunded_orders' => $refundCount,
            ],
            'series' => $series,
            'details' => [
                'content' => $details->items(), 'page' => $details->currentPage(),
                'per_page' => $details->perPage(), 'total' => $details->total(), 'total_pages' => $details->lastPage(),
            ],
            'definitions' => [
                'completed_product_amount' => 'Tiền hàng của đơn hoàn tất, tính theo completed_at trong khoảng chọn; không gồm phí vận chuyển.',
                'commission_amount' => 'platform_fee snapshot của đơn hoàn tất; chart ghi doanh số thuần theo completed_at.',
                'refund_amount' => 'Phần tiền thuần của người bán bị đảo từ pending_release theo bút toán hoàn trong posted_at.',
                'pending_release_balance' => 'Số dư pending_release hiện tại trong sổ ví; không phải tiền đã nhận.',
                'credited_to_wallet' => 'Bút toán order_proceeds_released ghi vào available trong posted_at; nạp/rút ví bị loại trừ.',
                'direct_cod_received' => 'Phần COD còn lại được ghi nhận thu trực tiếp theo cod_remaining_collected_at; không cộng vào ví.',
            ],
        ]]);
    }

    private function periodResource(array $range): array
    {
        return ['preset' => $range['period'], 'from' => $range['from'], 'to' => $range['to'], 'timezone' => $range['timezone'], 'grain' => $range['grain']];
    }
}
