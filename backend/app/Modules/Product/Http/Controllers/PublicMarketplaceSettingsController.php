<?php

namespace App\Modules\Product\Http\Controllers;

use App\Modules\Admin\Services\MarketplaceSettings;
use Illuminate\Http\JsonResponse;

class PublicMarketplaceSettingsController
{
    public function __invoke(MarketplaceSettings $settings): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $settings->all()]);
    }
}
