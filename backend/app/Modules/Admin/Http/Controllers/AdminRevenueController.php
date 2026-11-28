<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Support\Reporting\RevenuePeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRevenueController
{
    private const COMMISSION_TYPES = ['order_escrow_received', 'cod_deposit_received'];
    private const REFUND_TYPES = ['order_refund_to_wallet', 'cod_deposit_refunded'];

    public function __invoke(Request $request, RevenuePeriod $period): JsonResponse
    {
        $range = $period->resolve($request);
        $platformEvents = DB::table('wallet_entries as e')->join('wallet_transactions as t', 't.id', '=', 'e.wallet_transaction_id')
            ->whereNull('e.user_id')->where('e.account_code', 'platform_revenue')->where('t.status', 'posted')
            ->whereNotNull('t.posted_at')->where('t.posted_at', '>=', $range['from_utc'])->where('t.posted_at', '<', $range['to_utc_exclusive']);
        $byType = (clone $platformEvents)->select('t.type', DB::raw('SUM(e.amount) as amount'))->groupBy('t.type')->get()->keyBy('type');
        $sumTypes = fn (array $types): int => (int) $byType->only($types)->sum(fn ($row) => (int) $row->amount);
        $commission = $sumTypes(self::COMMISSION_TYPES);
        $listing = $sumTypes(['listing_fee']);
        $promotions = $sumTypes(['product_promotion']);
        $refundReversals = $sumTypes(self::REFUND_TYPES);
        $net = $commission + $listing + $promotions + $refundReversals;

        $paidOrders = DB::table('orders')->where('payment_status', 'paid')->whereNotNull('payment_paid_at')
            ->where('payment_paid_at', '>=', $range['from_utc'])->where('payment_paid_at', '<', $range['to_utc_exclusive']);
        $goodsValue = (int) (clone $paidOrders)->sum('product_amount');
        $paidOrderCount = (int) (clone $paidOrders)->count();
        $liabilities = DB::table('wallet_entries as e')->join('wallet_transactions as t', 't.id', '=', 'e.wallet_transaction_id')
            ->whereNotNull('e.user_id')->whereIn('e.account_code', ['available', 'withdrawal_hold', 'pending_release'])
            ->where('t.status', 'posted')->sum('e.amount');
        $held = (int) DB::table('wallet_entries as e')->join('wallet_transactions as t', 't.id', '=', 'e.wallet_transaction_id')
            ->whereNotNull('e.user_id')->where('e.account_code', 'pending_release')->where('t.status', 'posted')->sum('e.amount');
        $withdrawalHold = (int) DB::table('wallet_entries as e')->join('wallet_transactions as t', 't.id', '=', 'e.wallet_transaction_id')
            ->whereNotNull('e.user_id')->where('e.account_code', 'withdrawal_hold')->where('t.status', 'posted')->sum('e.amount');
        $reconciliationDeposits = DB::table('wallet_vnpay_deposits')->where('status', 'reconciliation_required');
        $reconciliationCount = (int) (clone $reconciliationDeposits)->count();
        $reconciliationAmount = (int) (clone $reconciliationDeposits)->sum('amount');

        $eventBucket = $period->bucketExpression('t.posted_at', $range['grain']);
        $eventRows = (clone $platformEvents)->selectRaw("{$eventBucket} as period, t.type, SUM(e.amount) as amount")
            ->groupBy('period', 't.type')->get();
        $bucketValues = [];
        foreach ($eventRows as $row) {
            $bucket = (string) $row->period;
            $type = (string) $row->type;
            $bucketValues[$bucket] ??= ['commission' => 0, 'listing_fees' => 0, 'promotions' => 0, 'refunds' => 0];
            if (in_array($type, self::COMMISSION_TYPES, true)) $bucketValues[$bucket]['commission'] += (int) $row->amount;
            elseif ($type === 'listing_fee') $bucketValues[$bucket]['listing_fees'] += (int) $row->amount;
            elseif ($type === 'product_promotion') $bucketValues[$bucket]['promotions'] += (int) $row->amount;
            elseif (in_array($type, self::REFUND_TYPES, true)) $bucketValues[$bucket]['refunds'] += (int) $row->amount;
        }
        $series = $period->fillSeries($range, []);
        foreach ($series as &$point) {
            $point['commission'] = $bucketValues[$point['period']]['commission'] ?? 0;
            $point['listing_fees'] = $bucketValues[$point['period']]['listing_fees'] ?? 0;
            $point['promotions'] = $bucketValues[$point['period']]['promotions'] ?? 0;
            $point['refunds'] = $bucketValues[$point['period']]['refunds'] ?? 0;
            $point['net'] = $point['commission'] + $point['listing_fees'] + $point['promotions'] + $point['refunds'];
        }
        unset($point);

        $details = (clone $platformEvents)->select([
            't.id as transaction_id', 't.reference', 't.type', 't.source_type', 't.source_id', 't.posted_at',
            't.metadata', 'e.amount',
        ])->orderByDesc('t.posted_at')->orderByDesc('t.id')->paginate(25, ['*'], 'page', $range['page']);

        return response()->json(['status' => 'success', 'message' => 'Báo cáo tài chính quản trị.', 'data' => [
            'period' => ['preset' => $range['period'], 'from' => $range['from'], 'to' => $range['to'], 'timezone' => $range['timezone'], 'grain' => $range['grain']],
            'mode' => 'Dữ liệu sổ ứng dụng; khoản thanh toán VNPay sử dụng Sandbox và chưa phải đối soát ngân hàng production.',
            'metrics' => [
                'paid_goods_value' => $goodsValue,
                'paid_orders' => $paidOrderCount,
                'commission_revenue' => $commission,
                'listing_fee_revenue' => $listing,
                'promotion_revenue' => $promotions,
                'revenue_reversals' => $refundReversals,
                'net_platform_revenue' => $net,
                'held_for_sellers' => $held,
                'user_wallet_liabilities' => (int) $liabilities,
                'pending_withdrawals' => $withdrawalHold,
                'wallet_deposits_requiring_reconciliation' => $reconciliationCount,
                'wallet_deposit_reconciliation_amount' => $reconciliationAmount,
            ],
            'series' => $series,
            'details' => [
                'content' => $details->items(), 'page' => $details->currentPage(),
                'per_page' => $details->perPage(), 'total' => $details->total(), 'total_pages' => $details->lastPage(),
            ],
            'definitions' => [
                'paid_goods_value' => 'Tổng tiền hàng của đơn payment_status=paid theo payment_paid_at; không gồm phí vận chuyển, tiền nạp ví hoặc tiền gửi giữ hộ.',
                'commission_revenue' => 'Bút toán dương platform_revenue của order_escrow_received/cod_deposit_received theo wallet_transactions.posted_at.',
                'listing_fee_revenue' => 'Bút toán posted loại listing_fee; báo giá/chưa thanh toán không được tính.',
                'promotion_revenue' => 'Bút toán posted loại product_promotion; không đọc giá cấu hình hay trạng thái gói để suy đoán doanh thu.',
                'revenue_reversals' => 'Bút toán âm platform_revenue của hoàn tiền order/COD deposit theo posted_at.',
                'net_platform_revenue' => 'Tổng các dòng platform_revenue từ sổ cái trong kỳ; mỗi wallet transaction chỉ tính các dòng đã ghi sổ.',
                'held_for_sellers' => 'Số dư pending_release hiện tại; là nghĩa vụ giữ hộ, không phải doanh thu.',
                'user_wallet_liabilities' => 'Tổng available + withdrawal_hold + pending_release có user_id trong sổ đã ghi sổ.',
            ],
        ]]);
    }
}
