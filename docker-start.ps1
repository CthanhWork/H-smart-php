$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Chua tim thay Docker. Hay cai Docker Desktop, mo Docker Desktop, roi chay lai script.'
}

docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose chua san sang. Hay mo Docker Desktop va thu lai.'
}

if (-not (Test-Path -LiteralPath '.env')) {
    $random = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $bytes = New-Object byte[] 32
        $random.GetBytes($bytes)
        $password = -join ($bytes | ForEach-Object { $_.ToString('x2') })
    } finally {
        $random.Dispose()
    }
    [System.IO.File]::WriteAllText((Join-Path $PSScriptRoot '.env'), "DB_PASSWORD=$password`n", [System.Text.Encoding]::ASCII)
}

if (-not (Select-String -LiteralPath '.env' -Pattern '^DB_PASSWORD=.+$' -Quiet)) {
    throw 'File .env o thu muc goc can co dong DB_PASSWORD voi mat khau PostgreSQL.'
}

docker compose up --build --wait
if ($LASTEXITCODE -ne 0) {
    throw 'Khoi dong Docker that bai. Xem loi o tren va chay docker compose logs.'
}

docker compose ps
Write-Host 'Giao dien: http://localhost:5173'
Write-Host 'Hop thu thu nghiem: http://localhost:8025'
