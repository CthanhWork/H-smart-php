<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Order\Actions\AdvanceOrderAction;
use App\Modules\Order\Actions\CancelOrderAction;
use App\Modules\Order\Actions\CreateOrderAction;
use App\Modules\Order\Http\Requests\CreateOrderRequest;
use App\Modules\Order\Http\Requests\ShipOrderRequest;
use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use App\Modules\Offer\Models\Offer;
use App\Modules\Product\Models\Product;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController
{
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate(['offer_id' => ['required', 'integer', 'exists:offers,id']]);
        $offer = Offer::with('product:id,status')->findOrFail($data['offer_id']);
        abort_unless($offer->buyer_id === (int) $request->user()->id, 404);
        abort_unless($offer->status === Offer::STATUS_ACCEPTED && $offer->product->status === Product::STATUS_ACTIVE, 409);

        $productAmount = (int) $offer->offer_price;
        $shippingFee = max(0, (int) config('services.marketplace.shipping_fee', 0));
        $commissionRate = min(100, max(0, (int) config('services.marketplace.commission_rate', 5)));
        $platformFee = intdiv($productAmount * $commissionRate, 100);

        return response()->json(['status' => 'success', 'message' => 'Tạm tính đơn hàng.', 'data' => [
            'product_amount' => $productAmount,
            'shipping_fee' => $shippingFee,
            'total_amount' => $productAmount + $shippingFee,
            'commission_rate' => $commissionRate,
            'platform_fee' => $platformFee,
            'seller_amount' => $productAmount - $platformFee,
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = Order::with(['product.images', 'buyer', 'seller', 'offer', 'statusHistory'])
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
        $order = Order::with(['product.images', 'buyer', 'seller', 'offer', 'statusHistory'])->findOrFail($id);
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
        ], $data['payment_method'] ?? 'vnpay');
        $notifications->send($order->seller, $order->buyer, 'order_created', 'Có đơn hàng mới', 'Bạn có đơn hàng #'.$order->id.' cho sản phẩm “'.$order->product->title.'”.', '/me/orders', ['order_id' => $order->id]);

        return response()->json(['status' => 'success', 'message' => 'Đơn hàng đã được tạo và sản phẩm đang được giữ trong 30 phút.', 'data' => new OrderResource($order)], 201);
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

    public function returnReceived(Request $request, int $id, AdvanceOrderAction $advance): JsonResponse
    {
        $order = $advance->execute($id, (int) $request->user()->id, 'return_received');
        return response()->json(['status' => 'success', 'message' => 'Đã xác nhận nhận hàng hoàn. Sản phẩm được mở bán lại.', 'data' => new OrderResource($order)]);
    }
}
