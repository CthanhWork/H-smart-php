#!/bin/bash
set -euo pipefail

cd "$(dirname "$0")"

# Kiểm tra Docker
if ! command -v docker &> /dev/null; then
    echo "Lỗi: Docker chưa được cài đặt."
    echo "Vui lòng cài Docker từ https://docs.docker.com/engine/install/"
    exit 1
fi

if ! docker compose version &> /dev/null; then
    echo "Lỗi: Docker Compose chưa sẵn sàng."
    echo "Vui lòng khởi động Docker Engine và thử lại."
    exit 1
fi

# Tạo file .env nếu chưa có
if [ ! -f .env ]; then
    echo "Tạo file cấu hình .env..."
    password=$(openssl rand -hex 32)
    echo "DB_PASSWORD=$password" > .env
fi

# Kiểm tra DB_PASSWORD trong .env
if ! grep -q '^DB_PASSWORD=..*$' .env; then
    echo "Lỗi: File .env cần có dòng DB_PASSWORD với mật khẩu PostgreSQL."
    exit 1
fi

echo ""
echo "==================================================="
echo "           H-Smart Docker Deployment"
echo "==================================================="
echo ""
echo "Đang khởi động Docker containers..."
echo "(Lần đầu tiên sẽ mất 2-5 phút để build images)"
echo ""

# Khởi động Docker Compose
docker compose up --build --wait

if [ $? -eq 0 ]; then
    echo ""
    echo "==================================================="
    echo "        ✓ Khởi động thành công!"
    echo "==================================================="
    echo ""
    echo "Truy cập ứng dụng:"
    echo "  • Giao diện web:     http://localhost:5173"
    echo "  • Email thử nghiệm:  http://localhost:8025"
    echo "  • Backend API:       http://localhost:8000"
    echo ""
    echo "Hướng dẫn sử dụng:"
    echo "  1. Mở http://localhost:5173 và chọn 'Đăng ký'"
    echo "  2. Mở http://localhost:8025 để xem email xác thực"
    echo "  3. Nhấn liên kết trong email để xác thực tài khoản"
    echo "  4. Quay lại giao diện và đăng nhập"
    echo ""
    echo "Để dừng ứng dụng:"
    echo "  docker compose down"
    echo ""
    echo "Để xem log:"
    echo "  docker compose logs -f"
    echo ""
    echo "==================================================="
    echo ""

    # Hiển thị trạng thái containers
    docker compose ps
else
    echo ""
    echo "Lỗi: Khởi động Docker thất bại."
    echo "Xem log chi tiết:"
    echo "  docker compose logs"
    exit 1
fi
