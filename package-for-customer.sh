#!/bin/bash
# Script để đóng gói ứng dụng cho khách hàng

set -euo pipefail

VERSION=${1:-"1.0"}
OUTPUT_DIR="./dist"
PACKAGE_NAME="h-smart-docker-v${VERSION}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)

echo "========================================"
echo "  Đóng gói H-Smart cho khách hàng"
echo "  Phiên bản: ${VERSION}"
echo "========================================"
echo ""

# Tạo thư mục output
mkdir -p "${OUTPUT_DIR}"

# Kiểm tra git clean
if ! git diff-index --quiet HEAD -- 2>/dev/null; then
    echo "⚠️  Cảnh báo: Có thay đổi chưa commit trong git"
    read -p "Tiếp tục? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Clean build artifacts
echo "1. Dọn dẹp build artifacts..."
rm -rf backend/vendor
rm -rf frontend/node_modules
rm -rf backend/storage/logs/*.log
rm -rf backend/storage/framework/cache/*
rm -rf backend/storage/framework/sessions/*
rm -rf backend/storage/framework/views/*
echo "   ✓ Đã dọn dẹp"

# Tạo archive
echo ""
echo "2. Tạo archive..."

tar -czf "${OUTPUT_DIR}/${PACKAGE_NAME}.tar.gz" \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='.env' \
  --exclude='backend/storage/app/public/*' \
  --exclude='backend/storage/logs/*' \
  --exclude='backend/storage/framework/cache/*' \
  --exclude='backend/storage/framework/sessions/*' \
  --exclude='backend/storage/framework/views/*' \
  --exclude='dist' \
  --exclude='*.log' \
  --exclude='.DS_Store' \
  --exclude='Thumbs.db' \
  --transform "s,^,${PACKAGE_NAME}/," \
  .

echo "   ✓ Đã tạo ${PACKAGE_NAME}.tar.gz"

# Tạo checksum
echo ""
echo "3. Tạo checksum..."
cd "${OUTPUT_DIR}"
sha256sum "${PACKAGE_NAME}.tar.gz" > "${PACKAGE_NAME}.tar.gz.sha256"
CHECKSUM=$(cat "${PACKAGE_NAME}.tar.gz.sha256" | cut -d' ' -f1)
cd ..
echo "   ✓ SHA256: ${CHECKSUM}"

# Tạo file thông tin giao hàng
echo ""
echo "4. Tạo file thông tin giao hàng..."
cat > "${OUTPUT_DIR}/GIAO_HANG.txt" << EOF
H-SMART - PHẦN MỀM QUẢN LÝ MUA BÁN
====================================

Phiên bản: ${VERSION}
Ngày đóng gói: $(date +"%Y-%m-%d %H:%M:%S")
Package: ${PACKAGE_NAME}.tar.gz

NỘI DUNG GÓI
------------
- Source code đầy đủ backend (Laravel 13) và frontend (React 19)
- Docker Compose configuration
- Scripts tự động khởi động cho Windows, Linux và macOS
- Tài liệu hướng dẫn triển khai đầy đủ

YÊU CẦU HỆ THỐNG
-----------------
- Docker Desktop 20.10+ (Windows/Mac) hoặc Docker Engine 20.10+ (Linux)
- Docker Compose 2.0+
- RAM: Tối thiểu 2GB, khuyến nghị 4GB
- Ổ đĩa: Tối thiểu 5GB dung lượng trống
- Cổng mạng: 5173 (frontend), 8000 (backend), 8025 (email test)

HƯỚNG DẪN CÀI ĐẶT NHANH
-------------------------
1. Cài đặt Docker:
   - Windows/Mac: https://www.docker.com/products/docker-desktop/
   - Linux: curl -fsSL https://get.docker.com | sh

2. Giải nén file ${PACKAGE_NAME}.tar.gz

3. Mở terminal/PowerShell trong thư mục đã giải nén

4. Chạy script khởi động:
   - Windows: .\docker-start.ps1
   - Linux/Mac: ./docker-start.sh

5. Đợi 2-5 phút (lần đầu tiên)

6. Mở trình duyệt:
   - Ứng dụng: http://localhost:5173
   - Email test: http://localhost:8025

TÀI LIỆU
--------
- QUICKSTART.txt     : Hướng dẫn khởi động nhanh
- DOCKER_HUONG_DAN_NHANH.txt : Hướng dẫn Docker nhanh
- DOCKER_CO_BAN.txt  : Kiến thức Docker cơ bản
- DEPLOYMENT.md      : Hướng dẫn triển khai chi tiết
- DEPLOYMENT_CHECKLIST.md : Checklist triển khai
- README.md          : Tổng quan dự án

TÍNH NĂNG
---------
✓ Đăng ký tài khoản với xác thực email
✓ Đăng nhập / Đăng xuất
✓ Quên mật khẩu / Đặt lại mật khẩu
✓ Đổi mật khẩu
✓ Đăng bán sản phẩm (với ảnh)
✓ Xem danh sách sản phẩm
✓ Xem chi tiết sản phẩm
✓ Quản lý sản phẩm của mình

BẢO MẬT
-------
- Mật khẩu được băm Argon2id
- JWT token với refresh mechanism
- Email verification bắt buộc
- Session management an toàn

CHECKSUM
--------
SHA256: ${CHECKSUM}

Để kiểm tra tính toàn vẹn:
  sha256sum -c ${PACKAGE_NAME}.tar.gz.sha256

HỖ TRỢ
------
Nếu gặp vấn đề khi cài đặt hoặc sử dụng, vui lòng:
1. Kiểm tra file DEPLOYMENT.md phần "Xử lý sự cố"
2. Kiểm tra logs: docker compose logs
3. Liên hệ đội ngũ phát triển

====================================
© 2026 H-Smart Team
====================================
EOF

echo "   ✓ Đã tạo GIAO_HANG.txt"

# Tạo file checksums tổng hợp
echo ""
echo "5. Tạo checksums tổng hợp..."
cd "${OUTPUT_DIR}"
sha256sum * > "CHECKSUMS-${PACKAGE_NAME}.txt" 2>/dev/null || true
cd ..

# Thống kê
FILESIZE=$(du -h "${OUTPUT_DIR}/${PACKAGE_NAME}.tar.gz" | cut -f1)

echo ""
echo "========================================"
echo "  ✓ Đóng gói hoàn tất!"
echo "========================================"
echo ""
echo "Files đã tạo:"
echo "  • ${PACKAGE_NAME}.tar.gz (${FILESIZE})"
echo "  • ${PACKAGE_NAME}.tar.gz.sha256"
echo "  • GIAO_HANG.txt"
echo "  • CHECKSUMS-${PACKAGE_NAME}.txt"
echo ""
echo "Thư mục: ${OUTPUT_DIR}/"
echo ""
echo "Bước tiếp theo:"
echo "  1. Test gói trên máy sạch"
echo "  2. Kiểm tra DEPLOYMENT_CHECKLIST.md"
echo "  3. Bàn giao cho khách hàng"
echo ""
