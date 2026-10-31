<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AdminReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportSubmissionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'string', Rule::in(['product', 'user'])],
            'target_id' => ['required', 'integer', 'min:1', Rule::exists($request->input('target_type') === 'user' ? 'users' : 'products', 'id')],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['target_type'] === 'user' && (int) $data['target_id'] === (int) $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Không thể báo cáo tài khoản của chính bạn.', 'data' => null], 422);
        }
        $existing = AdminReport::where('reporter_id', $request->user()->id)->where('target_type', $data['target_type'])
            ->where('target_id', $data['target_id'])->where('status', 'pending')->exists();
        if ($existing) return response()->json(['success' => false, 'message' => 'Bạn đã gửi báo cáo đang chờ xử lý cho nội dung này.', 'data' => null], 409);

        $report = AdminReport::create($data + ['reporter_id' => $request->user()->id]);
        return response()->json(['success' => true, 'message' => 'Đã gửi báo cáo.', 'data' => $report], 201);
    }
}
