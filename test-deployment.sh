#!/bin/bash
# Script kiểm tra deployment tự động

set -euo pipefail

TIMEOUT=300  # 5 phút timeout

echo "========================================"
echo "  Test H-Smart Docker Deployment"
echo "========================================"
echo ""

# Kiểm tra Docker
if ! command -v docker &> /dev/null; then
    echo "✗ Docker chưa được cài đặt"
    exit 1
fi
echo "✓ Docker có sẵn"

if ! docker compose version &> /dev/null; then
    echo "✗ Docker Compose chưa sẵn sàng"
    exit 1
fi
echo "✓ Docker Compose có sẵn"

# Dọn dẹp trước khi test
echo ""
echo "Dọn dẹp môi trường cũ..."
docker compose down -v 2>/dev/null || true
rm -f .env

# Test script khởi động
echo ""
echo "Test 1: Khởi động ứng dụng"
echo "----------------------------"

if [ -f "docker-start.sh" ]; then
    chmod +x docker-start.sh
    echo "✓ docker-start.sh có thể chạy"
else
    echo "✗ Không tìm thấy docker-start.sh"
    exit 1
fi

# Chạy khởi động
echo "Đang khởi động containers (có thể mất vài phút)..."
timeout $TIMEOUT ./docker-start.sh > /tmp/h-smart-startup.log 2>&1 || {
    echo "✗ Khởi động thất bại hoặc timeout"
    echo "Log:"
    tail -n 50 /tmp/h-smart-startup.log
    exit 1
}

echo "✓ Khởi động thành công"

# Kiểm tra containers
echo ""
echo "Test 2: Kiểm tra containers"
echo "----------------------------"

services=("db" "backend" "frontend" "mailpit")
all_running=true

for service in "${services[@]}"; do
    if docker compose ps | grep -q "$service.*running"; then
        echo "✓ $service đang chạy"
    else
        echo "✗ $service không chạy"
        all_running=false
    fi
done

if [ "$all_running" = false ]; then
    echo ""
    echo "Chi tiết containers:"
    docker compose ps
    exit 1
fi

# Kiểm tra health
echo ""
echo "Test 3: Kiểm tra health endpoints"
echo "-----------------------------------"

sleep 5  # Đợi services ổn định

# Test backend
if curl -sf http://localhost:8000/up > /dev/null; then
    echo "✓ Backend API responding"
else
    echo "✗ Backend API không phản hồi"
    echo "Backend logs:"
    docker compose logs backend | tail -n 20
    exit 1
fi

# Test frontend
if curl -sf http://localhost:5173 > /dev/null; then
    echo "✓ Frontend responding"
else
    echo "✗ Frontend không phản hồi"
    echo "Frontend logs:"
    docker compose logs frontend | tail -n 20
    exit 1
fi

# Test mailpit
if curl -sf http://localhost:8025 > /dev/null; then
    echo "✓ Mailpit responding"
else
    echo "✗ Mailpit không phản hồi"
    exit 1
fi

# Test database
echo ""
echo "Test 4: Kiểm tra database"
echo "--------------------------"

if docker compose exec -T db psql -U hsmart -d hsmart -c "SELECT 1" > /dev/null 2>&1; then
    echo "✓ Database kết nối thành công"
else
    echo "✗ Không thể kết nối database"
    exit 1
fi

# Kiểm tra migrations
table_count=$(docker compose exec -T db psql -U hsmart -d hsmart -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public' AND table_type='BASE TABLE';" | tr -d ' ')

if [ "$table_count" -gt 0 ]; then
    echo "✓ Database có $table_count bảng (migrations đã chạy)"
else
    echo "✗ Database không có bảng nào"
    exit 1
fi

# Test API endpoints
echo ""
echo "Test 5: Kiểm tra API endpoints"
echo "--------------------------------"

# Test health endpoint
response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/up)
if [ "$response" = "200" ]; then
    echo "✓ GET /up: 200 OK"
else
    echo "✗ GET /up: $response"
fi

# Test API v1 (should return 404 for root, not 500)
response=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/v1/)
if [ "$response" = "404" ] || [ "$response" = "200" ]; then
    echo "✓ GET /api/v1/: $response"
else
    echo "⚠ GET /api/v1/: $response (unexpected)"
fi

# Kiểm tra logs
echo ""
echo "Test 6: Kiểm tra logs"
echo "----------------------"

error_count=$(docker compose logs | grep -i "error\|exception\|fatal" | grep -v "error_log\|error_reporting" | wc -l)

if [ "$error_count" -eq 0 ]; then
    echo "✓ Không có lỗi trong logs"
else
    echo "⚠ Phát hiện $error_count dòng lỗi trong logs"
    echo "Chi tiết:"
    docker compose logs | grep -i "error\|exception\|fatal" | grep -v "error_log\|error_reporting" | head -n 10
fi

# Test dọn dẹp
echo ""
echo "Test 7: Test dừng và dọn dẹp"
echo "-----------------------------"

docker compose down -v > /dev/null 2>&1

if ! docker compose ps | grep -q "running"; then
    echo "✓ Dừng thành công"
else
    echo "✗ Vẫn còn containers chạy"
    docker compose ps
    exit 1
fi

# Tổng kết
echo ""
echo "========================================"
echo "  ✓ TẤT CẢ TESTS PASSED"
echo "========================================"
echo ""
echo "Deployment package hoạt động tốt!"
echo ""
