$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

Write-Host ""
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "     H-Smart - Quick Deploy (1 phút khởi động)" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

# Kiểm tra Docker
$useWsl = -not [bool](Get-Command docker -ErrorAction SilentlyContinue)
if ($useWsl -and -not (Get-Command wsl -ErrorAction SilentlyContinue)) {
    Write-Host "❌ Lỗi: Chưa tìm thấy Docker." -ForegroundColor Red
    Write-Host ""
    Write-Host "Vui lòng cài Docker Desktop hoặc Docker trong WSL." -ForegroundColor Yellow
    Write-Host "Tải tại: https://www.docker.com/products/docker-desktop/" -ForegroundColor Cyan
    Write-Host ""
    exit 1
}

function Invoke-DockerCompose {
    param([string[]]$ComposeArgs)
    if ($useWsl) {
        & wsl -- docker compose @ComposeArgs
    } else {
        & docker compose @ComposeArgs
    }
}

# Kiểm tra Docker Compose
Write-Host "🔍 Kiểm tra Docker..." -ForegroundColor Yellow
Invoke-DockerCompose @('version') | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Lỗi: Docker Compose chưa sẵn sàng." -ForegroundColor Red
    Write-Host "Hãy khởi động Docker Engine rồi thử lại." -ForegroundColor Yellow
    exit 1
}
Write-Host "✅ Docker đã sẵn sàng" -ForegroundColor Green

# Tạo file .env nếu chưa có
if (-not (Test-Path -LiteralPath '.env')) {
    Write-Host ""
    Write-Host "📝 Tạo file cấu hình .env..." -ForegroundColor Yellow
    $random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $bytes = New-Object byte[] 32
        $random.GetBytes($bytes)
        $password = -join ($bytes | ForEach-Object { $_.ToString('x2') })
    } finally {
        $random.Dispose()
    }
    [System.IO.File]::WriteAllText((Join-Path $PSScriptRoot '.env'), "DB_PASSWORD=$password`n", [System.Text.Encoding]::ASCII)
    Write-Host "✅ Đã tạo .env với mật khẩu database ngẫu nhiên" -ForegroundColor Green
}

# Kiểm tra DB_PASSWORD
if (-not (Select-String -LiteralPath '.env' -Pattern '^DB_PASSWORD=.+$' -Quiet)) {
    Write-Host "❌ Lỗi: File .env cần có dòng DB_PASSWORD với mật khẩu PostgreSQL." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🚀 Đang khởi động Docker containers..." -ForegroundColor Yellow
Write-Host "   (Lần đầu tiên sẽ mất 2-5 phút để build images)" -ForegroundColor Gray
Write-Host ""

# Khởi động Docker Compose
Invoke-DockerCompose @('up', '--build', '-d', '--wait')

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "❌ Lỗi: Khởi động Docker thất bại." -ForegroundColor Red
    Write-Host ""
    Write-Host "Xem log chi tiết:" -ForegroundColor Yellow
    Write-Host "  docker compose logs" -ForegroundColor White
    Write-Host ""
    exit 1
}

Write-Host ""
Write-Host "✅ Containers đã khởi động" -ForegroundColor Green

# Chờ backend sẵn sàng
Write-Host ""
Write-Host "⏳ Đang chờ backend sẵn sàng..." -ForegroundColor Yellow
$maxRetries = 30
$retryCount = 0
$backendReady = $false

while ($retryCount -lt $maxRetries) {
    try {
        $response = Invoke-WebRequest -Uri "http://localhost:8000/up" -TimeoutSec 2 -ErrorAction SilentlyContinue
        if ($response.StatusCode -eq 200) {
            $backendReady = $true
            break
        }
    } catch {
        # Backend chưa sẵn sàng, tiếp tục chờ
    }
    Start-Sleep -Seconds 2
    $retryCount++
    Write-Host "." -NoNewline -ForegroundColor Gray
}

Write-Host ""
if (-not $backendReady) {
    Write-Host "⚠️  Backend mất nhiều thời gian hơn dự kiến, nhưng có thể vẫn đang khởi động..." -ForegroundColor Yellow
} else {
    Write-Host "✅ Backend đã sẵn sàng" -ForegroundColor Green
}

# Tạo admin user
Write-Host ""
Write-Host "👤 Đang tạo tài khoản admin..." -ForegroundColor Yellow

$createAdminScript = @'
$admin = App\Modules\User\Models\User::where('email', 'admin@hsmart.local')->first();
if (!$admin) {
    $admin = App\Modules\User\Models\User::create([
        'username' => 'admin',
        'email' => 'admin@hsmart.local',
        'password_hash' => bcrypt('Admin@123456'),
        'role' => 'admin',
        'status' => 'active'
    ]);
    echo "ADMIN_CREATED";
} else {
    echo "ADMIN_EXISTS";
}
'@

$result = Invoke-DockerCompose @('exec', '-T', 'backend', 'php', 'artisan', 'tinker', '--execute', $createAdminScript)

if ($result -match "ADMIN_CREATED") {
    Write-Host "✅ Đã tạo tài khoản admin mới" -ForegroundColor Green
} elseif ($result -match "ADMIN_EXISTS") {
    Write-Host "ℹ️  Tài khoản admin đã tồn tại" -ForegroundColor Cyan
} else {
    Write-Host "⚠️  Không thể xác nhận tạo admin, có thể đã tồn tại hoặc cần kiểm tra logs" -ForegroundColor Yellow
}

# Hiển thị thông tin thành công
Write-Host ""
Write-Host "===================================================" -ForegroundColor Green
Write-Host "        ✅ KHỞI ĐỘNG THÀNH CÔNG!" -ForegroundColor Green
Write-Host "===================================================" -ForegroundColor Green
Write-Host ""
Write-Host "🌐 Truy cập ứng dụng:" -ForegroundColor Cyan
Write-Host "   • Giao diện web:     " -NoNewline -ForegroundColor Gray
Write-Host "http://localhost:5173" -ForegroundColor White
Write-Host "   • Email thử nghiệm:  " -NoNewline -ForegroundColor Gray
Write-Host "http://localhost:8025" -ForegroundColor White
Write-Host "   • Backend API:       " -NoNewline -ForegroundColor Gray
Write-Host "http://localhost:8000" -ForegroundColor White
Write-Host ""
Write-Host "👤 Đăng nhập Admin:" -ForegroundColor Cyan
Write-Host "   Email:    " -NoNewline -ForegroundColor Gray
Write-Host "admin@hsmart.local" -ForegroundColor Yellow
Write-Host "   Password: " -NoNewline -ForegroundColor Gray
Write-Host "Admin@123456" -ForegroundColor Yellow
Write-Host ""
Write-Host "   ⚠️  Hãy đổi mật khẩu ngay sau lần đăng nhập đầu tiên!" -ForegroundColor Red
Write-Host ""
Write-Host "📖 Hướng dẫn sử dụng:" -ForegroundColor Cyan
Write-Host "   1. Mở http://localhost:5173" -ForegroundColor Gray
Write-Host "   2. Đăng nhập bằng tài khoản admin ở trên" -ForegroundColor Gray
Write-Host "   3. Bấm nút 'Quản trị viên' để vào admin panel" -ForegroundColor Gray
Write-Host "   4. Quản lý sản phẩm và người dùng" -ForegroundColor Gray
Write-Host ""
Write-Host "🔧 Quản lý Docker:" -ForegroundColor Cyan
Write-Host "   • Dừng:       " -NoNewline -ForegroundColor Gray
Write-Host "docker compose down" -ForegroundColor White
Write-Host "   • Xem logs:   " -NoNewline -ForegroundColor Gray
Write-Host "docker compose logs -f" -ForegroundColor White
Write-Host "   • Khởi động lại: " -NoNewline -ForegroundColor Gray
Write-Host "docker compose restart" -ForegroundColor White
Write-Host ""
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

# Hiển thị trạng thái containers
Write-Host "📊 Trạng thái containers:" -ForegroundColor Cyan
Invoke-DockerCompose @('ps')

Write-Host ""
Write-Host "✨ Hệ thống đã sẵn sàng! Chúc bạn sử dụng vui vẻ!" -ForegroundColor Green
Write-Host ""
