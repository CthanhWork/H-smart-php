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
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = Order::with(['product.images', 'buyer', 'seller', 'offer'])
            ->where(fn ($query) => $query->where('buyer_id', $userId)->orWhere('seller_id', $userId));
        if ($request->filled('offer_id')) {
            $query->where('offer_id', (int) $request->query('offer_id'));
        }
        $orders = $query->orderByDesc('created_at')->paginate(20);

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
        $order = Order::with(['product.images', 'buyer', 'seller', 'offer'])->findOrFail($id);
        abort_unless(in_array($request->user()->id, [$order->buyer_id, $order->seller_id], true), 404);

        return response()->json(['status' => 'success', 'message' => 'Chi tiết đơn hàng.', 'data' => new OrderResource($order)]);
    }

    public function store(CreateOrderRequest $request, CreateOrderAction $action, NotificationService $notifications): JsonResponse
    {
        $data = $request->validated();
        $order = $action->execute((int) $request->user()->id, (int) $data['offer_id'], [
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'address' => $data['address'],
        ]);
        $notifications->send($order->seller, $order->buyer, 'order_created', 'Có đơn hàng mới', 'Bạn có đơn hàng #'.$order->id.' cho sản phẩm “'.$order->product->title.'”.', '/me/orders', ['order_id' => $order->id]);

        return response()->json(['status' => 'success', 'message' => 'Đơn hàng đã được tạo và sản phẩm đang được giữ trong 30 phút.', 'data' => new OrderResource($order)], 201);
    }

    public function simulatePayment(Request $request, int $id, SimulatePaymentAction $action, NotificationService $notifications): JsonResponse
    {
        $order = $action->execute($id, (int) $request->user()->id);
        if ($order->status === Order::STATUS_CANCELLED) {
            $body = 'Đơn hàng #'.$order->id.' đã hết hạn chờ thanh toán.';
            $notifications->send($order->buyer, null, 'order_expired', 'Đơn hàng đã hết hạn', $body, '/me/orders', ['order_id' => $order->id]);
            $notifications->send($order->seller, null, 'order_expired', 'Đơn hàng đã hết hạn', $body, '/me/orders', ['order_id' => $order->id]);
            return response()->json(['status' => 'error', 'message' => 'Thời gian giữ chỗ đã hết. Tạo đơn mới để tiếp tục.', 'data' => new OrderResource($order)], 409);
        }
        $notifications->send($order->seller, $request->user(), 'order_paid', 'Đơn hàng đã thanh toán', 'Đơn hàng #'.$order->id.' đã được thanh toán mô phỏng.', '/me/orders', ['order_id' => $order->id]);

        return response()->json(['status' => 'success', 'message' => 'Thanh toán mô phỏng thành công. Người bán có thể xử lý đơn hàng.', 'data' => new OrderResource($order)]);
    }

    public function cancel(Request $request, int $id, CancelOrderAction $action, NotificationService $notifications): JsonResponse
    {
        $order = $action->execute($id, (int) $request->user()->id);
        $notifications->send($order->seller, $request->user(), 'order_cancelled', 'Đơn hàng đã bị hủy', 'Đơn hàng #'.$order->id.' đã được người mua hủy.', '/me/orders', ['order_id' => $order->id]);

        return response()->json(['status' => 'success', 'message' => 'Đã hủy đơn chưa thanh toán và mở lại sản phẩm.', 'data' => new OrderResource($order)]);
    }

    public function confirm(Request $request, int $id, AdvanceOrderAction $advance, NotificationService $notifications): JsonResponse
    {
        $order = $advance->execute($id, (int) $request->user()->id, 'confirm');
        $notifications->send($order->buyer, $request->user(), 'order_confirmed', 'Người bán đã xác nhận đơn', 'Đơn hàng #'.$order->id.' đang được người bán chuẩn bị.', '/me/orders', ['order_id' => $order->id]);

        return response()->json(['status' => 'success', 'message' => 'Đã xác nhận đơn hàng. Hãy chuẩn bị sản phẩm để gửi.', 'data' => new OrderResource($order)]);
    }

    public function ship(ShipOrderRequest $request, int $id, AdvanceOrderAction $advance, NotificationService $notifications): JsonResponse
    {
        $order = $advance->execute($id, (int) $request->user()->id, 'ship', $request->validated());
        $tracking = $order->tracking_number ? ' Mã vận đơn: '.$order->tracking_number.'.' : '';
        $notifications->send($order->buyer, $request->user(), 'order_shipped', 'Đơn hàng đang được giao', 'Đơn hàng #'.$order->id.' đã được người bán gửi đi.'.$tracking, '/me/orders', ['order_id' => $order->id]);

        return response()->json(['status' => 'success', 'message' => 'Đơn hàng đã được chuyển sang trạng thái đang giao.', 'data' => new OrderResource($order)]);
    }

    public function complete(Request $request, int $id, AdvanceOrderAction $advance, NotificationService $notifications): JsonResponse
    {
        $order = $advance->execute($id, (int) $request->user()->id, 'complete');
        $notifications->send($order->seller, $request->user(), 'order_completed', 'Đơn hàng đã hoàn tất', 'Người mua đã xác nhận nhận hàng cho đơn #'.$order->id.'.', '/me/orders', ['order_id' => $order->id]);

        return response()->json(['status' => 'success', 'message' => 'Đã xác nhận nhận hàng. Đơn hàng hoàn tất.', 'data' => new OrderResource($order)]);
    }
}
