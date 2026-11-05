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

Tài khoản quản trị mặc định trên môi trường Docker mới:

- Email: `admin@hsmart.local`
- Mật khẩu: `Admin@123456`

Đổi mật khẩu này khi dùng môi trường chia sẻ. Tài khoản người dùng mới cần xác thực email từ hộp thư thử nghiệm trước khi đăng nhập.

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
