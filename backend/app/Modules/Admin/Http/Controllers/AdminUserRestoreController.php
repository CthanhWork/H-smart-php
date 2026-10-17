<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Actions\RestoreUserAction;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Modules\Admin\Services\AdminAuditLogger;

class AdminUserRestoreController extends Controller
{
    public function __construct(
        private readonly RestoreUserAction $restoreUserAction
    ) {}

    public function __invoke(Request $request, int $userId, AdminAuditLogger $audit): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'User not found',
                'data' => null,
            ], 404);
        }

        try {
            $user = $this->restoreUserAction->execute($user);
            $audit->record($request->user(), 'user_restored', 'user', $user->id, 'Mở khóa tài khoản '.$user->email);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'User restored successfully',
                'data' => ['user' => $user],
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'status' => 409,
                'message' => $e->getMessage(),
                'data' => null,
            ], 409);
        }
    }
}
