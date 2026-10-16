<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Http\Requests\AdminUserSuspendRequest;
use App\Modules\User\Actions\SuspendUserAction;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserSuspendController extends Controller
{
    public function __construct(
        private readonly SuspendUserAction $suspendUserAction
    ) {}

    public function __invoke(AdminUserSuspendRequest $request, int $userId): JsonResponse
    {
        $currentUser = $request->user();

        if ($currentUser->id === $userId) {
            return response()->json([
                'success' => false,
                'status' => 409,
                'message' => 'Admin cannot suspend themselves',
                'data' => null,
                'error' => [
                    'code' => 'USER_CANNOT_SUSPEND_SELF',
                    'message' => 'Admin cannot suspend themselves',
                ],
            ], 409);
        }

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
            $user = $this->suspendUserAction->execute($user, $request->validated('reason'));

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'User suspended successfully',
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
