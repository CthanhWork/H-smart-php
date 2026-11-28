<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Models\PaymentReconciliation;
use Illuminate\Http\JsonResponse;

class AdminPaymentReconciliationController extends Controller
{
    public function index(): JsonResponse
    {
        $items = PaymentReconciliation::latest()->paginate(50);

        return response()->json(['status' => 'success', 'data' => $items]);
    }
}
