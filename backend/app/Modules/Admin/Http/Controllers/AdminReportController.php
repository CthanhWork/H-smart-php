<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AdminReport;
use App\Modules\Admin\Services\AdminAuditLogger;
use App\Modules\Product\Actions\HideProductAction;
use App\Modules\Product\Models\Product;
use App\Modules\User\Actions\SuspendUserAction;
use App\Modules\User\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', 'in:pending,actioned,dismissed'], 'q' => ['nullable', 'string', 'max:120']]);
        $query = AdminReport::with(['reporter:id,email,username,full_name', 'product:id,title,seller_id,status,description,price,condition,category_id,created_at', 'product.images', 'product.category', 'reportedUser:id,email,username,full_name,status'])->latest();
        if (!empty($data['status'])) $query->where('status', $data['status']);
        if (!empty($data['q'])) $query->where(fn ($q) => $q->where('reason', 'like', '%'.$data['q'].'%')->orWhereHas('reporter', fn ($u) => $u->where('email', 'like', '%'.$data['q'].'%')));
        return response()->json(['success' => true, 'message' => 'Danh sách báo cáo.', 'data' => $query->paginate(20)]);
    }

    public function resolve(Request $request, int $id, HideProductAction $hideProduct, SuspendUserAction $suspendUser, AdminAuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:hide_product,suspend_user,dismiss']]);
        $report = AdminReport::findOrFail($id);
        if ($report->status !== 'pending') return response()->json(['success' => false, 'message' => 'Báo cáo đã được xử lý.', 'data' => null], 409);
        try {
            DB::transaction(function () use ($request, $data, $report, $hideProduct, $suspendUser, $audit): void {
                if ($data['action'] === 'hide_product') {
                    abort_unless($report->target_type === 'product', 422, 'Báo cáo này không nhắm tới sản phẩm.');
                    $target = Product::findOrFail($report->target_id);
                    $hideProduct->execute($target, 'Báo cáo #'.$report->id.': '.$report->reason);
                    $audit->record($request->user(), 'product_hidden', 'product', $target->id, 'Ẩn sản phẩm theo báo cáo #'.$report->id, ['report_id' => $report->id, 'reason' => $report->reason]);
                    $status = 'actioned';
                } elseif ($data['action'] === 'suspend_user') {
                    abort_unless($report->target_type === 'user', 422, 'Báo cáo này không nhắm tới tài khoản.');
                    $target = User::findOrFail($report->target_id);
                    abort_if($target->id === $request->user()->id || $target->role === 'admin', 422, 'Không thể khóa tài khoản quản trị viên.');
                    $suspendUser->execute($target, 'Báo cáo #'.$report->id.': '.$report->reason);
                    $audit->record($request->user(), 'user_suspended', 'user', $target->id, 'Khóa tài khoản theo báo cáo #'.$report->id, ['report_id' => $report->id, 'reason' => $report->reason]);
                    $status = 'actioned';
                } else {
                    $status = 'dismissed';
                }
                $report->update(['status' => $status, 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 409);
        }
        return response()->json(['success' => true, 'message' => 'Đã cập nhật báo cáo.', 'data' => $report->fresh()]);
    }
}
