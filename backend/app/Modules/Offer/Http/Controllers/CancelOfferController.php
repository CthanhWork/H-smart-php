<?php

namespace App\Modules\Offer\Http\Controllers;

use App\Modules\Offer\Actions\CancelOfferAction;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Offer\Http\Resources\OfferResource;
use App\Modules\Offer\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CancelOfferController
{
    public function __invoke(Request $request, int $id, CancelOfferAction $action, NotificationService $notifications): JsonResponse
    {
        $offer = Offer::findOrFail($id);

        // Authorization check
        if (! $request->user()->can('cancel', $offer)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền hủy đề nghị này.',
                'data' => null,
            ], 403);
        }

        try {
            $offer = $action->execute($id, (int) $request->user()->id);
            $notifications->send($offer->seller, $request->user(), 'offer_cancelled', 'Đề nghị giá đã được hủy', $notifications->name($request->user()).' đã hủy đề nghị cho “'.$offer->product->title.'”.', '/me/offers', ['offer_id' => $offer->id, 'product_id' => $offer->product_id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã hủy đề nghị.',
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
