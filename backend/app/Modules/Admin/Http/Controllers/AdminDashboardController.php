<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AdminReport;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Thống kê quản trị.', 'data' => [
            'users' => User::count(),
            'products' => Product::count(),
            'orders' => Order::count(),
            'hidden_products' => Product::where('status', Product::STATUS_HIDDEN)->count(),
            'pending_reports' => AdminReport::where('status', 'pending')->count(),
        ]]);
    }
}
