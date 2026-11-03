# Hướng dẫn chạy thanh toán VNPay Sandbox

Tài liệu này hướng dẫn tải H-Smart từ GitHub, cấu hình thông tin merchant Sandbox và chạy một giao dịch thử trên máy cá nhân. Sandbox chỉ dùng để kiểm thử; giao dịch không phải thanh toán tiền thật.

## 1. Lấy mã nguồn và khởi động H-Smart

Cài Docker Desktop, bật Docker Engine, rồi chạy PowerShell:

```powershell
git clone https://github.com/CthanhWork/H-smart-php.git
cd H-smart-php
git checkout codex/vnpay-sandbox-demo
```

Nếu chức năng VNPay đã được gộp vào nhánh chính, có thể bỏ lệnh `git checkout`.

Khởi động ứng dụng. Lần đầu, script sẽ tạo file `.env` riêng trên máy này từ `.env.example`:

```powershell
.\docker-start.ps1
```

> Lần chạy đầu có thể mất vài phút để Docker tải image và build. Khi hoàn tất, mở giao diện tại <http://localhost:5174>. Hộp thư xác thực email dùng <http://localhost:8026>; backend API dùng <http://localhost:8001>.

Nếu ứng dụng đã chạy, sau khi sửa `.env` hãy áp dụng cấu hình mới cho backend:

```powershell
docker compose up -d --build backend
docker compose logs -f backend
```

## 2. Cấu hình thông tin Sandbox

Lấy `TMN_CODE` và `HASH_SECRET` từ tài khoản merchant Sandbox của người kiểm thử. Không dùng thông tin production. Không đưa mã merchant hoặc secret vào commit, ảnh chụp màn hình, báo cáo công khai hay tin nhắn gửi lên GitHub.

Sửa các dòng VNPay trong file `.env` ở thư mục gốc. Nếu vừa chạy lần đầu theo mục 1, file này đã được tạo tự động. Dùng mẫu sau:

```dotenv
VNPAY_TMN_CODE=TMN_CODE_SANDBOX_CUA_BAN
VNPAY_HASH_SECRET=HASH_SECRET_SANDBOX_CUA_BAN
VNPAY_PAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL=http://localhost:8001/api/v1/payments/vnpay/return
VNPAY_IPN_URL=
VNPAY_RESULT_REDIRECT_URL=http://localhost:5174/payment-result
VNPAY_SANDBOX_RETURN_FALLBACK=true
```

Giữ nguyên URL Sandbox như trên. `VNPAY_SANDBOX_RETURN_FALLBACK=true` cho phép backend local xử lý callback Return có chữ ký hợp lệ trong môi trường Sandbox, vì máy chủ VNPay không thể gọi địa chỉ `localhost`. Mã nguồn chỉ bật cách này khi host cổng thanh toán là `sandbox.vnpayment.vn`; không dùng fallback cho production.

Địa chỉ Return phải trùng với địa chỉ đã đăng ký trong cấu hình merchant VNPay. Nếu VNPay không cho đăng ký `localhost`, dùng cách tạo HTTPS tunnel ở mục 3. Không điền `localhost` làm IPN URL trên VNPay rồi kỳ vọng máy chủ VNPay truy cập được máy cá nhân.

## 3. (Khuyến nghị) Nhận IPN thật qua HTTPS tunnel

Để kiểm tra máy chủ VNPay gửi thông báo IPN về ứng dụng, backend cần có địa chỉ HTTPS công khai. Có thể dùng ngrok hoặc một tunnel HTTPS tương đương trong lúc phát triển. Ví dụ, sau khi cài và đăng nhập ngrok:

```powershell
ngrok http 8001
```

Ngrok sẽ hiển thị một URL HTTPS, ví dụ `https://abc123.ngrok-free.app`. Giữ cửa sổ tunnel chạy và dùng chính xác tên miền hiện tại trong các cấu hình sau:

```dotenv
VNPAY_RETURN_URL=https://abc123.ngrok-free.app/api/v1/payments/vnpay/return
VNPAY_IPN_URL=https://abc123.ngrok-free.app/api/v1/payments/vnpay/ipn
VNPAY_RESULT_REDIRECT_URL=http://localhost:5174/payment-result
VNPAY_SANDBOX_RETURN_FALLBACK=false
```

Cập nhật cả Return URL và IPN URL trong cổng quản lý merchant Sandbox nếu cổng đó yêu cầu khai báo callback. Sau đó khởi động lại backend để nhận `.env` mới. URL ngrok miễn phí thường đổi khi tạo tunnel mới; mỗi lần đổi cần cập nhật `.env`, cấu hình merchant và khởi động lại backend.

Với tunnel đang hoạt động, trình duyệt quay về backend qua URL HTTPS và máy chủ VNPay có thể gọi IPN. Nếu chỉ thử giao diện local bằng fallback, để `VNPAY_IPN_URL` trống và `VNPAY_SANDBOX_RETURN_FALLBACK=true` như mục 2.

## 4. Tạo đơn và thanh toán thử

Đăng ký hai tài khoản riêng và xác thực email trong Mailpit tại <http://localhost:8026>:

1. Tài khoản người bán đăng tin một sản phẩm.
2. Tài khoản người mua gửi đề nghị mua sản phẩm.
3. Người bán vào **Đề nghị**, chấp nhận đề nghị đó.
4. Người mua vào đề nghị đã được chấp nhận, chọn **Tạo đơn hàng**, nhập thông tin nhận hàng.
5. Trong **Đơn hàng**, chọn **Thanh toán qua VNPay**. H-Smart tạo URL đã ký và chuyển trình duyệt sang cổng Sandbox.
6. Dùng thông tin thẻ thử do VNPay cung cấp cho merchant Sandbox. Hoàn tất các bước tại cổng; sau khi quay lại H-Smart, kiểm tra trạng thái đơn trong **Đơn hàng**.

Đơn chờ thanh toán được giữ trong 30 phút. Nếu hết hạn, tạo lại đơn từ đề nghị đã chấp nhận.

## 5. Cách hiểu kết quả và xử lý lỗi thường gặp

- **“Thanh toán VNPay chưa được cấu hình”**: kiểm tra `VNPAY_TMN_CODE` và `VNPAY_HASH_SECRET` trong `.env`, sau đó chạy lại `docker compose up -d --build backend`.
- **VNPay báo mã 71 / “The terminal (website) not approved”**: merchant Sandbox chưa được VNPay duyệt hoặc cấu hình merchant chưa đúng. Việc đổi Return URL, bật fallback hay khởi động lại local không thể thay thế bước duyệt merchant; liên hệ VNPay hoặc quản trị tài khoản Sandbox.
- **Đã thanh toán ở Sandbox nhưng đơn chưa thành “Đã thanh toán” khi dùng tunnel**: kiểm tra tunnel còn chạy, `VNPAY_IPN_URL` là HTTPS công khai và đúng path `/api/v1/payments/vnpay/ipn`, URL callback trong merchant trùng cấu hình, rồi xem `docker compose logs -f backend`.
- **Đơn vẫn đang xử lý sau khi trình duyệt quay về**: với cấu hình IPN, trang Return chỉ báo kết quả trình duyệt; trạng thái đơn được xác nhận từ IPN. Tải lại trang đơn sau ít giây và kiểm tra log backend.
- **Trang kết quả báo thất bại hoặc không xác minh được giao dịch**: kiểm tra `TMN_CODE`, `HASH_SECRET`, URL Sandbox, thời hạn đơn và cấu hình callback. Không sửa trạng thái thanh toán trực tiếp trong database để làm cho bài thử có vẻ thành công.

Trong lần kiểm tra của dự án ngày 02/11/2026, 20 ca xử lý callback có chữ ký hợp lệ và dữ liệu được rollback đều đạt. Lượt gọi checkout thực tế tới Sandbox bị mã 71 vì merchant chưa được duyệt, nên chưa hoàn tất giao dịch đầu cuối qua cổng ngân hàng. Đây là giới hạn phê duyệt merchant, không phải kết quả cho thấy thanh toán tiền thật đã hoạt động.

## 6. Trước khi triển khai production

Không dùng URL Sandbox, thông tin Sandbox hoặc `VNPAY_SANDBOX_RETURN_FALLBACK=true` cho production. Môi trường production cần thông tin merchant production, tên miền HTTPS công khai, cấu hình Return/IPN đã đăng ký với VNPay và `VNPAY_SANDBOX_RETURN_FALLBACK=false`. Chỉ chuyển sang nhận thanh toán thật sau khi merchant production được VNPay cấp quyền và luồng IPN đã được cấu hình.
