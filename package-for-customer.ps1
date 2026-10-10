# Script để đóng gói ứng dụng cho khách hàng (Windows)

param(
    [string]$Version = "1.0"
)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

$OutputDir = ".\dist"
$PackageName = "h-smart-docker-v$Version"
$Timestamp = Get-Date -Format "yyyyMMdd-HHmmss"

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Đóng gói H-Smart cho khách hàng" -ForegroundColor Cyan
Write-Host "  Phiên bản: $Version" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Kiểm tra 7-Zip hoặc tar
$use7zip = $false
$zipCmd = ""

if (Get-Command "7z" -ErrorAction SilentlyContinue) {
    $use7zip = $true
    $zipCmd = "7z"
    Write-Host "Sử dụng 7-Zip" -ForegroundColor Green
} elseif (Get-Command "tar" -ErrorAction SilentlyContinue) {
    $zipCmd = "tar"
    Write-Host "Sử dụng tar (Git Bash)" -ForegroundColor Green
} else {
    Write-Host "Cảnh báo: Không tìm thấy 7-Zip hoặc tar." -ForegroundColor Yellow
    Write-Host "Sẽ sử dụng Compress-Archive (chậm hơn)" -ForegroundColor Yellow
}

# Tạo thư mục output
if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir | Out-Null
}

# Kiểm tra git status
$gitStatus = git status --porcelain 2>$null
if ($gitStatus) {
    Write-Host "⚠️  Cảnh báo: Có thay đổi chưa commit trong git" -ForegroundColor Yellow
    $response = Read-Host "Tiếp tục? (y/n)"
    if ($response -ne 'y') {
        exit 1
    }
}

# Dọn dẹp
Write-Host ""
Write-Host "1. Dọn dẹp build artifacts..." -ForegroundColor Yellow

$cleanDirs = @(
    "backend\vendor",
    "frontend\node_modules",
    "backend\storage\framework\cache",
    "backend\storage\framework\sessions",
    "backend\storage\framework\views"
)

foreach ($dir in $cleanDirs) {
    if (Test-Path $dir) {
        Remove-Item -Path $dir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

# Xóa log files
Get-ChildItem -Path "backend\storage\logs" -Filter "*.log" -ErrorAction SilentlyContinue | Remove-Item -Force

Write-Host "   ✓ Đã dọn dẹp" -ForegroundColor Green

# Tạo danh sách files cần exclude
$excludePatterns = @(
    ".git",
    "node_modules",
    "vendor",
    ".env",
    "backend\storage\app\public\*",
    "backend\storage\logs\*.log",
    "dist",
    "*.log",
    ".DS_Store",
    "Thumbs.db"
)

# Tạo archive
Write-Host ""
Write-Host "2. Tạo archive..." -ForegroundColor Yellow

$archivePath = Join-Path $OutputDir "$PackageName.zip"

if ($zipCmd -eq "tar") {
    # Sử dụng tar
    $excludeArgs = $excludePatterns | ForEach-Object { "--exclude=$_" }
    & tar -czf $archivePath @excludeArgs .

    if ($LASTEXITCODE -eq 0) {
        Write-Host "   ✓ Đã tạo $PackageName.zip" -ForegroundColor Green
    } else {
        Write-Host "   ✗ Lỗi khi tạo archive" -ForegroundColor Red
        exit 1
    }
} elseif ($use7zip) {
    # Sử dụng 7-Zip
    $excludeArgs = $excludePatterns | ForEach-Object { "-xr!$_" }
    & 7z a -tzip $archivePath . @excludeArgs -mx9 | Out-Null

    if ($LASTEXITCODE -eq 0) {
        Write-Host "   ✓ Đã tạo $PackageName.zip" -ForegroundColor Green
    } else {
        Write-Host "   ✗ Lỗi khi tạo archive" -ForegroundColor Red
        exit 1
    }
} else {
    # Sử dụng Compress-Archive
    $tempDir = ".\temp-package-$Timestamp"
    New-Item -ItemType Directory -Path $tempDir | Out-Null

    # Copy files
    Copy-Item -Path ".\*" -Destination $tempDir -Recurse -Exclude $excludePatterns

    # Compress
    Compress-Archive -Path "$tempDir\*" -DestinationPath $archivePath -CompressionLevel Optimal

    # Cleanup temp
    Remove-Item -Path $tempDir -Recurse -Force

    Write-Host "   ✓ Đã tạo $PackageName.zip" -ForegroundColor Green
}

# Tạo checksum
Write-Host ""
Write-Host "3. Tạo checksum..." -ForegroundColor Yellow

$hash = Get-FileHash -Path $archivePath -Algorithm SHA256
$checksum = $hash.Hash.ToLower()

$checksumFile = "$archivePath.sha256"
"$checksum  $PackageName.zip" | Out-File -FilePath $checksumFile -Encoding ASCII -NoNewline

Write-Host "   ✓ SHA256: $checksum" -ForegroundColor Green

# Tạo file thông tin
Write-Host ""
Write-Host "4. Tạo file thông tin giao hàng..." -ForegroundColor Yellow

$giaohangContent = @"
H-SMART - PHẦN MỀM QUẢN LÝ MUA BÁN
====================================

Phiên bản: $Version
Ngày đóng gói: $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")
Package: $PackageName.zip

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

2. Giải nén file $PackageName.zip

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
SHA256: $checksum

Để kiểm tra tính toàn vẹn (PowerShell):
  `$hash = Get-FileHash $PackageName.zip -Algorithm SHA256
  `$hash.Hash.ToLower() -eq '$checksum'

HỖ TRỢ
------
Nếu gặp vấn đề khi cài đặt hoặc sử dụng, vui lòng:
1. Kiểm tra file DEPLOYMENT.md phần "Xử lý sự cố"
2. Kiểm tra logs: docker compose logs
3. Liên hệ đội ngũ phát triển

====================================
© 2026 H-Smart Team
====================================
"@

$giaohangFile = Join-Path $OutputDir "GIAO_HANG.txt"
$giaohangContent | Out-File -FilePath $giaohangFile -Encoding UTF8

Write-Host "   ✓ Đã tạo GIAO_HANG.txt" -ForegroundColor Green

# Thống kê
$fileSize = (Get-Item $archivePath).Length
$fileSizeMB = [math]::Round($fileSize / 1MB, 2)

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "  ✓ Đóng gói hoàn tất!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Files đã tạo:" -ForegroundColor Cyan
Write-Host "  • $PackageName.zip ($fileSizeMB MB)" -ForegroundColor White
Write-Host "  • $PackageName.zip.sha256" -ForegroundColor White
Write-Host "  • GIAO_HANG.txt" -ForegroundColor White
Write-Host ""
Write-Host "Thư mục: $OutputDir\" -ForegroundColor Cyan
Write-Host ""
Write-Host "Bước tiếp theo:" -ForegroundColor Yellow
Write-Host "  1. Test gói trên máy sạch" -ForegroundColor Gray
Write-Host "  2. Kiểm tra DEPLOYMENT_CHECKLIST.md" -ForegroundColor Gray
Write-Host "  3. Bàn giao cho khách hàng" -ForegroundColor Gray
Write-Host ""
