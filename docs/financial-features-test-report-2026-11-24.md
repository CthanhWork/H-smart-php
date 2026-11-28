# Báo cáo kiểm thử bổ sung: doanh thu, ví và khiếu nại

Ngày kiểm tra: 24/11/2026. Phạm vi là bốn yêu cầu doanh thu, nạp VNPay Sandbox, ẩn phí theo vai trò của từng đơn và xử lý khiếu nại. Báo cáo này bổ sung, không thay thế [báo cáo tích hợp ngày 22/11/2026](integration-test-report-followup-2026-11-22.md).

> **Bản ghi lịch sử, không phải trạng thái hiện tại.** Một số dòng bên dưới ghi lại nút nạp QA và số dư mô phỏng từng có trong phiên bản ngày 24/11. Luồng nạp trực tiếp đó đã bị gỡ khỏi ứng dụng; mock chỉ còn trong test tự động. Báo cáo này không chứng minh giao dịch VNPay Sandbox end-to-end hay chuyển khoản thật. Từ 28/11, luồng ứng dụng quy định nạp/thanh toán qua VNPay Sandbox và admin ghi nhận chi trả thủ công trên dữ liệu thử nghiệm.

## Kết quả theo yêu cầu

### 1. Doanh thu người bán và quản trị viên

- Người bán gọi `GET /api/v1/me/wallet/revenue`; seller ID lấy từ phiên đăng nhập và mọi truy vấn đơn/bút toán đều được giới hạn theo ID đó.
- Quản trị viên gọi `GET /api/v1/admin/revenue`; route có cả middleware xác thực JWT và kiểm tra vai trò admin.
- Có lọc tháng này, tháng trước, khoảng ngày (tối đa 366 ngày), phân trang chi tiết và chuỗi biểu đồ theo ngày hoặc tháng.
- Ranh giới ngày dùng Asia/Ho_Chi_Minh, đổi thành khoảng UTC nửa mở `[đầu ngày, đầu ngày kế tiếp)`. Test xác nhận 01/11/2026 bắt đầu từ `2026-10-31 17:00 UTC` và kết thúc tại `2026-11-01 17:00 UTC`.
- Doanh thu bán hàng của người bán dùng tiền hàng đơn hoàn tất trừ snapshot hoa hồng và phần người bán bị đảo theo bút toán hoàn. Phí giao hàng không cộng vào tiền hàng; COD thu trực tiếp được tính theo thời điểm xác nhận thu và không ghi thêm vào ví. Số đang chờ giải ngân là số dư giữ hiện tại, tách khỏi tiền đã ghi có.
- Báo cáo admin tính GMV từ tiền hàng đã thanh toán theo `payment_paid_at`; doanh thu hoa hồng, phí đăng tin, VIP/đẩy tin và đảo doanh thu lấy từ các dòng `platform_revenue` đã ghi sổ theo `posted_at`. Nạp ví, rút ví, số dư người dùng và tiền đang giữ không được coi là doanh thu sàn.
- Fixture kiểm thử có một đơn hoàn tất của seller đang xem, một đơn của seller khác, bút toán hoa hồng, phí đăng tin, phí khuyến mại và hoàn tiền. Kết quả kiểm tra seller chỉ gồm đơn của mình; net seller bằng 0 sau khi hoàn toàn bộ phần 98.000đ; doanh thu sàn thuần là 20.000đ sau đảo hoa hồng.

### 2. Nạp ví qua VNPay Sandbox

- Yêu cầu nạp được lưu riêng ở `wallet_vnpay_deposits`, có user, số tiền, mã `HWS...`, idempotency key, trạng thái, hạn thanh toán và tham chiếu giao dịch ví. Không tạo đơn hàng giả.
- API yêu cầu số tiền từ 10.000đ đến 10.000.000đ. Số tiền ghi có lấy từ yêu cầu đã lưu, không lấy lại từ callback.
- Callback yêu cầu chữ ký đúng, merchant đúng, tiền tệ VND, đúng loại tham chiếu, đúng số tiền và cả `vnp_ResponseCode=00` lẫn `vnp_TransactionStatus=00`. Bút toán đối ứng đi qua `WalletLedger`; callback lặp chỉ tạo một wallet transaction.
- IPN merchant dùng chung có thể trỏ tới `/api/v1/payments/vnpay/ipn`, phân luồng theo namespace tham chiếu. `HWS...` chỉ vào bộ xử lý nạp ví; tham chiếu đơn hàng chỉ vào bộ xử lý đơn hàng. Các endpoint IPN/Return riêng cho ví cũng tồn tại. Không chấp nhận tham chiếu ví trong bộ xử lý đơn hoặc ngược lại.
- Callback thành công đến sau khi yêu cầu hết hạn, thất bại hoặc bị hủy chuyển thành `reconciliation_required`, không tự ghi có. Callback sai số tiền có lưu trạng thái đối soát; sai chữ ký không thay đổi yêu cầu.
- Trang ví phân biệt **Nạp qua VNPay Sandbox** với **Nạp mô phỏng QA**, hiển thị lịch sử và đọc kết quả từ API sau khi Return. URL query không tự xác nhận thanh toán. Simulation bị giới hạn ở local/testing.
- Đã kiểm tra URL/callback và callback đồng thời ký bằng khóa test trên MySQL. **Chưa hoàn tất giao dịch trên trang thanh toán VNPay Sandbox**; kết quả callback local không được báo là giao dịch qua nhà cung cấp.

### 3. Quyền xem phí theo từng đơn

- API order chỉ trả `seller_financials` cho người bán của chính đơn đó hoặc admin. Buyer không nhận commission rate, platform fee, seller net hay chi tiết chi trả nội bộ.
- Quote API chỉ trả tiền hàng, phí giao hàng, tổng thanh toán, tiền cọc và phần còn lại; client không cần gửi tỷ lệ hoa hồng để giữ giá. Khi trường kỳ vọng này được gửi từ client cũ, backend vẫn kiểm tra.
- Chatbot chỉ nêu hoa hồng, tiền người bán dự kiến nhận và trạng thái đối soát COD khi người đang hỏi là seller của đơn đó. Buyer vẫn nhận thông tin thanh toán liên quan đến mình.
- Test cùng một tài khoản ở vai buyer trong đơn A và seller trong đơn B: trường tài chính vắng ở đơn A, có ở đơn B; admin nhìn thấy ở cả hai. Build TypeScript qua và các trường tài chính phụ thuộc quyền đã là tùy chọn.

### 4. Khiếu nại quản trị

- Trước khi tạo fixture QA, MySQL local có **0** khiếu nại `open` hoặc `reconciliation_pending`; do đó không có hồ sơ pending thực tế để tái hiện báo cáo ban đầu. Việc kiểm tra mã nguồn không tìm thấy lệch tên route, payload hay resolution enum: frontend gửi `{resolution,note}` đúng với controller.
- Danh sách mặc định lọc khiếu nại đang xử lý; có thêm bộ lọc lịch sử đã giải quyết và tất cả. Vì vậy hồ sơ đã đóng không còn biến mất khỏi trang tra cứu. Chi tiết hiển thị riêng phương thức thanh toán, nhà cung cấp và trạng thái đối soát COD; lỗi từ API vẫn được hiển thị.
- Các chốt an toàn tiếp tục ở backend: không hoàn/giải ngân khi chưa xác nhận thu đủ; COD cần đã thu trực tiếp hoặc có mốc carrier settlement; hoàn vào ví cần consent của buyer; đơn đã trả không thể đổi quyết định. Quyết định admin có sự kiện lịch sử, audit log và thông báo cho hai bên.
- Fixture QA chứng minh quyết định chờ đối soát giữ tiền; đơn chưa thanh toán không thể giải ngân; quyết định hợp lệ chuyển sang đủ điều kiện, scheduler ghi có đúng một lần.
- MySQL race test chạy hai tiến trình cho quyết định chờ đối soát và scheduler. Kết quả: scheduler ghi 0 giao dịch, tranh chấp ở `reconciliation_pending`, tiền seller vẫn ở `pending_release` và không có `ORDER_RELEASE`.
- Kết luận về nguyên nhân thực tế được giới hạn: dữ liệu local rỗng giải thích vì sao trang không có hồ sơ để thao tác, nhưng không chứng minh nguyên nhân của một vụ việc ngoài local. Không thấy bằng chứng API action hoặc payload bị sai.

## Tệp chính

- Báo cáo người bán/admin: `SellerRevenueController`, `AdminRevenueController`, `RevenuePeriod`, route ví và route admin.
- Nạp ví: migration `2026_11_24_000001_create_wallet_vnpay_deposits.php`, model, `WalletVnPayService`, controller, các route callback, scheduler và `WalletPage`.
- Quyền phí: `OrderResource`, quote/controller/action tạo đơn, trang đơn hàng và phản hồi tra cứu đơn chatbot.
- Khiếu nại: `AdminDisputeController`, `AdminDisputePage`, điều hướng quản trị và bộ lọc lịch sử.
- Hướng dẫn Sandbox được cập nhật trong [HUONG_DAN_THANH_TOAN_VNPAY_SANDBOX.md](HUONG_DAN_THANH_TOAN_VNPAY_SANDBOX.md); migration mới không sửa migration đã chạy.

## Kiểm thử đã chạy

- `docker compose exec -T backend php artisan migrate --force`: không còn migration cần chạy; trạng thái migration mới là `Ran` trên MySQL local.
- `docker compose exec -T backend php artisan test`: **49 test pass**, gồm callback ký hợp lệ/sai chữ ký/sai tiền/đến muộn, phân quyền phí, doanh thu, khiếu nại, hai cuộc đua đồng thời trên MySQL và các hồi quy hiện có.
- Cuộc đua callback MySQL dùng hai tiến trình độc lập cùng xử lý một callback có chữ ký: kết quả một tiến trình ghi mới, tiến trình còn lại nhận trạng thái đã ghi; chỉ có một bút toán ví.
- Cuộc đua khiếu nại-scheduler MySQL ở fixture riêng: không giải ngân khi tranh chấp còn đang giữ tiền.
- `npm run build`: TypeScript và Vite production build thành công.
- `npm run lint`: exit 0; còn cảnh báo React hooks ở các effect tải dữ liệu và cảnh báo bundle lớn hơn 500 kB. Không có lỗi parse/type.
- `php artisan schedule:list`: có lịch mỗi phút cho `wallet:expire-vnpay-deposits` và `orders:release-eligible`; scheduler container local đã được khởi động.
- Trình duyệt không được kiểm tra trực quan trong lượt này vì môi trường đã từ chối truy cập origin local ở lần thử trước. Không đánh dấu UI desktop/mobile là PASS.

## Giới hạn

- Không chạy giao dịch từ trang VNPay Sandbox; kiểm thử thanh toán là callback ký cục bộ và test backend. Muốn xác nhận giao dịch nhà cung cấp cần merchant Sandbox được chấp thuận, thẻ thử và callback Return/IPN truy cập được từ VNPay.
- Không có hồ sơ khiếu nại pending trong dữ liệu MySQL ban đầu. Các hành động được kiểm thử trên fixture biệt lập, không thay dữ liệu người dùng hiện hữu.
- Không triển khai hoặc thay đổi máy chủ từ xa.
