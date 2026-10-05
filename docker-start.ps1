$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot

$useWsl = -not [bool](Get-Command docker -ErrorAction SilentlyContinue)
if ($useWsl -and -not (Get-Command wsl -ErrorAction SilentlyContinue)) {
    throw 'Chua tim thay Docker. Hay cai Docker Desktop hoac Docker trong WSL.'
}

function Invoke-DockerCompose {
    param([string[]]$ComposeArgs)
    if ($useWsl) {
        & wsl -- docker compose @ComposeArgs
    } else {
        & docker compose @ComposeArgs
    }
}

Invoke-DockerCompose @('version') | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose chua san sang. Hay khoi dong Docker Engine roi thu lai.'
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

Write-Host 'Giao dien: http://localhost:5173'
Write-Host 'Hop thu thu nghiem: http://localhost:8025'

if ($useWsl) {
    Write-Host 'Docker dang chay trong WSL. Giu cua so PowerShell nay mo; nhan Ctrl+C de dung.'
    Invoke-DockerCompose @('up', '--build', '--quiet-build')
} else {
    Invoke-DockerCompose @('up', '--build', '--wait')
    if ($LASTEXITCODE -eq 0) {
        Invoke-DockerCompose @('ps')
    }
}

if ($LASTEXITCODE -ne 0) {
    throw 'Khoi dong Docker that bai. Xem log container de tim nguyen nhan.'
}
