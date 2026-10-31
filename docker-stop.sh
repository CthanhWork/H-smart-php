#!/bin/bash
# Script dừng H-Smart Docker

set -euo pipefail
cd "$(dirname "$0")"

REMOVE_VOLUMES=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -v|--remove-volumes)
            REMOVE_VOLUMES=true
            shift
            ;;
        *)
            echo "Usage: $0 [-v|--remove-volumes]"
            echo "  -v, --remove-volumes  Xóa tất cả dữ liệu (database, images, emails)"
            exit 1
            ;;
    esac
done

echo ""
echo "===================================================="
echo "           Dừng H-Smart Docker"
echo "===================================================="
echo ""

if [ "$REMOVE_VOLUMES" = true ]; then
    echo "⚠️  CẢNH BÁO: Bạn đang xóa TẤT CẢ dữ liệu!"
    echo "    - Database"
    echo "    - Ảnh sản phẩm đã upload"
    echo "    - Email trong Mailpit"
    echo ""
    read -p "Tiếp tục? (y/n) " -n 1 -r
    echo

    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Đã hủy."
        exit 0
    fi

    echo ""
    echo "Đang dừng và xóa volumes..."
    docker compose down -v

    if [ $? -eq 0 ]; then
        echo ""
        echo "✓ Đã dừng và xóa tất cả dữ liệu"
    fi
else
    echo "Đang dừng containers (giữ nguyên dữ liệu)..."
    docker compose down

    if [ $? -eq 0 ]; then
        echo ""
        echo "✓ Đã dừng containers"
        echo ""
        echo "Dữ liệu đã được giữ lại."
        echo "Để khởi động lại: ./docker-start.sh"
        echo ""
        echo "Để xóa toàn bộ dữ liệu:"
        echo "  ./docker-stop.sh -v"
    fi
fi

echo ""
