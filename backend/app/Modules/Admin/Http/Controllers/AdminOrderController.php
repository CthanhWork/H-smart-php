<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Order\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'string', 'in:pending_payment,paid,processing,shipping,completed,cancelled'], 'q' => ['nullable', 'string', 'max:120']]);
        $query = Order::with(['product:id,title,description,price,condition,category_id', 'product.images', 'buyer:id,email,full_name', 'seller:id,email,full_name', 'statusHistory'])->latest();
        if (!empty($data['status'])) $query->where('status', $data['status']);
        if (!empty($data['q'])) {
            $term = trim($data['q']);
            $query->where(function ($builder) use ($term): void {
                if (ctype_digit($term)) $builder->where('id', (int) $term);
                $builder->orWhere('tracking_number', 'like', "%{$term}%")
                    ->orWhereHas('product', fn ($q) => $q->where('title', 'like', "%{$term}%"))
                    ->orWhereHas('buyer', fn ($q) => $q->where('email', 'like', "%{$term}%")->orWhere('full_name', 'like', "%{$term}%"))
                    ->orWhereHas('seller', fn ($q) => $q->where('email', 'like', "%{$term}%")->orWhere('full_name', 'like', "%{$term}%"));
            });
        }
        return response()->json(['success' => true, 'message' => 'Danh sách giao dịch.', 'data' => $query->paginate(min((int) $request->input('per_page', 20), 100))]);
    }
}
