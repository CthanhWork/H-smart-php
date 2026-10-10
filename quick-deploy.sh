#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

echo ""
echo "==================================================="
echo "     H-Smart - Quick Deploy (1 phút khởi động)"
echo "==================================================="
echo ""

# Kiểm tra Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Lỗi: Chưa tìm thấy Docker."
    echo ""
    echo "Vui lòng cài Docker:"
    echo "  curl -fsSL https://get.docker.com | sh"
    echo ""
    exit 1
fi

# Kiểm tra Docker Compose
echo "🔍 Kiểm tra Docker..."
if ! docker compose version &> /dev/null; then
    echo "❌ Lỗi: Docker Compose chưa sẵn sàng."
    echo "Hãy khởi động Docker Engine rồi thử lại."
    exit 1
fi
echo "✅ Docker đã sẵn sàng"

# Tạo file .env nếu chưa có
if [ ! -f .env ]; then
    echo ""
    echo "📝 Tạo file cấu hình .env..."
    password=$(openssl rand -hex 32 2>/dev/null || head -c 32 /dev/urandom | xxd -p -c 32)
    echo "DB_PASSWORD=$password" > .env
    echo "✅ Đã tạo .env với mật khẩu database ngẫu nhiên"
fi

# Kiểm tra DB_PASSWORD
if ! grep -q '^DB_PASSWORD=..*$' .env; then
    echo "❌ Lỗi: File .env cần có dòng DB_PASSWORD với mật khẩu PostgreSQL."
    exit 1
fi

echo ""
echo "🚀 Đang khởi động Docker containers..."
echo "   (Lần đầu tiên sẽ mất 2-5 phút để build images)"
echo ""

# Khởi động Docker Compose
if ! docker compose up --build -d --wait; then
    echo ""
    echo "❌ Lỗi: Khởi động Docker thất bại."
    echo ""
    echo "Xem log chi tiết:"
    echo "  docker compose logs"
    echo ""
    exit 1
fi

echo ""
echo "✅ Containers đã khởi động"

# Chờ backend sẵn sàng
echo ""
echo "⏳ Đang chờ backend sẵn sàng..."
max_retries=30
retry_count=0
backend_ready=false

while [ $retry_count -lt $max_retries ]; do
    if curl -sf http://localhost:8000/up > /dev/null 2>&1; then
        backend_ready=true
        break
    fi
    sleep 2
    retry_count=$((retry_count + 1))
    echo -n "."
done

echo ""
if [ "$backend_ready" = false ]; then
    echo "⚠️  Backend mất nhiều thời gian hơn dự kiến, nhưng có thể vẫn đang khởi động..."
else
    echo "✅ Backend đã sẵn sàng"
fi

# Tạo admin user
echo ""
echo "👤 Đang tạo tài khoản admin..."

create_admin_script='
$admin = App\Modules\User\Models\User::where("email", "admin@hsmart.local")->first();
if (!$admin) {
    $admin = App\Modules\User\Models\User::create([
        "username" => "admin",
        "email" => "admin@hsmart.local",
        "password_hash" => bcrypt("Admin@123456"),
        "role" => "admin",
        "status" => "active"
    ]);
    echo "ADMIN_CREATED";
} else {
    echo "ADMIN_EXISTS";
}
'

result=$(docker compose exec -T backend php artisan tinker --execute "$create_admin_script" 2>&1 || true)

if echo "$result" | grep -q "ADMIN_CREATED"; then
    echo "✅ Đã tạo tài khoản admin mới"
elif echo "$result" | grep -q "ADMIN_EXISTS"; then
    echo "ℹ️  Tài khoản admin đã tồn tại"
else
    echo "⚠️  Không thể xác nhận tạo admin, có thể đã tồn tại hoặc cần kiểm tra logs"
fi

# Hiển thị thông tin thành công
echo ""
echo "==================================================="
echo "        ✅ KHỞI ĐỘNG THÀNH CÔNG!"
echo "==================================================="
echo ""
echo "🌐 Truy cập ứng dụng:"
echo "   • Giao diện web:     http://localhost:5173"
echo "   • Email thử nghiệm:  http://localhost:8025"
echo "   • Backend API:       http://localhost:8000"
echo ""
echo "👤 Đăng nhập Admin:"
echo "   Email:    admin@hsmart.local"
echo "   Password: Admin@123456"
echo ""
echo "   ⚠️  Hãy đổi mật khẩu ngay sau lần đăng nhập đầu tiên!"
echo ""
echo "📖 Hướng dẫn sử dụng:"
echo "   1. Mở http://localhost:5173"
echo "   2. Đăng nhập bằng tài khoản admin ở trên"
echo "   3. Bấm nút 'Quản trị viên' để vào admin panel"
echo "   4. Quản lý sản phẩm và người dùng"
echo ""
echo "🔧 Quản lý Docker:"
echo "   • Dừng:       docker compose down"
echo "   • Xem logs:   docker compose logs -f"
echo "   • Khởi động lại: docker compose restart"
echo ""
echo "==================================================="
echo ""

# Hiển thị trạng thái containers
echo "📊 Trạng thái containers:"
docker compose ps

echo ""
echo "✨ Hệ thống đã sẵn sàng! Chúc bạn sử dụng vui vẻ!"
echo ""
