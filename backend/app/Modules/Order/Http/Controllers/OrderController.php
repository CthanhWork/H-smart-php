<?php

namespace App\Modules\Order\Http\Controllers;

use App\Modules\Admin\Services\MarketplaceSettings;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Offer\Models\Offer;
use App\Modules\Order\Actions\AdvanceOrderAction;
use App\Modules\Order\Actions\CancelOrderAction;
use App\Modules\Order\Actions\CreateListedOrderAction;
use App\Modules\Order\Actions\CreateOrderAction;
use App\Modules\Order\Http\Requests\CreateOrderRequest;
use App\Modules\Order\Http\Requests\ShipOrderRequest;
use App\Modules\Order\Http\Resources\OrderResource;
use App\Modules\Order\Models\Order;
use App\Modules\Product\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController
{
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'offer_id' => ['required_without:product_id', 'prohibits:product_id', 'integer', 'exists:offers,id'],
            'product_id' => ['required_without:offer_id', 'prohibits:offer_id', 'integer', 'exists:products,id'],
        ]);
        if (isset($data['product_id'])) {
            $product = Product::findOrFail($data['product_id']);
            abort_unless($product->status === Product::STATUS_ACTIVE && $product->seller_id !== (int) $request->user()->id, 409);
            $productAmount = (int) $product->price;
        } else {
            $offer = Offer::with('product:id,status')->findOrFail($data['offer_id']);
            abort_unless($offer->buyer_id === (int) $request->user()->id, 404);
            abort_unless($offer->status === Offer::STATUS_ACCEPTED && $offer->product->status === Product::STATUS_ACTIVE, 409);
            $productAmount = (int) $offer->offer_price;
        }
        $settings = app(MarketplaceSettings::class);
        $shippingFee = max(0, $settings->get('shipping_fee'));
        $commissionRate = min(100, max(0, $settings->get('commission_rate')));
        $feeBase = $productAmount + $shippingFee;
        $platformFee = intdiv($feeBase * $commissionRate, 100);
        abort_if($platformFee > $productAmount, 409, 'Giá sản phẩm thấp hơn mức cần thiết để trừ phí sàn; không thể tạo đơn này.');

        return response()->json(['status' => 'success', 'message' => 'Tạm tính đơn hàng.', 'data' => [
            'product_amount' => $productAmount,
            'shipping_fee' => $shippingFee,
            'total_amount' => $productAmount + $shippingFee,
            'cod_deposit_percent' => $settings->get('cod_deposit_percent'),
            'cod_deposit_amount' => (int) ceil($productAmount * $settings->get('cod_deposit_percent') / 100),
            'cod_remaining_amount' => $productAmount - (int) ceil($productAmount * $settings->get('cod_deposit_percent') / 100),
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = Order::with(['product.images', 'buyer', 'seller', 'offer', 'statusHistory', 'disputes.events'])
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
        $order = Order::with(['product.images', 'buyer', 'seller', 'offer', 'statusHistory', 'disputes.events'])->findOrFail($id);
        abort_unless(in_array($request->user()->id, [$order->buyer_id, $order->seller_id], true), 404);

        return response()->json(['status' => 'success', 'message' => 'Chi tiết đơn hàng.', 'data' => new OrderResource($order)]);
    }

    public function store(CreateOrderRequest $request, CreateOrderAction $action, CreateListedOrderAction $listedAction, NotificationService $notifications, MarketplaceSettings $settings): JsonResponse
    {
        $data = $request->validated();
        $address = [
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'],
            'address' => $data['address'],
        ];
        $order = isset($data['product_id'])
            ? $listedAction->execute((int) $request->user()->id, (int) $data['product_id'], (int) $data['expected_price'], (string) $data['idempotency_key'], $address, $data['payment_method'] ?? 'vnpay', $settings, [
                'shipping_fee' => (int) $data['expected_shipping_fee'], 'cod_deposit_percent' => (int) $data['expected_cod_deposit_percent'],
            ])
            : $action->execute((int) $request->user()->id, (int) $data['offer_id'], $address, $data['payment_method'] ?? 'vnpay', $settings, [
                'shipping_fee' => (int) $data['expected_shipping_fee'], 'cod_deposit_percent' => (int) $data['expected_cod_deposit_percent'],
            ]);
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
