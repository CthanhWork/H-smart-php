# Báo cáo QA tài chính H-Smart — 24/11/2026

## Phạm vi và môi trường

- Kiểm tra bốn phạm vi: báo cáo doanh thu; nạp ví VNPay Sandbox; quyền xem thông tin tài chính của đơn; vòng đời khiếu nại và xử lý tiền.
- Môi trường local Docker, MySQL demo, frontend `localhost:5174`, backend `localhost:8001`. Workspace đang ở `main`, HEAD ban đầu `9421bda` (`Cập nhật lại nghiệp vụ tài chính H-Smart`); nhánh `codex/revenue-vnpay-disputes` tại `d040048` là ancestor của HEAD. Không checkout nhánh để tránh làm gián đoạn trạng thái workspace; các thay đổi QA bên dưới chưa commit. Không push, merge hoặc deploy.
- Tạo dữ liệu QA cục bộ cho người mua, người bán và admin. Không dùng tiền thật. Đã khôi phục mật khẩu gốc của ba tài khoản kiểm thử sau phiên; dữ liệu đơn/khiếu nại QA còn trong DB demo local.
- VNPay được cấu hình ở chế độ gateway Sandbox. Không lưu thông tin merchant/secret trong báo cáo.

## Kết quả theo chức năng

| Mã | Hạng mục / ca | Kết quả | Bằng chứng và ghi chú |
|---|---|---|---|
| REV-01 | Doanh thu người bán, kỳ tháng hiện tại và tháng trước | PASS | UI người bán hiển thị đơn #42: hàng 150.000đ, phí 3.500đ, doanh thu ròng 146.500đ; tổng trùng với ví đã ghi có. Chuyển “Tháng trước” hiển thị 0 và trạng thái rỗng; lọc ngày tùy chỉnh hoạt động. |
| REV-02 | Tổng hợp doanh thu quản trị | PASS | UI admin hiển thị doanh số hàng, số đơn, phí nền tảng, hoàn/điều chỉnh, tiền đang giữ, nghĩa vụ ví và yêu cầu rút; danh sách chi tiết khớp giao dịch dữ liệu demo tại thời điểm kiểm tra. |
| WAL-01 | Nạp dưới ngưỡng tối thiểu | PASS | UI từ chối 9.999đ tại bước kiểm tra trường, trước khi tạo yêu cầu. Ngưỡng 10.000đ được chấp nhận ở thử nghiệm tiếp theo. |
| WAL-02 | Tạo yêu cầu nạp 10.000đ và kiểm tra gateway | BLOCKED | API tạo yêu cầu `pending`, amount 10.000đ, mode `sandbox`, URL thuộc `sandbox.vnpayment.vn`. Cổng phản hồi mã 70 “Sai chữ ký” ở cả lần đầu và lần thử sau khi chỉnh canonicalization. Không phát sinh ghi có ví; không nhập thẻ/OTP hay thực hiện thanh toán. Cần đối chiếu cặp `TmnCode`/`HashSecret` Sandbox với VNPAY trước khi demo thanh toán end-to-end. Hai yêu cầu QA đang ở trạng thái pending trong DB local và sẽ tự hết hạn theo lịch. |
| ORD-01 | Người mua xem đơn của mình | PASS | UI buyer xem đơn #42, giá hàng 150.000đ và tổng 175.000đ; không hiển thị phí nền tảng hay tiền ròng người bán. API buyer không có `seller_financials`/`platform_fee`. |
| ORD-02 | Người bán xem thông tin tài chính đơn của mình | PASS | UI/API seller cho đơn #42 hiển thị giá cơ sở 150.000đ, phí 3.500đ, tỷ lệ 2%, tiền ròng 146.500đ và trạng thái giải ngân. |
| ORD-03 | Buyer gọi API admin doanh thu | PASS | API trả 403. Không thấy dữ liệu doanh thu quản trị ở phiên người mua. |
| DSP-01 | Mở khiếu nại, giữ tiền, chờ đối soát, giải ngân | PASS | UI mở khiếu nại #5 trên đơn #47; admin chuyển sang chờ đối soát rồi giải ngân. Đơn kết thúc `credited_to_wallet`; có một bút toán `ORDER_RELEASE_47`, sự kiện khiếu nại/audit và thông báo cho hai bên. |
| DSP-02 | Người mua đồng ý hoàn số dư; admin hoàn VNPay đã thu | PASS | UI lưu consent của người mua cho đơn #48; admin hoàn 30.000đ vào số dư mô phỏng. Ledger có ba dòng phân bổ: người bán -29.400đ, platform revenue -600đ, buyer available +30.000đ. Trạng thái `refunded_to_wallet`; chỉ một bút toán hoàn. |
| DSP-03 | COD direct không có tiền cọc do sàn giữ | PASS API sau sửa; UI retest chưa đủ | Đơn QA #49 được mở khiếu nại. Hồi quy tạo thêm tiền chờ giải ngân ở một đơn khác cùng người bán để kiểm tra không thể lấy nhầm tiền đó; API từ chối refund, không đổi số dư hai bên và không tạo transaction. Đã sửa UI admin để khóa nút hoàn và nêu lý do, nhưng chưa quan sát trực tiếp màn admin sau build cuối. |
| DSP-04 | Tải danh sách khiếu nại admin có lịch sử actor | PASS sau sửa | Khi mở danh sách lần đầu, API lỗi vì thiếu quan hệ `actor` của event. Đã bổ sung relation và hồi quy, sau đó UI hiển thị hồ sơ và lịch sử khiếu nại. |

## Sửa lỗi có bằng chứng

1. `OrderDisputeEvent` thiếu relation `actor`, làm trang admin khiếu nại trả Server Error khi eager-load lịch sử. Bổ sung relation và hồi quy vòng đời khiếu nại.
2. Refund COD direct không cọc có thể tính toàn bộ giá trị hàng là tiền sàn giữ. Thêm chặn ở API khi `cod_deposit_amount <= 0`, đồng thời disable hành động refund và giải thích trên UI. Test đặt một khoản escrow của đơn khác vào ví người bán và xác nhận mọi số dư/transaction đều giữ nguyên.
3. UI chi tiết COD dùng `deposit || payment.amount`, nên 0đ cọc hiển thị nhầm thành toàn bộ tiền COD đã thanh toán. Đã sửa UI để hiển thị đúng trường tiền cọc (kể cả 0) và nhãn “Không có tiền cọc”; build pass nhưng chưa quan sát lại màn buyer sau build cuối.
4. Theo [đặc tả kỹ thuật Sandbox VNPAY 2.1.0](https://sandbox.vnpayment.vn/apis/files/VNPAY%20Payment%20Gateway_Techspec%20Post%20method%202.1.0-VN.pdf), `vnp_SecureHash` ký các cặp key/value đã sắp xếp trước khi URL-encode. Cập nhật tạo/xác minh chữ ký và hồi quy canonicalization. Gateway vẫn báo chữ ký sai với cấu hình merchant hiện tại; đây chưa được coi là đã khắc phục hoàn toàn và cần kiểm tra lại cặp credential được cấp.

## Hồi quy và build

- Baseline trước sửa: `php artisan test --compact` — 49 passed, 325 assertions.
- Bộ test liên quan sau sửa: 18 passed, 134 assertions.
- Toàn bộ backend sau sửa: `php artisan test --compact` — **51 passed, 336 assertions**.
- Frontend: `npm run lint` hoàn tất (có các cảnh báo hook/state effect hiện có ở nhiều trang); `npm run build` thành công. Vite còn cảnh báo bundle JS minified khoảng 573 kB vượt ngưỡng 500 kB.
- `git diff --check` thành công. Compose backend/frontend hoạt động khỏe sau build.
- Ở viewport 390×844, homepage công khai không tràn ngang (`scrollWidth=375`); đây chưa phải bằng chứng responsive cho các trang tài chính đã đăng nhập. Các trang doanh thu, ví, đơn và khiếu nại đã quan sát trực quan ở cửa sổ mặc định khoảng 703×610. Chưa kiểm tra đủ các trang đó trên desktop cố định 1366×768 và mobile 390×844 sau build cuối; ảnh chụp chưa được đính kèm vào file báo cáo này.

## Kết luận demo

1. **Chức năng:** doanh thu, phân quyền và vòng đời khiếu nại đã chạy được trên local; nạp VNPay tạo được yêu cầu nhưng chưa qua cổng.
2. **Dữ liệu tài chính và phân quyền:** ca đã đối chiếu cân bằng số tiền và chặn hoàn COD direct không có tiền sàn giữ. Không phát hiện hoàn/giải ngân trùng trong các ca đã chạy.
3. **UI desktop:** các trang chính đã mở và thao tác ở cửa sổ mặc định; chưa có bằng chứng đủ ở 1366×768.
4. **UI mobile:** homepage công khai không tràn ngang ở 390×844; các màn tài chính đã đăng nhập chưa được kiểm tra đủ ở kích thước này.
5. **VNPay Sandbox:** BLOCKED ở màn báo lỗi “Sai chữ ký” (code 70), chưa đến màn chọn phương thức thanh toán.

Vì chưa xác minh được luồng Sandbox và còn thiếu kiểm tra responsive của các trang tài chính, **chưa đủ điều kiện kết luận demo end-to-end cả bốn hạng mục**. Có thể demo ba nhóm đã PASS bằng dữ liệu local/mô phỏng; cần xác nhận cặp merchant/hash secret do VNPAY cấp, rồi lặp lại nạp Sandbox và UI desktop/mobile. Không có thanh toán nào được gửi và không có tiền thật.
