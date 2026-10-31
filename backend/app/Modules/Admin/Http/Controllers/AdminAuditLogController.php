<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['action' => ['nullable', 'string', 'max:60'], 'q' => ['nullable', 'string', 'max:120']]);
        $query = AdminAuditLog::with('admin:id,email,username,full_name')->latest();
        if (!empty($data['action'])) $query->where('action', $data['action']);
        if (!empty($data['q'])) $query->where('summary', 'like', '%'.$data['q'].'%');
        return response()->json(['success' => true, 'message' => 'Lịch sử thao tác quản trị.', 'data' => $query->paginate(20)]);
    }
}
