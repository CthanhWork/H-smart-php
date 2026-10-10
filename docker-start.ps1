$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

Write-Host ""
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "           H-Smart Docker Deployment" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

# Kiểm tra Docker
$useWsl = -not [bool](Get-Command docker -ErrorAction SilentlyContinue)
if ($useWsl -and -not (Get-Command wsl -ErrorAction SilentlyContinue)) {
    Write-Host "Loi: Chua tim thay Docker." -ForegroundColor Red
    Write-Host "Vui long cai Docker Desktop hoac Docker trong WSL."
    Write-Host "Tai tai: https://www.docker.com/products/docker-desktop/"
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
Invoke-DockerCompose @('version') | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Loi: Docker Compose chua san sang." -ForegroundColor Red
    Write-Host "Hay khoi dong Docker Engine roi thu lai."
    exit 1
}

# Tạo file .env nếu chưa có
if (-not (Test-Path -LiteralPath '.env')) {
    Write-Host "Tao file cau hinh .env..." -ForegroundColor Yellow
    $random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $bytes = New-Object byte[] 32
        $random.GetBytes($bytes)
        $password = -join ($bytes | ForEach-Object { $_.ToString('x2') })
    } finally {
        $random.Dispose()
    }
    [System.IO.File]::WriteAllText((Join-Path $PSScriptRoot '.env'), "DB_PASSWORD=$password`n", [System.Text.Encoding]::ASCII)
    Write-Host "Da tao .env voi mat khau database ngau nhien." -ForegroundColor Green
}

# Kiểm tra DB_PASSWORD
if (-not (Select-String -LiteralPath '.env' -Pattern '^DB_PASSWORD=.+$' -Quiet)) {
    Write-Host "Loi: File .env can co dong DB_PASSWORD voi mat khau PostgreSQL." -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Dang khoi dong Docker containers..." -ForegroundColor Yellow
Write-Host "(Lan dau tien se mat 2-5 phut de build images)" -ForegroundColor Gray
Write-Host ""

# Khởi động Docker Compose
if ($useWsl) {
    Write-Host "Docker dang chay trong WSL." -ForegroundColor Gray
    Write-Host "Giu cua so PowerShell nay mo; nhan Ctrl+C de dung." -ForegroundColor Gray
    Write-Host ""
    Invoke-DockerCompose @('up', '--build', '--quiet-build')
} else {
    Invoke-DockerCompose @('up', '--build', '--wait')
}

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "Loi: Khoi dong Docker that bai." -ForegroundColor Red
    Write-Host "Xem log chi tiet:" -ForegroundColor Yellow
    Write-Host "  docker compose logs" -ForegroundColor White
    exit 1
}

# Hiển thị thông tin thành công
Write-Host ""
Write-Host "===================================================" -ForegroundColor Green
Write-Host "        ✓ Khoi dong thanh cong!" -ForegroundColor Green
Write-Host "===================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Truy cap ung dung:" -ForegroundColor Cyan
Write-Host "  • Giao dien web:     http://localhost:5173" -ForegroundColor White
Write-Host "  • Email thu nghiem:  http://localhost:8025" -ForegroundColor White
Write-Host "  • Backend API:       http://localhost:8000" -ForegroundColor White
Write-Host ""
Write-Host "Huong dan su dung:" -ForegroundColor Cyan
Write-Host "  1. Mo http://localhost:5173 va chon 'Dang ky'" -ForegroundColor Gray
Write-Host "  2. Mo http://localhost:8025 de xem email xac thuc" -ForegroundColor Gray
Write-Host "  3. Nhan lien ket trong email de xac thuc tai khoan" -ForegroundColor Gray
Write-Host "  4. Quay lai giao dien va dang nhap" -ForegroundColor Gray
Write-Host ""
Write-Host "Quan ly Docker:" -ForegroundColor Cyan
Write-Host "  • Dung ung dung:  docker compose down" -ForegroundColor White
Write-Host "  • Xem log:        docker compose logs -f" -ForegroundColor White
Write-Host ""
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

# Hiển thị trạng thái
Invoke-DockerCompose @('ps')
