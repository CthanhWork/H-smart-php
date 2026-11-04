<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>H-Smart · Thanh toán mô phỏng</title>
    <style>
        body{margin:0;padding:24px;background:#f1f5f9;color:#0f172a;font:16px/1.6 system-ui,sans-serif}main{max-width:520px;margin:8vh auto;background:white;padding:32px;border-radius:20px;box-shadow:0 10px 35px #0f172a12}h1{font-size:25px;line-height:1.3}.notice{background:#fff7ed;color:#9a3412;padding:16px;border-radius:12px}dl{margin:24px 0}dt{color:#64748b}dd{margin:0 0 14px;overflow-wrap:anywhere}.amount{font-size:28px;font-weight:700}button{display:block;width:100%;padding:13px;margin-top:12px;border:1px solid #cbd5e1;border-radius:10px;background:white;font:inherit;cursor:pointer}button:first-of-type{background:#ea580c;color:white;border-color:#ea580c}button:focus-visible{outline:3px solid #2563eb;outline-offset:3px}
    </style>
</head>
<body><main>
    <h1>Thanh toán VNPay mô phỏng</h1>
    <p class="notice">Chế độ thử nghiệm của H-Smart. Không kết nối VNPay, không thu tiền và không cần nhập thông tin ngân hàng.</p>
    <dl>
        <dt>Đơn hàng</dt><dd>#{{ $order->id }}</dd>
        <dt>Số tiền mô phỏng</dt><dd class="amount">{{ number_format($order->total_amount, 0, ',', '.') }} đ</dd>
        <dt>Mã giao dịch</dt><dd>{{ $order->payment_txn_ref }}</dd>
    </dl>
    @if ($order->status === 'pending_payment' && $order->payment_status === 'pending' && $order->expires_at->isFuture())
        <form method="post" action="{{ $action }}">
            @csrf
            <button name="outcome" value="success">Mô phỏng thanh toán thành công</button>
            <button name="outcome" value="failed">Mô phỏng thanh toán thất bại</button>
            <button name="outcome" value="cancel">Hủy thanh toán mô phỏng</button>
        </form>
    @else
        <p>Giao dịch đã được xử lý hoặc hết hạn. Hãy quay lại danh sách đơn hàng để kiểm tra hoặc thử lại.</p>
    @endif
</main></body></html>
