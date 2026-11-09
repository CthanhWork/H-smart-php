<?php

namespace App\Modules\Offer\Http\Controllers;

use App\Modules\Offer\Http\Resources\OfferResource;
use App\Modules\Offer\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfferController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'filter' => ['sometimes', Rule::in(['action', 'accepted', 'closed'])],
        ]);
        $userId = $request->user()->id;

        // Get offers where user is buyer or seller
        $offers = Offer::with(['product.images', 'buyer', 'seller'])
            ->where(function ($query) use ($userId) {
                $query->where('buyer_id', $userId)
                    ->orWhere('seller_id', $userId);
            })
            ->when(($validated['filter'] ?? null) === 'action', function ($query) use ($userId): void {
                $query->where(function ($actionable) use ($userId): void {
                    $actionable->where('status', 'pending')
                        ->orWhere(fn ($buyerOffer) => $buyerOffer->where('buyer_id', $userId)->where('status', 'accepted'));
                });
            })
            ->when(($validated['filter'] ?? null) === 'accepted', fn ($query) => $query->whereIn('status', ['accepted', 'ordered']))
            ->when(($validated['filter'] ?? null) === 'closed', fn ($query) => $query->whereIn('status', ['rejected', 'cancelled', 'expired']))
            ->orderBy('created_at', 'desc')
            ->paginate(20, ['*'], 'page', (int) ($validated['page'] ?? 1));

        return response()->json([
            'status' => 'success',
            'message' => 'Danh sách đề nghị.',
            'data' => [
                'content' => OfferResource::collection($offers->items()),
                'pageNo' => $offers->currentPage() - 1,
                'pageSize' => $offers->perPage(),
                'totalElements' => $offers->total(),
                'totalPages' => $offers->lastPage(),
                'last' => $offers->currentPage() >= $offers->lastPage(),
            ],
        ], 200);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $offer = Offer::with(['product.images', 'buyer', 'seller'])->findOrFail($id);

        // Authorization check
        if (! $request->user()->can('view', $offer)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền xem đề nghị này.',
                'data' => null,
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Chi tiết đề nghị.',
            'data' => new OfferResource($offer),
        ], 200);
    }
}
