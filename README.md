# H-Smart

H-Smart là đồ án chợ đồ cũ dành cho sinh viên. Dự án gồm giao diện React, API Laravel và MySQL; Docker Compose chạy toàn bộ các dịch vụ.

## Chạy bằng Docker

Cài Docker Desktop và bật Docker Engine. Tải mã nguồn, mở PowerShell tại thư mục dự án rồi chạy:

```powershell
.\docker-start.ps1
```

Lần đầu Docker sẽ tải các image và build ứng dụng. Backend tự chạy migration và nạp dữ liệu demo gồm danh mục cùng các tin đăng mẫu. Các lần chạy sau sẽ giữ dữ liệu trong Docker volumes.

Mở ứng dụng tại:

- Giao diện: http://localhost:5174
- API: http://localhost:8001
- Hộp thư thử nghiệm: http://localhost:8026
- Quản lý cơ sở dữ liệu: http://localhost:8082

### Thanh toán VNPay Sandbox

Xem [hướng dẫn cấu hình merchant Sandbox, Return/IPN và chạy thử thanh toán](docs/HUONG_DAN_THANH_TOAN_VNPAY_SANDBOX.md). Cần dùng thông tin Sandbox riêng; không commit thông tin merchant hoặc secret vào GitHub.

### Cấu hình AI

Người mới pull code: xem [hướng dẫn lấy API key Gemini từ Google AI Studio và bật AI](docs/HUONG_DAN_LAY_API_KEY_GEMINI.md).

Xem [tài liệu mô tả chức năng AI](docs/MO_TA_CHUC_NANG_AI.md) để biết cách dùng chatbot, nhận diện ảnh YOLO, tạo mô tả sản phẩm, luồng xử lý và các giới hạn hiện tại.

AI service (YOLO, chatbot và tạo mô tả) được đóng gói trong thư mục `ai-service`; không cần clone thêm repo/thư mục bên cạnh. YOLO nhận diện ảnh bằng checkpoint đã có sẵn trong repo. Chatbot có thể trích đoạn từ tài liệu H-Smart đã duyệt; để bật câu trả lời Gemini và tạo mô tả sản phẩm, mỗi người cần tự thêm khóa Gemini của mình vào `AI_API_KEY` trong `.env` (không commit khóa lên Git).

`docker-start.ps1` tạo `.env` từ `.env.example`. Sau khi thêm hoặc đổi khóa Gemini, áp dụng bằng lệnh:

```powershell
docker compose up -d --build ai backend
```

Các cổng này được ánh xạ từ máy tính vào Docker. MySQL chỉ dùng trong mạng nội bộ Docker nên không chiếm cổng MySQL của AMPPS trên Windows.

### Laptop 8 GB RAM

Chạy chế độ tiết kiệm RAM bằng PowerShell:

```powershell
.\docker-start.ps1 -LowMemory
```

Chế độ này dùng `compose.low-memory.yaml` cùng cấu hình gốc và giữ nguyên database, ảnh, tài khoản, email và các cổng. Frontend được build trước rồi phục vụ bằng Nginx với một worker; mã nguồn thay đổi cần build lại. MySQL dùng buffer pool 64 MB, tối đa 40 kết nối; AI chạy một worker với hai luồng tính toán. Scheduler và Mailpit vẫn chạy. Các container có giới hạn RAM và log xoay vòng để giảm tải máy.

phpMyAdmin chỉ bật khi cần:

```powershell
docker compose -f compose.yaml -f compose.low-memory.yaml --profile tools up -d phpmyadmin
docker compose -f compose.yaml -f compose.low-memory.yaml stop phpmyadmin
```

Nếu phpMyAdmin đã chạy ở chế độ thường, dùng lệnh `stop phpmyadmin` trên khi chuyển sang chế độ tiết kiệm RAM. Để dừng ứng dụng, chạy `docker compose -f compose.yaml -f compose.low-memory.yaml down` (giữ dữ liệu).

Nên dành khoảng 3 GB RAM cho máy ảo Docker trên laptop 8 GB và đóng bớt ứng dụng nặng. Lần build đầu cần thêm RAM và thời gian; script build tuần tự trong chế độ này. Đây là cấu hình cho phát triển/demo với ít người dùng đồng thời; giới hạn RAM container không bao gồm RAM của Windows, trình duyệt và Docker Desktop. Chạy `docker stats --no-stream` để xem mức dùng thực tế.

Quay lại chế độ Vite phục vụ phát triển: `.\docker-start.ps1` (không có `-LowMemory`).

Kết quả kiểm tra ngày 31/10/2026 trên máy host 24 GB, máy ảo Docker giới hạn khoảng 1,9 GiB:

| Hạng mục | Số đo |
| --- | --- |
| Frontend Vite trước tối ưu | 466,9 MiB RAM |
| Frontend Nginx sau tối ưu | 7,8 MiB RAM |
| Tổng 6 container sau gọi API và nhận diện ảnh | Khoảng 547 MiB RAM tại thời điểm đo |
| API danh sách sản phẩm qua Nginx, 20 request / 2 đồng thời | 20/20 thành công; median 15,2 ms, p95 22,5 ms |

Phép đo API dùng `127.0.0.1` để tránh độ trễ chuyển IPv6/IPv4 của `localhost` trên máy đo. Đã kiểm tra trang chủ trong trình duyệt, reload đường dẫn SPA, API đăng nhập, upload ảnh nhận diện, gzip và cache asset. AI dùng model YOLO thật trên CPU (`demo_mode=false`). Các container không ghi nhận OOM hoặc tự khởi động lại trong lượt thử. Số RAM trên là snapshot, không phải mức đỉnh; chưa kiểm thử tải lớn hoặc trực tiếp trên phần cứng 8 GB.

Tài khoản quản trị được cấu hình bằng `INITIAL_ADMIN_EMAIL` và `INITIAL_ADMIN_PASSWORD` trong `.env`. Script tự tạo `.env` sẽ sinh mật khẩu riêng; không dùng chung file `.env` chứa mật khẩu admin giữa các môi trường. Tài khoản người dùng mới cần xác thực email từ hộp thư thử nghiệm trước khi đăng nhập.

## Dữ liệu và cơ sở dữ liệu

Migration và seeder trong `backend/database` là nguồn dữ liệu có thể tái tạo. Khi khởi tạo lần đầu, `DatabaseSeeder` tạo tài khoản quản trị, các danh mục và các sản phẩm demo. Seeder sản phẩm có thể chạy lại mà không tạo bản sao cho các tin mẫu.

Dự án có kèm snapshot database thử nghiệm tại `database/hsmart-demo.sql`. Trên lần khởi động đầu tiên với Docker volume mới, MySQL tự nạp snapshot này; sau đó backend chạy migration và seeder để cập nhật cấu trúc và dữ liệu mẫu. Snapshot gồm tài khoản demo, danh mục, sản phẩm và toàn bộ dữ liệu thử nghiệm hiện có.

Dữ liệu sau khi chạy được lưu trong Docker volume. `docker compose down` dừng dịch vụ và giữ dữ liệu. Lệnh `docker compose down -v` xóa database và các tệp lưu trong volumes; lần khởi động tiếp theo sẽ nạp lại snapshot từ đầu. Lệnh này xóa mọi thay đổi cục bộ nên chỉ dùng khi muốn reset toàn bộ dữ liệu.

Trong phpMyAdmin, chọn máy chủ `db`, đăng nhập với tên người dùng `hsmart` và mật khẩu lấy từ file `.env` ở thư mục dự án. Snapshot trong repo chỉ chứa dữ liệu phục vụ đồ án và tài khoản thử nghiệm. Không thay file snapshot bằng database thật hoặc commit `.env` lên GitHub.

## Lệnh thường dùng

```powershell
# Dừng dịch vụ, giữ dữ liệu
.\docker-stop.ps1

# Xem trạng thái
 docker compose ps

# Xem log
 docker compose logs -f

```

Có thể chạy `docker compose up -d --build` để build và khởi động lại sau khi cập nhật mã nguồn.

Thanh toán thử không cần đăng ký merchant: xem [hướng dẫn VNPay mô phỏng](docs/HUONG_DAN_THANH_TOAN_MO_PHONG.md).

## Trợ lý tìm sản phẩm và giỏ hàng

- Mở `/assistant` để tìm sản phẩm bằng tiếng Việt. Tìm kiếm công khai không cần đăng nhập; đăng nhập để lưu hội thoại, tra cứu đơn của mình và thêm hàng vào giỏ.
- Trợ lý dùng AI để phân tích ý định/bộ lọc khi AI sẵn sàng. Laravel luôn kiểm tra bộ lọc và lấy sản phẩm thật từ tin đã duyệt, đang bán. Khi AI không khả dụng, bộ lọc cơ bản vẫn chạy tại backend.
- `/me/cart` chỉ dành cho người đăng nhập. Giỏ không giữ hàng; mỗi sản phẩm được đặt thành một đơn riêng. Khi đặt mua, giá và trạng thái được kiểm tra lại, sản phẩm được giữ trong 30 phút. VNPay/đơn hàng mô phỏng vẫn phải xác nhận qua luồng hiện có.
- Hoa hồng mặc định của đơn mới là 2% trên giá sản phẩm cộng phí vận chuyển (`MARKETPLACE_COMMISSION_RATE`, `MARKETPLACE_SHIPPING_FEE`). Mức phí và cơ sở tính được chụp lên đơn; đơn cũ không bị tính lại.
- Hạn mức mặc định là 5 lần xuất bản miễn phí mỗi tài khoản trong tháng Việt Nam; từ lần thứ 6 là 5.000đ. Chỉ tin duyệt lần đầu mới ghi nhận một lượt. Tin vượt hạn mức chờ người bán xác nhận phí và thanh toán từ số dư mô phỏng trước khi xuất bản. Có thể cấu hình bằng `MARKETPLACE_LISTING_FREE_MONTHLY` và `MARKETPLACE_LISTING_FEE`.
- Tin đang bán có thể mua gói đẩy mặc định 10.000đ/3 ngày hoặc VIP 15.000đ/7 ngày. Theo sắp xếp mặc định, tối đa 3 tin ưu tiên đứng trước danh sách thường; VIP xếp trước tin đẩy. VIP áp dụng ở trang chủ và danh mục, tin đẩy chỉ trong danh mục. Các tin cùng gói luân phiên theo lát 5 phút, vẫn tuân theo bộ lọc; vị trí thay đổi theo lượt luân phiên. Mức giá, thời lượng và số vị trí có thể cấu hình bằng `MARKETPLACE_BOOST_*`, `MARKETPLACE_VIP_*` và `MARKETPLACE_PROMOTION_SLOTS`.
- Admin có thể sửa hoa hồng, phí giao hàng, hạn mức và phí đăng tin, giá/thời lượng Boost và VIP, số vị trí ưu tiên, và tỷ lệ cọc COD (20–30%) tại **Quản trị → Biểu phí marketplace**. Giá mới áp dụng cho nghiệp vụ mới; đơn/giao dịch đã tạo giữ snapshot trước đó. Thay đổi được ghi nhật ký.
- COD yêu cầu cọc online mặc định 25% giá sản phẩm trước khi người bán xác nhận/gửi hàng. Phần còn lại là tiền sản phẩm trừ cọc; phí giao hàng thu riêng cùng lúc nhận hàng. Hoa hồng toàn đơn chỉ trừ một lần vào cọc. Thu phần còn lại được ghi rõ là người bán thu trực tiếp hoặc đơn vị giao nhận thu; tiền thu trực tiếp không được cộng vào ví nội bộ. Nếu hàng hoàn về, không tự tịch thu cọc; hoàn cọc về số dư mô phỏng cần người mua đồng ý và quản trị viên xác minh.
- VietQR có adapter tạo hướng dẫn/ảnh QR khi cấu hình `VIETQR_BANK_ID`, `VIETQR_ACCOUNT_NUMBER` và `VIETQR_ACCOUNT_NAME`. Adapter không xác minh chuyển khoản và không tự đánh dấu đơn đã thanh toán. Cần tích hợp webhook/sao kê ngân hàng đã xác minh và quy trình đối soát trước khi nhận tiền thật.
- Admin có thể ghi nhận một dòng VietQR sau khi kiểm tra trực tiếp sao kê tại **Quản trị → Đối soát thanh toán**. Chỉ số tiền khớp với đơn còn hiệu lực mới xác nhận đơn; mã không khớp, sai số hoặc đến muộn được lưu lại nhưng không tự ghi nhận thanh toán. Chưa có đồng bộ sao kê tự động hay hoàn tiền ngân hàng.

### Kịch bản demo thương mại

Trong file `.env` ở thư mục gốc (được `docker-start.ps1` tạo và Git bỏ qua), đặt `SEED_COMMERCE_DEMO=true` và `DEMO_USER_PASSWORD` thành mật khẩu riêng dài ít nhất 16 ký tự. Sau khi migration và sản phẩm demo đã được seed, chạy `docker compose exec backend php artisan db:seed`. Seeder chỉ chạy trong `local`/`testing`; tài khoản đăng nhập là `demo-seller@hsmart.local` và `demo-buyer@hsmart.local`, dùng mật khẩu đã cấu hình. Seeder tạo năm lượt đăng miễn phí đã dùng, gói VIP còn hạn và đã hết hạn, món hàng trong giỏ, đơn COD đã cọc, đơn còn trong cửa sổ 48 giờ, đơn có khiếu nại, cùng số dư và yêu cầu rút tiền mô phỏng. Không chạy seeder này trên dữ liệu production.

Sau khi cập nhật mã, chạy migration bằng `docker compose exec backend php artisan migrate --force`. Scheduler trong cấu hình compose chạy lệnh `orders:expire-pending`, `orders:release-eligible` và `products:expire-promotions` mỗi phút; ở môi trường khác cần chạy Laravel Scheduler liên tục. Chạy `cd frontend; npm run build` để kiểm tra giao diện.

Để demo: mở `/assistant`, tìm “nồi cơm điện dưới 500 nghìn”, chọn một thẻ để xem giá hiện tại, thêm vào giỏ, nhập thông tin giao nhận và đặt từng sản phẩm thành đơn riêng. Mở `/me/orders` để tiếp tục thanh toán VNPay Sandbox/mô phỏng. Sau khi xác nhận giao hàng, khoản bán hàng chờ 48 giờ trước khi chuyển vào số dư nội bộ. Buyer có thể mở khiếu nại trong thời gian tiền chưa giải ngân; admin xử lý tại mục Khiếu nại. `/me/wallet` và mục Rút tiền mô phỏng chỉ ghi sổ nội bộ trong `local/testing` khi `WALLET_SIMULATION_ENABLED=true`; chúng không chuyển tiền thật. Kiểm tra AI fallback bằng `cd ai-service; python -m unittest discover -s tests -v`. Khi Docker chạy, kiểm thử backend trên SQLite tạm trong test bằng `docker compose exec backend php artisan test`.

Các trang chính sách công khai nằm tại `/policies/fees` và `/policies/payment`. Nạp, rút, ví và giải ngân vẫn là mô phỏng nội bộ; chưa có xác minh ngân hàng thật hay đối soát đơn vị vận chuyển thật. Không dùng chế độ mô phỏng như giao dịch tiền thật. Xem [chính sách chatbot đã duyệt](docs/assistant/approved/commerce-policies.md) để biết phần cần cấu hình trước khi vận hành.
