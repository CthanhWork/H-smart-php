<?php

namespace App\Modules\Offer\Http\Controllers;

use App\Modules\Offer\Actions\CreateOfferAction;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Offer\Http\Requests\CreateOfferRequest;
use App\Modules\Offer\Http\Resources\OfferResource;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class CreateOfferController
{
    public function __invoke(CreateOfferRequest $request, CreateOfferAction $action, NotificationService $notifications): JsonResponse
    {
        $data = $request->validated();

        try {
            $offer = $action->execute(
                (int) $request->user()->id,
                (int) $data['product_id'],
                (int) $data['offer_price']
            );
            $notifications->send($offer->seller, $offer->buyer, 'offer_received', 'Có đề nghị giá mới', $notifications->name($offer->buyer).' đã gửi đề nghị cho “'.$offer->product->title.'”.', '/me/offers', ['offer_id' => $offer->id, 'product_id' => $offer->product_id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã gửi đề nghị mua. Người bán sẽ phản hồi trong vòng 24 giờ.',
                'data' => new OfferResource($offer),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }
}
