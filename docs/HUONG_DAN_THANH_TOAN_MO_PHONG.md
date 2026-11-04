# Thanh toán VNPay mô phỏng, không cần đăng ký

Chế độ này do H-Smart tự mô phỏng, không gọi VNPay Sandbox và không chuyển tiền thật. Không cần mã merchant, khóa VNPay hoặc tài khoản ngân hàng.

## Chạy với Docker

Trong file `.env` ở thư mục gốc thêm:

```dotenv
VNPAY_MODE=mock
VNPAY_RESULT_REDIRECT_URL=http://localhost:5174/payment-result
```

Áp dụng mã nguồn và cấu hình:

```powershell
docker compose up -d --build backend frontend scheduler
```

Docker local mặc định dùng `mock`. Nếu đã có `VNPAY_MODE=gateway` trong `.env`, đổi thành `mock`. Các giá trị merchant cũ không cần xóa.

Mở http://localhost:5174, đăng nhập tài khoản người mua, tạo đơn từ đề nghị đã được chấp nhận, vào đơn hàng rồi chọn **Thanh toán qua VNPay**. Trang mô phỏng hiển thị số tiền và ba lựa chọn:

- Thành công: đơn chuyển sang đã thanh toán, sản phẩm chuyển sang đã bán.
- Thất bại hoặc hủy thanh toán: đơn vẫn chờ thanh toán và có thể thanh toán lại trước khi hết hạn giữ chỗ. Hủy thanh toán không đồng nghĩa hủy đơn.

Không cần nhập số thẻ hoặc OTP. Giao dịch được ghi với provider `vnpay_mock` và hiển thị nhãn **VNPay mô phỏng** trong đơn hàng. URL có chữ ký, hết hạn theo đơn; gửi lại kết quả thành công không ghi nhận thanh toán thêm lần nữa. Không chia sẻ URL giao dịch vì người có URL có thể chọn kết quả mô phỏng.

## Chạy backend trực tiếp

Đặt `APP_ENV=local`, `VNPAY_MODE=mock` trong `backend/.env`, đặt `APP_URL` đúng địa chỉ backend và `VNPAY_RESULT_REDIRECT_URL` đúng địa chỉ frontend, rồi chạy `php artisan config:clear`.

## Kết nối VNPay khi có thông tin đăng ký

Đổi `VNPAY_MODE=gateway` và cấu hình merchant, khóa ký, URL thanh toán, Return, IPN theo [hướng dẫn Sandbox](HUONG_DAN_THANH_TOAN_VNPAY_SANDBOX.md). Cả Sandbox và production đều cần thông tin kết nối được VNPay cấp. Chế độ mô phỏng bị chặn ngoài `local` và `testing`, kể cả khi đặt nhầm `VNPAY_MODE=mock` trên production.

## Kiểm thử tự động

```powershell
docker compose exec -T backend php vendor/bin/phpunit --bootstrap vendor/autoload.php tests/Feature/VnPayMockTest.php
```

Kiểm thử dùng SQLite trong bộ nhớ, không sửa dữ liệu MySQL đang chạy.
