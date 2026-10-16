<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Actions\AdvanceOrderAction;
use App\Modules\Order\Actions\CancelOrderAction;
use App\Modules\Order\Actions\CreateOrderAction;
use App\Modules\Order\Actions\SimulatePaymentAction;
use App\Modules\Order\Http\Requests\CreateOrderRequest;
use App\Modules\Order\Http\Requests\ShipOrderRequest;
use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $orders = Order::with(['product.images', 'buyer', 'seller', 'offer', 'payment', 'review'])
            ->where(fn ($query) => $query->where('buyer_id', $userId)->orWhere('seller_id', $userId))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'message' => 'Danh sách đơn hàng.',
            'data' => [
                'content' => OrderResource::collection($orders->items())->resolve(),
                'pageNo' => $orders->currentPage() - 1,
                'pageSize' => $orders->perPage(),
                'totalElements' => $orders->total(),
                'totalPages' => $orders->lastPage(),
                'last' => $orders->currentPage() >= $orders->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = Order::with(['product.images', 'buyer', 'seller', 'offer', 'payment', 'review'])->findOrFail($id);
        abort_unless(in_array($request->user()->id, [$order->buyer_id, $order->seller_id], true), 404);

        return response()->json(['status' => 'success', 'message' => 'Chi tiết đơn hàng.', 'data' => new OrderResource($order)]);
    }

    public function store(CreateOrderRequest $request, CreateOrderAction $action): JsonResponse
    {
        $data = $request->validated();
        $order = $action->execute((int) $request->user()->id, (int) $data['offer_id'], [
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'address' => $data['address'],
        ]);

        return response()->json(['status' => 'success', 'message' => 'Đơn hàng đã được tạo và sản phẩm đang được giữ trong 30 phút.', 'data' => new OrderResource($order)], 201);
    }

    public function simulatePayment(Request $request, int $id, SimulatePaymentAction $action): JsonResponse
    {
        $order = $action->execute($id, (int) $request->user()->id);
        if ($order->status === Order::STATUS_CANCELLED) {
            return response()->json(['status' => 'error', 'message' => 'Thời gian giữ chỗ đã hết. Tạo đơn mới để tiếp tục.', 'data' => new OrderResource($order)], 409);
        }

        return response()->json(['status' => 'success', 'message' => 'Thanh toán mô phỏng thành công. Người bán có thể xử lý đơn hàng.', 'data' => new OrderResource($order)]);
    }

    public function cancel(Request $request, int $id, CancelOrderAction $action): JsonResponse
    {
        $order = $action->execute($id, (int) $request->user()->id);

        return response()->json(['status' => 'success', 'message' => 'Đã hủy đơn chưa thanh toán và mở lại sản phẩm.', 'data' => new OrderResource($order)]);
    }

    public function confirm(Request $request, int $id, AdvanceOrderAction $advance): JsonResponse
    {
        $order = $advance->execute($id, (int) $request->user()->id, 'confirm');

        return response()->json(['status' => 'success', 'message' => 'Đã xác nhận đơn hàng. Hãy chuẩn bị sản phẩm để gửi.', 'data' => new OrderResource($order)]);
    }

    public function ship(ShipOrderRequest $request, int $id, AdvanceOrderAction $advance): JsonResponse
    {
        $order = $advance->execute($id, (int) $request->user()->id, 'ship', $request->validated());

        return response()->json(['status' => 'success', 'message' => 'Đơn hàng đã được chuyển sang trạng thái đang giao.', 'data' => new OrderResource($order)]);
    }

    public function complete(Request $request, int $id, AdvanceOrderAction $advance): JsonResponse
    {
        $order = $advance->execute($id, (int) $request->user()->id, 'complete');

        return response()->json(['status' => 'success', 'message' => 'Đã xác nhận nhận hàng. Đơn hàng hoàn tất.', 'data' => new OrderResource($order)]);
    }
}
