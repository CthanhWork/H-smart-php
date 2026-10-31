<?php

namespace App\Modules\Offer\Http\Controllers;

use App\Modules\Offer\Actions\AcceptOfferAction;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Offer\Http\Resources\OfferResource;
use App\Modules\Offer\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AcceptOfferController
{
    public function __invoke(Request $request, int $id, AcceptOfferAction $action, NotificationService $notifications): JsonResponse
    {
        $offer = Offer::findOrFail($id);

        // Authorization check
        if (! $request->user()->can('accept', $offer)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền chấp nhận đề nghị này.',
                'data' => null,
            ], 403);
        }

        try {
            $offer = $action->execute($id, (int) $request->user()->id);
            $notifications->send($offer->buyer, $request->user(), 'offer_accepted', 'Đề nghị giá được chấp nhận', 'Người bán đã chấp nhận đề nghị cho “'.$offer->product->title.'”. Bạn có thể tạo đơn hàng.', '/me/offers', ['offer_id' => $offer->id, 'product_id' => $offer->product_id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã chấp nhận đề nghị. Người mua sẽ nhận được thông báo.',
                'data' => new OfferResource($offer),
            ], 200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}
