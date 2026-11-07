# Chuẩn bị một file `.env` cho khách hàng dùng VNPay Sandbox

Khách hàng có thể tải mã nguồn, đặt file cấu hình này thành `.env` ở thư mục gốc rồi chạy `docker-start.ps1`. File `.env` chứa credentials và phải gửi riêng cho đúng khách hàng; không commit lên GitHub. File này cấu hình ứng dụng để dùng VNPay; nó không tự làm cho máy local có thể nhận callback từ Internet.

## Các giá trị cần cá nhân hóa

Tạo file từ `.env.example`, rồi điền các dòng sau:

```dotenv
APP_DEBUG=false
INITIAL_ADMIN_EMAIL=admin@khachhang.example.vn
INITIAL_ADMIN_PASSWORD=<mat-khau-rieng-dai-it-nhat-24-ky-tu>
APP_PUBLIC_DOMAIN=pay-khachhang.example.vn
COMPOSE_PROFILES=public-https
APP_URL=https://pay-khachhang.example.vn
FRONTEND_URL=https://pay-khachhang.example.vn
CORS_ALLOWED_ORIGINS=https://pay-khachhang.example.vn

VNPAY_MODE=gateway
VNPAY_TMN_CODE=<TMN_CODE Sandbox>
VNPAY_HASH_SECRET=<HASH_SECRET Sandbox>
VNPAY_PAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL=https://pay-khachhang.example.vn/api/v1/payments/vnpay/return
VNPAY_IPN_URL=https://pay-khachhang.example.vn/api/v1/payments/vnpay/ipn
VNPAY_RESULT_REDIRECT_URL=https://pay-khachhang.example.vn/payment-result
VNPAY_SANDBOX_RETURN_FALLBACK=false
```

Giữ các biến Docker khác trong `.env.example`, nhất là đặt `DB_PASSWORD` và `INITIAL_ADMIN_PASSWORD` riêng cho từng khách (admin tối thiểu 24 ký tự). Script khởi động tự tạo mật khẩu ngẫu nhiên cho database và admin nếu tự tạo `.env`; nếu chuẩn bị file gửi khách, đặt email/mật khẩu admin riêng trong file. Nếu `TMN_CODE`/`HASH_SECRET` dùng chung, chỉ chia sẻ với khách hàng được phép dùng merchant Sandbox và chuyển file qua kênh bảo mật.

## Chuẩn bị tên miền và mạng

Với mỗi máy chạy ứng dụng, hostname trong `APP_PUBLIC_DOMAIN` phải trỏ tới IP public của máy đó. Mở TCP 80/443 trên firewall; nếu máy ở sau router, chuyển tiếp hai cổng tới máy chạy Docker. Return/IPN trong `.env` phải dùng hostname đã được VNPay duyệt. Caddy được bật tự động nhờ `COMPOSE_PROFILES=public-https`, xin chứng chỉ HTTPS và chuyển request tới frontend/backend.

Nếu nhiều khách hàng chạy app ở nhiều máy, mỗi máy cần hostname riêng mà VNPay chấp nhận. Một DNS hostname chỉ trỏ tới một máy tại một thời điểm; không gửi cùng `hsmart.thatcherdev.id.vn` cho nhiều máy độc lập. Nếu chỉ có hostname đó được duyệt, hãy để DNS trỏ tới một máy chủ duy nhất chạy H-Smart và cho khách hàng dùng chung địa chỉ đó. Khách hàng chạy trên máy riêng vẫn dùng được Sandbox để thử thanh toán, nhưng VNPay không thể gọi callback vào `localhost`; cần hostname công khai và đường mạng tới đúng máy.

Sau khi `.env` hoàn chỉnh, khách hàng chạy:

```powershell
.\docker-start.ps1
```

Không cần sửa code hoặc Caddyfile. Nếu DNS/firewall chưa sẵn sàng, Caddy chưa thể xin chứng chỉ và giao dịch VNPay chưa thể quay lại ứng dụng.
