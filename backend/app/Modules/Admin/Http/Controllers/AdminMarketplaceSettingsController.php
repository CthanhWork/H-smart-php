<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Admin\Services\MarketplaceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMarketplaceSettingsController extends Controller
{
    public function show(MarketplaceSettings $settings): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $settings->all()]);
    }

    public function update(Request $request, MarketplaceSettings $settings, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'commission_rate' => ['sometimes', 'integer', 'between:0,100'],
            'shipping_fee' => ['sometimes', 'integer', 'between:0,10000000'],
            'listing_free_monthly' => ['sometimes', 'integer', 'between:0,1000'],
            'listing_fee' => ['sometimes', 'integer', 'between:0,10000000'],
            'boost_price' => ['sometimes', 'integer', 'between:0,10000000'],
            'boost_days' => ['sometimes', 'integer', 'between:1,365'],
            'vip_price' => ['sometimes', 'integer', 'between:0,10000000'],
            'vip_days' => ['sometimes', 'integer', 'between:1,365'],
            'promotion_slots' => ['sometimes', 'integer', 'between:0,100'],
            'cod_deposit_percent' => ['sometimes', 'integer', 'between:20,30'],
        ]);
        abort_if($data === [], 422, 'Cần gửi ít nhất một thiết lập.');

        $values = $settings->update($data, (int) $request->user()->id);
        $audit->record($request->user(), 'marketplace_settings_updated', 'marketplace_settings', null,
            'Cập nhật biểu phí và giới hạn marketplace.', ['changed' => $data]);

        return response()->json(['status' => 'success', 'message' => 'Đã lưu thiết lập marketplace.', 'data' => $values]);
    }
}
