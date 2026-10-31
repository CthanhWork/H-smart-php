<?php

namespace App\Modules\User\Http\Controllers;

use App\Modules\User\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Thông tin hồ sơ.',
            'data' => $this->profileData($request->user()),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Đã cập nhật hồ sơ và địa chỉ nhận hàng.',
            'data' => $this->profileData($user->refresh()),
        ]);
    }

    private function profileData($user): array
    {
        return $user->only(['id', 'email', 'full_name', 'phone_number', 'shipping_address']);
    }
}
