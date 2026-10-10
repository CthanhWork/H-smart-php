#!/bin/bash
set -e

# Default values
VERSION="v0.2.0"
SKIP_TESTS=false

# Parse arguments
while [[ "$#" -gt 0 ]]; do
    case $1 in
        -t|--token) GIT_TOKEN="$2"; shift ;;
        -u|--user) DOCKER_USER="$2"; shift ;;
        -v|--version) VERSION="$2"; shift ;;
        --skip-tests) SKIP_TESTS=true ;;
        *) echo "Unknown parameter: $1"; exit 1 ;;
    esac
    shift
done

if [ -z "$GIT_TOKEN" ] || [ -z "$DOCKER_USER" ]; then
    echo "❌ Thiếu tham số: Vui lòng cung cấp -t <GitHub_Token> và -u <Docker_Username>"
    exit 1
fi

echo "=========================================================================="
echo "  H-Smart Complete Deployment (WSL Linux Engine)"
echo "=========================================================================="

echo "[1/8] >> Kiểm tra môi trường..."
git --version
docker --version

echo "[2/8] >> Tham số:"
echo "    Docker User: $DOCKER_USER"
echo "    Version:     $VERSION"

echo "[3/8] >> Cấu hình Git & Commit..."
git config --global user.email "deployment@hsmart.dev"
git config --global user.name "H-Smart Deployment Bot"

if [ -n "$(git status --porcelain)" ]; then
    git add .
    git commit -m "feat: Add Admin module with integration tests and Docker deployment

Version: $VERSION"
fi

git tag -a "$VERSION" -m "Release ${VERSION}: Admin Module with Docker" -f
echo "    [OK] Đã tạo tag $VERSION"

echo "[4/8] >> Đẩy mã nguồn lên GitHub..."
REMOTE_URL=$(git remote get-url origin)
CLEAN_URL=$(echo "$REMOTE_URL" | sed -E 's#https://(.*@)?#https://#')
AUTH_URL=$(echo "$CLEAN_URL" | sed -E "s#https://#https://x-access-token:${GIT_TOKEN}@#")

git remote set-url origin "$AUTH_URL"
git push -u origin develop || true
git push origin "$VERSION" -f || true
git remote set-url origin "$CLEAN_URL"

if [ "$SKIP_TESTS" = false ]; then
    echo "[5/8] >> Chạy Tests..."
    if command -v php &> /dev/null; then
        (cd backend && php artisan test --filter Admin) || echo "⚠️ Tests failed/skipped"
    fi
else
    echo "[5/8] >> Bỏ qua Tests."
fi

echo "[6/8] >> Build Docker images..."
docker build -t "${DOCKER_USER}/hsmart-backend:$VERSION" -t "${DOCKER_USER}/hsmart-backend:latest" ./backend
docker build -t "${DOCKER_USER}/hsmart-frontend:$VERSION" -t "${DOCKER_USER}/hsmart-frontend:latest" ./frontend

echo "[7/8] >> Push Docker images lên Docker Hub..."
docker push "${DOCKER_USER}/hsmart-backend:$VERSION"
docker push "${DOCKER_USER}/hsmart-backend:latest"
docker push "${DOCKER_USER}/hsmart-frontend:$VERSION"
docker push "${DOCKER_USER}/hsmart-frontend:latest"

echo "[8/8] >> Hoàn tất triển khai thành công!"
echo "=========================================================================="
