# H-Smart

H-Smart là đồ án chợ đồ cũ dành cho sinh viên. Dự án gồm giao diện React, API Laravel và MySQL; Docker Compose chạy toàn bộ các dịch vụ.

## Chạy bằng Docker

Cài Docker Desktop và bật Docker Engine. Tải mã nguồn, mở PowerShell tại thư mục dự án rồi chạy:

```powershell
.\docker-start.ps1
```

Lần đầu Docker sẽ tải các image và build ứng dụng. Backend tự chạy migration và nạp dữ liệu demo gồm danh mục cùng các tin đăng mẫu. Các lần chạy sau sẽ giữ dữ liệu trong Docker volumes.

Mở ứng dụng tại:

- Giao diện: http://localhost:5173
- API: http://localhost:8000
- Hộp thư thử nghiệm: http://localhost:8025
- Quản lý cơ sở dữ liệu: http://localhost:8081

Tài khoản quản trị mặc định trên môi trường Docker mới:

- Email: `admin@hsmart.local`
- Mật khẩu: `Admin@123456`

Đổi mật khẩu này khi dùng môi trường chia sẻ. Tài khoản người dùng mới cần xác thực email từ hộp thư thử nghiệm trước khi đăng nhập.

## Dữ liệu và cơ sở dữ liệu

Migration và seeder trong `backend/database` là nguồn dữ liệu có thể tái tạo. Khi khởi tạo lần đầu, `DatabaseSeeder` tạo tài khoản quản trị, các danh mục và các sản phẩm demo. Seeder sản phẩm có thể chạy lại mà không tạo bản sao cho các tin mẫu.

Dữ liệu đang chạy được lưu trong các Docker volumes, không nằm trong Git. `docker compose down` dừng dịch vụ và giữ dữ liệu. Lệnh `docker compose down -v` xóa database và các tệp lưu trong volumes; lần khởi động tiếp theo sẽ tạo lại database từ migration và seeder.

Trong phpMyAdmin, chọn máy chủ `db`, đăng nhập với tên người dùng `hsmart` và mật khẩu lấy từ file `.env` ở thư mục dự án. Không commit `.env` hoặc bản sao lưu chứa tài khoản người dùng lên GitHub.

## Lệnh thường dùng

```powershell
# Dừng dịch vụ, giữ dữ liệu
.\docker-stop.ps1

# Xem trạng thái
 docker compose ps

# Xem log
 docker compose logs -f

# Chạy kiểm thử backend
 docker compose exec backend php artisan test
```

Có thể chạy `docker compose up -d --build` để build và khởi động lại sau khi cập nhật mã nguồn.
