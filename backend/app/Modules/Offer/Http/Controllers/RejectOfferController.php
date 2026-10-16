<?php

namespace App\Modules\Offer\Http\Controllers;

use App\Modules\Offer\Actions\RejectOfferAction;
use App\Modules\Offer\Http\Resources\OfferResource;
use App\Modules\Offer\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RejectOfferController
{
    public function __invoke(Request $request, int $id, RejectOfferAction $action): JsonResponse
    {
        $offer = Offer::findOrFail($id);

        // Authorization check
        if (! $request->user()->can('reject', $offer)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền từ chối đề nghị này.',
                'data' => null,
            ], 403);
        }

        try {
            $offer = $action->execute($id, (int) $request->user()->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Đã từ chối đề nghị.',
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
