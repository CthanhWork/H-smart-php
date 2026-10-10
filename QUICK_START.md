# H-Smart - Khởi động nhanh 1 phút

Hệ thống chợ đồ cũ dành cho sinh viên với giao diện quản trị admin.

## Yêu cầu hệ thống

- **Docker Desktop** (Windows/Mac) hoặc **Docker Engine** (Linux)
- Đó là tất cả! Không cần cài PHP, PostgreSQL, Node.js hay bất cứ thứ gì khác.

## Cài đặt Docker

Nếu chưa có Docker:
- **Windows/Mac**: Tải [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- **Linux**: `curl -fsSL https://get.docker.com | sh`

## Khởi động 1 lệnh

### Windows (PowerShell):
```powershell
.\quick-deploy.ps1
```

### Linux/Mac:
```bash
chmod +x quick-deploy.sh
./quick-deploy.sh
```

Script sẽ tự động:
1. ✅ Tạo file cấu hình `.env` với mật khẩu ngẫu nhiên
2. ✅ Build và khởi động 4 containers (Database, Backend, Frontend, Email)
3. ✅ Chạy database migrations
4. ✅ Tạo tài khoản admin mặc định
5. ✅ Hiển thị thông tin đăng nhập

## Truy cập ứng dụng

Sau khi khởi động thành công:

- **🌐 Giao diện web**: http://localhost:5173
- **📧 Email test**: http://localhost:8025
- **🔧 Backend API**: http://localhost:8000

## Đăng nhập Admin

Tài khoản admin mặc định:
- **Email**: `admin@hsmart.local`
- **Mật khẩu**: `Admin@123456`

**⚠️ Quan trọng**: Đổi mật khẩu admin ngay sau lần đăng nhập đầu tiên!

## Tính năng Admin

Sau khi đăng nhập với tài khoản admin:
1. Bấm nút **"Quản trị viên"**
2. Xem 2 tab:
   - **Sản phẩm chờ duyệt**: Duyệt/ẩn sản phẩm với lý do
   - **Quản lý người dùng**: Khóa/mở khóa tài khoản, lọc và tìm kiếm

## Dừng hệ thống

```bash
docker compose down
```

Hoặc dùng script:
```powershell
# Windows
.\docker-stop.ps1

# Linux/Mac
./docker-stop.sh
```

## Xóa toàn bộ dữ liệu

```bash
docker compose down -v
```

## Các lệnh hữu ích

```bash
# Xem trạng thái containers
docker compose ps

# Xem logs
docker compose logs -f

# Xem logs của backend
docker compose logs -f backend

# Khởi động lại
docker compose restart

# Tạo admin mới
docker compose exec backend php artisan make:admin
```

## Xử lý lỗi

### Lỗi: Port đã được sử dụng
```bash
# Kiểm tra port nào bị chiếm
netstat -ano | findstr :5173  # Windows
lsof -ti:5173                 # Linux/Mac

# Dừng containers cũ
docker compose down
```

### Lỗi: Database connection refused
```bash
# Khởi động lại backend sau khi database sẵn sàng
docker compose restart backend
```

### Reset hoàn toàn
```bash
docker compose down -v
docker system prune -a
.\quick-deploy.ps1  # hoặc ./quick-deploy.sh
```

## Hướng dẫn chi tiết

Xem file `DOCKER_HUONG_DAN_NHANH.txt` để biết thêm chi tiết về Docker và workflow phát triển.

## Workflow phát triển

1. **Khởi động**: `.\quick-deploy.ps1` hoặc `docker compose up -d`
2. **Code**: Sửa file trong `backend/` và `frontend/` - tự động reload
3. **Test**: `docker compose exec backend php artisan test`
4. **Xem logs**: `docker compose logs -f`
5. **Dừng**: `docker compose down`

## Sao lưu dữ liệu

```bash
# Backup database
docker compose exec db pg_dump -U hsmart hsmart > backup.sql

# Restore database
cat backup.sql | docker compose exec -T db psql -U hsmart hsmart
```

## Hỗ trợ

Nếu gặp vấn đề:
1. Kiểm tra Docker đang chạy: `docker ps`
2. Xem logs: `docker compose logs`
3. Đọc file `DOCKER_HUONG_DAN_NHANH.txt`
4. Reset: `docker compose down -v && .\quick-deploy.ps1`
