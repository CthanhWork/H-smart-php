# H-Smart PHP

Đồ án H-Smart theo kiến trúc Laravel Modular Monolith và React/Vite. Module auth có đăng ký bằng email, xác thực email, đăng nhập, làm mới phiên, quên/đặt lại mật khẩu, đổi mật khẩu và đăng xuất. Người dùng đã đăng nhập có thể đăng bán, xem danh sách/chi tiết và quản lý sản phẩm của mình. **Admin có thể duyệt/ẩn sản phẩm và khóa/mở khóa tài khoản người dùng.**

- `backend/`: Laravel 13 API, module `Auth`, `User`, `Product` và `Admin`.
- `frontend/`: giao diện React 19, TypeScript và Vite với admin panel.
- `docs/architecture/`: thiết kế gốc, gồm schema PostgreSQL **15 bảng**. Migration hiện tạo `users`, `account_tokens`, `categories`, `products` và `product_images`.

Xem [PROJECT_LOG tổng hợp](PROJECT_LOG.md); nhật ký chi tiết được tách theo [Frontend](frontend/PROJECT_LOG.md) và [Backend](backend/PROJECT_LOG.md).

## 🚀 Khởi động nhanh 1 phút (Khuyến nghị)

**Cách dễ nhất - Tự động tạo admin và khởi động mọi thứ:**

### Windows
```powershell
.\quick-deploy.ps1
```

### Linux / macOS
```bash
chmod +x quick-deploy.sh
./quick-deploy.sh
```

Script sẽ tự động:
- ✅ Tạo file cấu hình `.env` với mật khẩu ngẫu nhiên
- ✅ Build và khởi động 4 containers (Database, Backend, Frontend, Email)
- ✅ Chạy database migrations
- ✅ **Tạo tài khoản admin** (`admin@hsmart.local` / `Admin@123456`)
- ✅ Hiển thị thông tin đăng nhập

Sau 2-5 phút, mở:
- **🌐 Ứng dụng**: http://localhost:5173
- **📧 Email test**: http://localhost:8025
- **🔧 Backend API**: http://localhost:8000

### Đăng nhập Admin

Sau khi chạy `quick-deploy`, đăng nhập với:
- **Email**: `admin@hsmart.local`
- **Password**: `Admin@123456`

⚠️ **Đổi mật khẩu ngay sau lần đăng nhập đầu tiên!**

Bấm nút **"Quản trị viên"** để vào admin panel với:
- 📦 **Duyệt/ẩn sản phẩm** chờ duyệt (với lý do)
- 👥 **Khóa/mở khóa tài khoản** người dùng
- 🔍 **Lọc và tìm kiếm** người dùng theo status/role

## 📚 Tài liệu

- **[QUICK_START.md](QUICK_START.md)** - Hướng dẫn khởi động nhanh chi tiết
- **[DOCKER_HUONG_DAN_NHANH.txt](DOCKER_HUONG_DAN_NHANH.txt)** - Hướng dẫn Docker cho H-Smart
- **[DOCKER_CO_BAN.txt](DOCKER_CO_BAN.txt)** - Khái niệm Docker cơ bản
- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Hướng dẫn triển khai production
- **[ADMIN_SUMMARY.md](ADMIN_SUMMARY.md)** - Tổng quan tính năng Admin

## 🛠️ Các lệnh thường dùng

```bash
# Khởi động hệ thống
.\quick-deploy.ps1          # Windows (tự động tạo admin)
./quick-deploy.sh            # Linux/Mac (tự động tạo admin)

# Hoặc khởi động thủ công
docker compose up -d         # Khởi động
docker compose down          # Dừng (giữ dữ liệu)
docker compose down -v       # Dừng và xóa dữ liệu

# Xem logs
docker compose logs -f
docker compose logs -f backend

# Tạo admin user thủ công
docker compose exec backend php artisan make:admin

# Chạy tests
docker compose exec backend php artisan test
```

## Chạy trên máy cá nhân (không dùng Docker)

Với Docker Desktop, chạy `.\docker-start.ps1` ở thư mục gốc. Docker Compose khởi động PostgreSQL, Laravel, React/Vite và Mailpit. Mở `http://localhost:5173` để dùng ứng dụng, `http://localhost:8025` để xem email xác thực thử nghiệm. Lệnh `docker compose down` dừng ứng dụng và giữ dữ liệu.

Nếu chạy trực tiếp không qua Docker, cần PHP 8.4.1+ (có `pdo_pgsql`, `sodium`, `mbstring`, `openssl`, `fileinfo`), Composer 2, PostgreSQL, Node.js 20.19+ và npm.

1. Trong `backend`, chạy `composer install`, sao chép `.env.example` thành `.env`, rồi chạy `php artisan key:generate`.
2. Tạo database PostgreSQL `hsmart`. Đặt `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=hsmart`, `DB_USERNAME=postgres` và mật khẩu PostgreSQL trong `DB_PASSWORD` của `.env`, rồi chạy `php artisan migrate`.
3. Tạo `JWT_SECRET` ngẫu nhiên tối thiểu 32 byte (ví dụ `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`) và đặt `HASH_DRIVER=argon2id` trong `.env`.
4. Để **nhận email thật**, cấu hình `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`. Mặc định `MAIL_MAILER=array` chỉ giữ thư trong bộ nhớ và không gửi đi. `FRONTEND_URL` phải là URL của giao diện (mặc định `http://localhost:5173`).
5. Chạy `php artisan storage:link` trong `backend` để URL ảnh đã tải lên có thể truy cập; PHP cần cho phép tối đa 10 MB mỗi ảnh và đủ dung lượng POST cho tối đa 5 ảnh.
6. Chạy `php artisan serve` trong `backend`. Trong `frontend`, chạy `npm install` rồi `npm run dev`. Mở `http://localhost:5173`.

Frontend dùng Vite proxy tới backend ở `127.0.0.1:8000`. Khi triển khai khác origin, đặt `CORS_ALLOWED_ORIGINS` bằng danh sách origin frontend, phân tách bằng dấu phẩy.

## Kiểm tra

- Backend: `cd backend && php artisan test`
- Frontend: `cd frontend && npm run build && npm run lint`

Mật khẩu đăng ký hoặc đặt lại dài 8–128 ký tự, bắt buộc nhập lại, chặn mật khẩu phổ biến và mật khẩu chứa phần tên email; mật khẩu được băm Argon2id. Email phải được xác thực trước khi đăng nhập. Token xác thực có hạn 24 giờ; token đặt lại mật khẩu có hạn 60 phút, đều chỉ dùng một lần. Access JWT có hạn 15 phút; refresh token có hạn 30 ngày, được xoay khi dùng và chỉ lưu SHA-256 hash. Đổi hoặc đặt lại mật khẩu sẽ thu hồi mọi phiên đăng nhập cũ.

Giao diện giữ token trong bộ nhớ, vì vậy tải lại trang sẽ cần đăng nhập lại. Đây là phạm vi đơn giản của giai đoạn đầu; có thể bổ sung cơ chế giữ phiên an toàn khi phát triển các màn hình tiếp theo.

Đăng bán dùng `POST /api/v1/products` với Bearer access token và `multipart/form-data`: `title`, `description`, `price` (VND nguyên dương), `condition` (`new`, `like_new`, `good`, `fair`) và tối đa 5 tệp `images[]` JPEG/PNG/WebP (mỗi tệp tối đa 10 MB). `seller_id` lấy từ phiên đăng nhập, không nhận từ client. Sản phẩm mới có trạng thái `pending_review` và chưa có luồng duyệt trong giai đoạn này.
