# QA tài chính và thanh toán H-Smart — 28/11/2026

## Phạm vi

Bản triển khai và các ca kiểm thử dùng dữ liệu local/demo, VNPay Sandbox và ghi nhận thủ công của quản trị viên. Không tích hợp ngân hàng production, không yêu cầu hoặc phát sinh chuyển khoản tiền thật. VNPay Sandbox chỉ xử lý nạp ví/thanh toán đơn; phần chi trả cho seller là thao tác quản trị thủ công trong phạm vi demo/Sandbox, không phải xác nhận giao dịch ngân hàng.

## Phân loại khi rà soát

| Nhóm | Xử lý |
|---|---|
| Đường nạp tiền QA/mô phỏng trong ứng dụng | Gỡ cấu hình bật/tắt và endpoint đang chạy. Không có endpoint cộng số dư trực tiếp cho người dùng. |
| Mock thanh toán | Giữ trong `testing` để kiểm thử hồi quy; không đăng ký route trong local/demo. |
| VietQR | Gỡ luồng tạo và ghi nhận mới. Giữ dữ liệu/payment references cũ ở chế độ tra cứu; POST lịch sử trả 410. Đơn cũ còn chờ giữ nguyên trạng thái, không được mở khóa/ghi có bằng tích hợp ngân hàng. |
| Lịch sử và test fixture | Giữ giao dịch, báo cáo cũ và fixture; báo cáo lịch sử được đánh dấu không phải phạm vi hiện hành. |
| Giao diện | Dùng “Ví của tôi”, “Chi trả cho người bán”, nhãn trạng thái tiếng Việt; số dư thay đổi qua ledger đối ứng. |

## Sửa lỗi và hồi quy trong lượt này

- Sửa cấu hình Return của nạp ví VNPay: URL rỗng trước đó khiến fallback trỏ vào route backend không tồn tại; giờ mặc định về SPA `/me/wallet`. Kiểm tra local đã thấy Return redirect đúng trang SPA, cả hai route kết quả frontend trả HTTP 200. Callback IPN/Return vẫn xác minh chữ ký, số tiền, reference và chống xử lý lặp.
- Kết quả thanh toán đơn: Return đã xác minh kèm mã đơn để frontend tải chi tiết từ API; UI không hiển thị thành công/thất bại chỉ dựa vào query string và không còn nhánh báo thành công giả `mock_success`.
- Bỏ hoa hồng seller khỏi báo giá checkout dành cho buyer. Báo giá hiển thị tiền hàng, vận chuyển, tiền cọc/phần còn thu và tổng.
- Buyer COD có thể xác nhận đã nhận hàng; thao tác này không ghi nhận COD đã thu và không tự giải ngân. Đơn COD sau đó vẫn cần thao tác quản trị có căn cứ để ghi nhận thu/đối soát. Có test hồi quy cho trường hợp này.
- Khiếu nại ở trạng thái chờ đối soát tiếp tục chặn scheduler, chặn mở hồ sơ thứ hai và vẫn cho buyer bổ sung consent hoàn ví. COD qua đơn vị giao nhận không chuyển sang đủ điều kiện trước mốc 48 giờ chỉ vì buyer đã xác nhận nhận hàng.
- Seller xem trạng thái giải ngân bằng nhãn nghiệp vụ; trang khiếu nại admin không còn in thẳng trạng thái/provider kỹ thuật.
- Tạo yêu cầu VietQR mới không còn route; kiểm tra `Route::has` trong test và route listing không tìm thấy endpoint VietQR.
- Bổ sung giới hạn cho đối soát VietQR cũ: lịch sử chỉ xem được, POST trả HTTP 410; UI không còn form nhập sao kê và ca hồi quy xác nhận đơn cũ còn chờ không được ghi có hoặc đổi trạng thái.
- Scheduler hết hạn và thao tác tạo đơn từ đề nghị không mở lại sản phẩm của đơn VietQR cũ còn pending; giữ tham chiếu và trạng thái chờ để admin tra cứu hồ sơ riêng.
- Kiểm tra DB demo local ngày 28/11: không có đơn VietQR đang pending và không có bản ghi đối soát lịch sử. Dữ liệu này không kết luận về DB triển khai khác.
- Cấu hình VNPay mặc định trỏ đến `sandbox.vnpayment.vn`; backend từ chối tạo checkout và xử lý callback nếu host không phải Sandbox. Có kiểm thử khẳng định host production `pay.vnpay.vn` bị từ chối.
- Chi trả seller có kiểm thử từ chối, thất bại, kết quả chưa rõ và xử lý lặp; khoản giữ chỉ được trả lại một lần khi từ chối/thất bại đã xác định. Tranh chấp chưa rõ tiếp tục giữ tiền.
- Migration `2026_11_28_000002_hide_legacy_qa_listings` chuyển listing test cũ còn trạng thái đang bán sang ẩn và gỡ danh mục QA rỗng; giữ nguyên listing đã bán, đơn và sổ giao dịch. Migration đã chạy trên DB local.

## Kết quả kiểm tra

- Backend: `php artisan test` — **62 tests passed, 414 assertions**, gồm kiểm tra POST VietQR bị vô hiệu hóa, đơn VietQR cũ không bị scheduler mở khóa, tranh chấp chờ đối soát, COD giữ đúng mốc 48 giờ và các ca MySQL đồng thời về callback VNPay, khiếu nại cạnh tranh với scheduler và hai admin cùng xử lý một yêu cầu rút.
- Frontend: `npm run build` — thành công; Vite cảnh báo bundle JS khoảng 575 kB vượt ngưỡng 500 kB.
- Frontend: `npm run lint` — hoàn tất, có cảnh báo hook/state effect sẵn có ở nhiều trang, không có lỗi chặn lint.
- Pint: các file PHP nghiệp vụ vừa chạm — pass kiểm tra định dạng; `git diff --check` sạch.
- Route VietQR tạo thanh toán mới — không tồn tại. Lịch sử đối soát chỉ còn GET; POST bị vô hiệu hóa với HTTP 410 và có test bảo đảm đơn chờ không bị ghi có.
- Rebuild và khởi động lại Docker backend/frontend sau các thay đổi — thành công. Smoke test local: `/`, `/payment-result`, `/me/wallet` trả HTTP 200; wallet VNPay Return trả HTTP 302 về frontend theo thiết kế.
- Kiểm tra UI công khai đã đăng xuất: trang chủ không còn sản phẩm/danh mục QA; `/products` hiển thị 40 listing, qua đủ 4 trang không còn các fixture `QA`, `DEEPAUDIT`, “kiểm thử”, “thử nhiều ảnh”. Trang chính sách hiển thị phạm vi VNPay Sandbox và giải ngân cần admin xác nhận.
- Nạp ví VNPay: đã xác định và sửa lỗi 404 Return URL như trên. Lần kiểm tra gateway đầu tiên (28/11) phát hiện canonicalization chữ ký sai: mã nguồn ký giá trị chưa URL-encode. Đã sửa theo định dạng VNPay v2.1.0 (URL-encode khóa và giá trị trước HMAC-SHA512); test hồi quy xác nhận hash mã hóa đúng và không trùng cách ký giá trị thô. Yêu cầu Sandbox tiếp theo được VNPay chấp nhận đến trang “Chọn phương thức thanh toán (Test)”. Chưa hoàn tất giao dịch, chưa nhận callback hợp lệ và chưa ghi có ví trong lần kiểm tra này; không gọi callback tự ký là thanh toán Sandbox.
- Không thực hiện chuyển khoản ngân hàng thật, không tạo chứng từ ngân hàng giả. Admin chỉ ghi nhận thao tác chi trả demo/Sandbox với mã tham chiếu thử nghiệm và lịch sử xử lý.

## Chưa xác minh trực tiếp

- Chưa thao tác checkout giỏ hàng xuyên suốt trên UI đã đăng nhập hoặc kiểm tra layout mobile. Kiểm tra UI lượt này chỉ xác minh được trang công khai; các luồng seller/admin cần tài khoản đăng nhập demo và chưa được kiểm tra bằng trình duyệt.
- Giao dịch VNPay Sandbox đã tới trang chọn phương thức thanh toán sau khi sửa chữ ký; đang chờ người dùng tự hoàn tất hoặc hủy trên trang Sandbox. Chưa xác minh kết quả callback/Return, ghi có sổ ví hoặc trang kết quả sau giao dịch.
- Ca MySQL hai admin xử lý cùng yêu cầu rút hiện đã chạy: một kết quả thành công, request còn lại bị từ chối; chỉ có một bút toán trừ khoản giữ và một audit event thành công. Test từ chối và thất bại riêng cũng xác nhận hoàn lại khoản giữ đúng một lần.
- Chưa xác minh UI trực tiếp cho tất cả màn buyer/seller/admin ở desktop và mobile sau build cuối.

Không chứa số tài khoản ngân hàng, mã bí mật merchant hoặc thông tin đăng nhập.
