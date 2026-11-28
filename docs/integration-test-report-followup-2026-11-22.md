# Báo cáo kiểm thử bổ sung H-Smart

> **Bản ghi lịch sử ngày 22/11/2026.** Các kết quả `mô phỏng`, `simulation` và `succeeded_simulated` dưới đây mô tả hành vi của phiên bản tại ngày kiểm tra, không phải luồng được hỗ trợ hiện nay. Nạp QA trực tiếp đã bị gỡ khỏi ứng dụng; mock được giữ cho test tự động. H-Smart hiện dùng VNPay Sandbox cho nạp/thanh toán, còn chi trả seller được quản trị viên ghi nhận thủ công trên dữ liệu demo/Sandbox. Bản ghi này không chứng minh chuyển khoản ngân hàng thật.

- **Ngày:** 22-11-2026
- **Báo cáo mốc:** `docs/integration-test-report-2026-11-22.md` (được giữ nguyên)
- **Mã nguồn:** HEAD `ac2cf04` cộng các thay đổi chưa commit đã có trong workspace và các sửa lỗi trong lượt kiểm thử. Không tạo commit.
- **Phạm vi:** kiểm thử bổ sung API, MySQL local đang chạy, cuộc gọi đồng thời thật, test tự động và build. Không reset DB/volume.

## Môi trường và ma trận bằng chứng

Backend, MySQL, AI, frontend, Mailpit và scheduler đều đang healthy/running. MySQL 8.0.46; Laravel 13.33.0/PHP 8.4.28; AI Python 3.9.25; frontend Vite 8.3.1, Node 24.11.1/npm 11.6.2. Migration mới đã được áp dụng; `php artisan migrate:status --pending` báo không còn migration chờ.

| Luồng | Kết quả | Bằng chứng thực tế và giới hạn |
|---|---|---|
| A. Chatbot → sản phẩm → giỏ | **PASS phần API**; **BLOCKED UI** | API chatbot tìm theo từ khóa/giá, trả sản phẩm thật; hội thoại A/B có ID/ngữ cảnh tách biệt; “cái thứ hai” trỏ đúng ID thứ hai ở kết quả hội thoại A; thêm kết quả vào giỏ lặp lại không tạo dòng thứ hai. Người dùng khác đọc/tiếp tục/xóa hội thoại bị từ chối. Tìm kiếm ẩn danh chạy được. Thêm sản phẩm ẩn hoặc của chính mình trả 409. Kiểm tra API dữ liệu thật, chưa xác minh card và phản hồi trực quan trên desktop/mobile.
| B. Giỏ, mua niêm yết, giữ hàng | **PASS phần API/MySQL**; offer cũ **unit-test** | Giỏ nhiều người bán, thêm lặp idempotent; giá đổi giữa báo giá và tạo đơn trả 409; tái sử dụng key với payload khác trả 409. Hai request đồng thời từ hai buyer cùng mua sản phẩm 101: một request tạo đơn, request kia nhận 409; MySQL chỉ có một đơn giữ hàng (order 39, product 101). Order 42 được tạo/hoàn tất qua MySQL. Hết hạn order 40 chuyển `cancelled/expired`, product 103 được giải phóng. Nhánh offer được chấp nhận chỉ kiểm tra bằng test tự động, không dựng riêng offer trên MySQL trong lượt này.
| C. Hạn mức đăng tin và phí | **PASS luồng quota chính**; báo giá đổi cấu hình **unit-test** | Seller 94 có 4 lượt miễn phí đã dùng; hai phê duyệt đồng thời ở lượt miễn phí cuối đều trả kết quả hợp lệ nhưng DB chỉ ghi đúng một tin miễn phí, tin còn lại chờ phí 5.000đ. Đã thanh toán phí đăng cho product 116; lần lặp bị từ chối. Bốn sản phẩm được duyệt trước đó không bị tính lại. Test tự động bao phủ draft/từ chối/duyệt lại/xóa và báo giá phí cũ khi cấu hình đổi. Ranh giới tháng Asia/Ho_Chi_Minh chưa chạy đối kháng trực tiếp trên MySQL.
| D. VIP/đẩy tin | **PASS API/MySQL, hết hạn scheduler** | Seller 94 mua VIP product 116 (15.000đ/7 ngày) và boost product 117 (10.000đ/3 ngày); request VIP lặp không trừ tiền lần hai, đổi phí trên cùng key bị từ chối. Đã đặt `expires_at` của VIP 116 vào quá khứ trong dữ liệu QA, chạy `products:expire-promotions`: một gói chuyển `active → expired`; chạy lần hai báo 0 gói, không lặp tác dụng. Quy tắc lọc/phạm vi xếp hạng và loại gói có thêm PHPUnit; chưa đo thứ tự qua giao diện/phân trang thực tế.
| E. VNPay Sandbox | Tạo URL và callback local **PASS**; giao dịch Sandbox ngoài **BLOCKED** | URL API trỏ host Sandbox, số tiền nhân 100 đúng. Callback ký hợp lệ được gửi vào backend local, callback lặp không ghi escrow lần hai; đổi số tiền nhưng giữ chữ ký cũ bị từ chối. Đây là callback tổng hợp ký bằng cấu hình local, **không phải giao dịch trên trang Sandbox**. Không hoàn tất redirect/thẻ Sandbox trong trình duyệt.
| F. Giao hàng, 48 giờ, giải ngân | **PASS với test và MySQL đồng thời**; dispute race **unit-test** | Order 42: thanh toán mô phỏng → seller xác nhận/giao → admin xác nhận đã giao → buyer hoàn tất. DB ghi `release_eligible_at = delivered_at + 48 giờ`. Scheduler trước hạn trả 0. Sau khi điều khiển thời gian DB vượt hạn, buyer-confirm API và `orders:release-eligible` chạy đồng thời; ledger chỉ có một `ORDER_RELEASE_42`, số dư khả dụng seller tăng 146.500đ, pending release về 0. Gọi lại không tạo thêm ghi có. Tranh chấp đang mở chặn scheduler được bao phủ bằng PHPUnit SQLite; chưa chạy đồng thời khiếu nại và scheduler trên MySQL.
| G. Ví mô phỏng/đối soát | **PASS các luồng đã thử trực tiếp**; một số lệch đối soát **unit-test** | Nạp thử 250.000đ; gửi lại cùng reference với số tiền khác trả 409. Rút 100.000đ chuyển đúng từ available sang hold; hai request rút 100.000đ chạy đồng thời khi chỉ đủ một khoản cho kết quả 201/409, số dư còn 50.000đ và hold 100.000đ. Seller 91 nhận 146.500đ tiền bán, tạo yêu cầu rút 100.000đ, admin chuyển processing rồi `succeeded_simulated`; available còn 46.500đ, hold 0. Seller 94 thanh toán phí đăng/VIP/boost trong chế độ simulation. Đối soát thiếu/dư/trùng/đến muộn có PHPUnit, không thử chuyển tiền/ngân hàng thật. Không ghi secret hoặc mật khẩu vào báo cáo.
| H. Admin, chính sách, UI và luồng cũ | **PASS một phần API**; **BLOCKED UI** | API admin đã duyệt tin, xác nhận giao, xử lý payout mô phỏng; quyền admin/member được kiểm tra ở các route đã gọi. API upload ảnh thật trả 201, tạo product 118 ở trạng thái `pending_review` kèm ảnh lưu trữ. Test quyền, policy và trạng thái còn lại được bao phủ một phần trong PHPUnit. Thao tác mọi trang admin, thông báo/đánh giá, desktop và viewport 390px chưa kiểm chứng trực quan: công cụ trình duyệt trước đó từ chối quyền truy cập origin local; không dùng bề mặt trình duyệt khác để lách.

**Các mục chưa được coi là PASS:** giao dịch VNPay Sandbox qua nhà cung cấp; kiểm tra trực quan desktop/mobile/console; thử toàn bộ CRUD quản trị trên UI; tranh chấp-vs-scheduler cùng lúc trên MySQL; boundary đổi tháng trực tiếp trên MySQL; các tình huống đối soát tiền thiếu/dư/trùng chỉ mới dùng test tự động. Không có bằng chứng giao dịch tiền thật.

## Lỗi tái hiện và sửa trong lượt này

1. **P1 — buyer xác nhận làm mất cửa sổ khiếu nại 48 giờ.** Tái hiện ở order 41: trước bản vá, `release_eligible_at` bị ghi đè thành thời điểm giao/xác nhận; scheduler đã giải ngân order 41 ngay sau đó. Sửa `AdvanceOrderAction::complete` để giữ hạn `delivered_at + 48 giờ` (không rút ngắn hạn muộn hơn), thêm kiểm thử biên trước/đúng hạn và xác minh lại đầy đủ trên order 42 bằng MySQL. Order 41 được giữ làm bằng chứng trạng thái QA trước sửa, không hoàn tác bút toán đã ghi.
2. **P1 — MySQL từ chối trạng thái chờ thanh toán phí đăng tin.** Constraint trạng thái cũ không cho `pending_listing_payment`, làm thao tác duyệt tin thứ sáu trả 500. Thêm migration `2026_11_22_000001_allow_paid_listing_status.php` để cập nhật constraint mà không sửa migration cũ; áp dụng migration, duyệt và thanh toán product 116 thành công.
3. **P1 — dùng lại idempotency key với payload khác không bị phát hiện nhất quán.** Bổ sung so khớp payload/số tiền cho đơn, ledger/nạp-rút và mua promotion; payload khác nhận 409 thay vì nhận lại kết quả cũ. Có regression tests cho đơn, ledger, rút và promotion.
4. **P1 — cạnh tranh quota, thanh toán và giải ngân cần xác minh khóa/ledger trên DB thật.** Cuộc chạy đồng thời cho thấy chỉ một request giữ product 101; quota cuối chỉ có một lượt miễn phí; rút tiền chỉ có một request thành công; giải ngân order 42 chỉ tạo một giao dịch. Không phát hiện ghi có kép ở các ca này.
5. **P2 — thêm giỏ với sản phẩm ẩn/tự bán trả 500.** Thiếu import `ConflictHttpException` trong `CartController`; đã thêm import và regression test. Sau dựng lại, cả hai trường hợp trả 409 đúng.

## Kết quả hồi quy

| Lệnh/kiểm tra | Kết quả |
|---|---|
| `docker compose up -d --build backend scheduler` | PASS; image mới build và backend/scheduler healthy. |
| `docker compose exec -T backend php artisan migrate:status --pending` | PASS; không có migration pending. |
| `docker compose exec -T backend php artisan test --compact` | **37 passed, 237 assertions**. PHPUnit dùng SQLite in-memory theo cấu hình dự án; không thay thế các ca MySQL đồng thời/API nêu trên. |
| `python -m unittest discover -s tests -p test_parse_product_search.py -v` (tại `ai-service`, `PYTHONPATH=.`) | **3 passed**. Thử `pytest` trong image AI không chạy vì image không có executable pytest; bộ test này là unittest và đã chạy thành công bằng Python host. |
| `npm run build` (tại `frontend`) | PASS; TypeScript/Vite build thành công. Có cảnh báo bundle JS 558.01 kB vượt ngưỡng 500 kB. |
| `npm run lint` (tại `frontend`) | Thoát mã 0; còn cảnh báo Oxlint về setState/effect dependencies và `Date.now` trong render. |
| `npm audit --omit=dev` (tại `frontend`) | PASS; 0 vulnerabilities. |
| Pint trên 5 file hồi quy PHP | PASS. `vendor/bin/pint --test` toàn backend báo **70 lỗi định dạng trên 211 file** (bao gồm file cũ và mới); không áp formatter lên toàn bộ workspace để tránh chỉnh rộng thay đổi có sẵn. |
| `git diff --check` | PASS; Git chỉ cảnh báo LF/CRLF cho hai file Python trên Windows. |

## Tài khoản và dữ liệu QA local

Các tài khoản thử nghiệm đã tạo trong DB local, không có thông tin xác thực trong tài liệu:

- Buyers: `deep-buyer-a@hsmart.local` (ID 89), `deep-buyer-b@hsmart.local` (90), `deep-buyer-c@hsmart.local` (95), `deep-buyer-d@hsmart.local` (96).
- Sellers: `deep-seller-a@hsmart.local` (91), `deep-seller-b@hsmart.local` (92), `deep-seller-c@hsmart.local` (94).
- Admin: `deep-admin@hsmart.local` (93).
- Sản phẩm: 101 (tranh chấp đặt cùng món, hiện giữ bởi order 39); 102 (đã bán qua order 42); 103 (được nhả sau order 40 hết hạn); 104 (ẩn); 112–116 (quota seller 94; 116 đã hết hạn VIP); 117 (boost, đã được bán); 118 (upload ảnh, chờ duyệt).
- Đơn: 38 và 40 đã hết hạn/hủy; 39 đang chờ thanh toán và product 101 đang giữ để thể hiện trạng thái cạnh tranh; 41 là trạng thái trước sửa cửa sổ 48 giờ; 42 là luồng hoàn tất/giải ngân sau sửa.

Phép thử upload tạm thời đặt lại mật khẩu seller 91, tạo sản phẩm 118, rồi khôi phục chính xác hash mật khẩu ban đầu trong cùng lượt chạy. Không có password/token được ghi vào output lưu trữ hoặc tài liệu.

## Lệnh chạy lại

Từ `D:\H-smart-php`:

```powershell
docker compose ps
docker compose exec -T backend php artisan migrate:status --pending
docker compose exec -T backend php artisan test --compact
Push-Location ai-service
$env:PYTHONPATH='.'
python -m unittest discover -s tests -p test_parse_product_search.py -v
Pop-Location
Push-Location frontend
npm run build
npm run lint
npm audit --omit=dev
Pop-Location
```

Các yêu cầu đồng thời được gọi song song qua API backend và đối chiếu bản ghi/bút toán trên MySQL local; PHPUnit chạy SQLite in-memory. Không chạy lại các lệnh thử thời gian/scheduler trên cùng dữ liệu nếu muốn giữ nguyên trạng thái demo (đặc biệt VIP 116 đã được đặt hết hạn và order 42 đã giải ngân).
