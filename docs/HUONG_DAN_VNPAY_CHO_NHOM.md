# Dùng VNPay Sandbox trên các máy clone H-Smart

## Cấu hình riêng trên từng máy

Clone nhánh `develop` và chạy `docker-start.ps1` một lần để tạo `.env` riêng. Trong `.env`, cấu hình:

```dotenv
VNPAY_MODE=gateway
VNPAY_TMN_CODE=<mã merchant Sandbox>
VNPAY_HASH_SECRET=<secret Sandbox>
VNPAY_PAY_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_RETURN_URL=http://localhost:8001/api/v1/payments/vnpay/return
VNPAY_IPN_URL=
VNPAY_RESULT_REDIRECT_URL=http://localhost:5174/payment-result
VNPAY_SANDBOX_RETURN_FALLBACK=true
```

Giữ `TMN_CODE` và `HASH_SECRET` trong `.env` trên máy; không commit chúng vào GitHub. Nếu nhóm dùng chung merchant Sandbox, chia sẻ hai giá trị bằng password manager hoặc kênh bảo mật. Không dùng khóa production.

Khởi động lại backend sau khi sửa `.env`:

```powershell
docker compose up -d --build backend
```

Fallback cho phép Return có chữ ký Sandbox hợp lệ cập nhật đơn local khi VNPay không gọi được IPN `localhost`. Return URL vẫn phải được Sandbox merchant chấp nhận. Nếu tài khoản chỉ cho phép hostname đã duyệt, cấu hình hostname HTTPS công khai thay cho localhost và đặt `VNPAY_SANDBOX_RETURN_FALLBACK=false`.

## Dùng hostname đã duyệt cho máy local

Một DNS hostname chỉ trỏ tới một IP tại một thời điểm. Nếu dùng `hsmart.thatcherdev.id.vn` cho một máy local, cập nhật bản ghi A về IP public của máy đó và chuyển tiếp TCP 80/443 trên router tới máy chạy Docker. Sau đó cấu hình Return, IPN và result URL trong `.env` với hostname HTTPS, rồi bật proxy:

```powershell
docker compose --profile public-https up -d --build caddy
```

Caddy tự xin chứng chỉ HTTPS và chuyển frontend/backend qua hostname. Các máy clone khác không cần bật profile này. Không thể dùng cùng hostname để callback đồng thời vào nhiều cơ sở dữ liệu local riêng.

## Nhiều developer

Để mỗi developer chạy thanh toán độc lập, mỗi máy cần một Return URL public HTTPS mà VNPay Sandbox chấp nhận, cùng cấu hình DNS/tunnel riêng; hoặc nhóm dùng một server Sandbox chung và tất cả cùng thao tác trên instance đó. Chỉ pull cùng code không tự cung cấp credentials, DNS, IP public hay tunnel cho máy mới.

Template `.env.example` dùng `VNPAY_MODE=mock` để bản clone mới không gọi cổng thanh toán cho đến khi người phát triển cấu hình chủ động. `.env` và `backend/.env` được Git ignore.
