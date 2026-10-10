# Script dừng H-Smart Docker
param(
    [switch]$RemoveVolumes = $false
)

$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

Write-Host ""
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "           Dừng H-Smart Docker" -ForegroundColor Cyan
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

$useWsl = -not [bool](Get-Command docker -ErrorAction SilentlyContinue)

function Invoke-DockerCompose {
    param([string[]]$ComposeArgs)
    if ($useWsl) {
        & wsl -- docker compose @ComposeArgs
    } else {
        & docker compose @ComposeArgs
    }
}

if ($RemoveVolumes) {
    Write-Host "⚠️  CẢNH BÁO: Bạn đang xóa TẤT CẢ dữ liệu!" -ForegroundColor Red
    Write-Host "    - Database" -ForegroundColor Yellow
    Write-Host "    - Ảnh sản phẩm đã upload" -ForegroundColor Yellow
    Write-Host "    - Email trong Mailpit" -ForegroundColor Yellow
    Write-Host ""
    $confirm = Read-Host "Tiếp tục? (y/n)"

    if ($confirm -ne 'y') {
        Write-Host "Đã hủy." -ForegroundColor Gray
        exit 0
    }

    Write-Host ""
    Write-Host "Đang dừng và xóa volumes..." -ForegroundColor Yellow
    Invoke-DockerCompose @('down', '-v')

    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "✓ Đã dừng và xóa tất cả dữ liệu" -ForegroundColor Green
    }
} else {
    Write-Host "Đang dừng containers (giữ nguyên dữ liệu)..." -ForegroundColor Yellow
    Invoke-DockerCompose @('down')

    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "✓ Đã dừng containers" -ForegroundColor Green
        Write-Host ""
        Write-Host "Dữ liệu đã được giữ lại." -ForegroundColor Gray
        Write-Host "Để khởi động lại: .\docker-start.ps1" -ForegroundColor Gray
        Write-Host ""
        Write-Host "Để xóa toàn bộ dữ liệu:" -ForegroundColor Yellow
        Write-Host "  .\docker-stop.ps1 -RemoveVolumes" -ForegroundColor White
    }
}

Write-Host ""
