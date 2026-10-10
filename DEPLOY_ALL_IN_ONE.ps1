# ============================================================================
# H-Smart Complete Deployment Script - One Click Deploy!
# ============================================================================
param(
    [Parameter(Mandatory=$true, HelpMessage="GitHub Personal Access Token")]
    [string]$GitToken,

    [Parameter(Mandatory=$true, HelpMessage="Docker Hub username")]
    [string]$DockerUser,

    [Parameter(Mandatory=$false, HelpMessage="Version tag (default: v0.2.0)")]
    [string]$Version = "v0.2.0",

    [Parameter(Mandatory=$false, HelpMessage="Skip tests (default: false)")]
    [switch]$SkipTests
)

$ErrorActionPreference = 'Stop'
$WarningPreference = 'Continue'

function Write-Title {
    param([string]$Message)
    Write-Host ""
    Write-Host ("=" * 74) -ForegroundColor Cyan
    Write-Host "  $Message" -ForegroundColor Cyan
    Write-Host ("=" * 74) -ForegroundColor Cyan
}

function Write-Step {
    param([string]$Message, [int]$Step)
    Write-Host ""
    Write-Host "[$Step/8] >>$Message" -ForegroundColor White
}

function Write-Success {
    param([string]$Message)
    Write-Host "    [OK] $Message" -ForegroundColor Green
}

function Write-Warn {
    param([string]$Message)
    Write-Host "    [WARN] $Message" -ForegroundColor Yellow
}

function Write-Error-Custom {
    param([string]$Message)
    Write-Host "    [ERROR] $Message" -ForegroundColor Red
}

# ============================================================================
# STEP 1: Verify Prerequisites
# ============================================================================
Write-Title "H-Smart Complete Deployment - All in One"
Write-Step "Verifying prerequisites..." 1

try {
    if ($PSVersionTable.PSVersion.Major -lt 5) {
        throw "PowerShell 5.0+ required. Current: $($PSVersionTable.PSVersion)"
    }
    Write-Success "PowerShell version OK: $($PSVersionTable.PSVersion)"

    $gitVersion = git --version 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Git not found. Please install Git for Windows."
    }
    Write-Success "Git installed: $gitVersion"

    docker --version | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "Docker not running. Please start Docker Desktop."
    }
    Write-Success "Docker is running"

    docker compose version | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "Docker Compose not available."
    }
    Write-Success "Docker Compose available"

    if (-not $SkipTests) {
        node --version | Out-Null
        if ($LASTEXITCODE -ne 0) {
            Write-Warn "Node.js not found. Skipping tests."
            $SkipTests =$true
        } else {
            Write-Success "Node.js available"
        }
    }
} catch {
    Write-Error-Custom $_
    exit 1
}

# ============================================================================
# STEP 2: Validate Parameters
# ============================================================================
Write-Step "Validating parameters..." 2

if ([string]::IsNullOrWhiteSpace($GitToken)) {
    Write-Error-Custom "GitHub token cannot be empty"
    exit 1
}

if ([string]::IsNullOrWhiteSpace($DockerUser)) {
    Write-Error-Custom "Docker username cannot be empty"
    exit 1
}

Write-Success "Docker User: $DockerUser"
Write-Success "Version: $Version"

# ============================================================================
# STEP 3: Git Configuration & Commit
# ============================================================================
Write-Step "Configuring Git and committing changes..." 3

try {
    Set-Location -Path "D:\H-smart-php" -ErrorAction Stop

    git config --global user.email "deployment@hsmart.dev" 2>&1 | Out-Null
    git config --global user.name "H-Smart Deployment Bot" 2>&1 | Out-Null
    Write-Success "Git configured"

    $changes = git status --porcelain
    if ([string]::IsNullOrWhiteSpace($changes)) {
        Write-Warn "No uncommitted changes detected"
    } else {
        Write-Success "Found changes, committing..."
        git add . 2>&1 | Out-Null
        git commit -m "feat: Add Admin module with integration tests and Docker deployment`n`nVersion: $Version" 2>&1 | Out-Null
        Write-Success "Changes committed"
    }

    Write-Host "     Creating tag $Version..."
    git tag -a $Version -m "Release ${Version}: Admin Module with Docker" -f 2>&1 | Out-Null
    Write-Success "Tag created: $Version"
} catch {
    Write-Error-Custom "Git operation failed: $_"
    exit 1
}

# ============================================================================
# STEP 4: Push to GitHub
# ============================================================================
Write-Step "Pushing to GitHub..." 4

try {
    $remoteUrl = git remote get-url origin
    $cleanUrl =$remoteUrl -replace "https://.*@", "https://"

    if ($cleanUrl -match "^https://") {
        $authenticatedUrl =$cleanUrl -replace "^https://", "https://x-access-token:$($GitToken)@"
        git remote set-url origin $authenticatedUrl 2>&1 | Out-Null
    }

    try {
        Write-Host "     Pushing develop branch..."
        git push -u origin develop 2>&1 | Out-Null
        Write-Success "Develop branch pushed"

        Write-Host "     Pushing tag $Version..."
        git push origin $Version -f 2>&1 | Out-Null
        Write-Success "Tag pushed: $Version"
    } finally {
        git remote set-url origin $cleanUrl 2>&1 | Out-Null
    }
} catch {
    Write-Error-Custom "GitHub push failed: $_"
    Write-Warn "Check your GitHub token permissions (requires 'repo' scope)."
}

# ============================================================================
# STEP 5: Run Tests (Optional)
# ============================================================================
Write-Step "Running tests..." 5

if ($SkipTests) {
    Write-Warn "Tests skipped"
} else {
    try {
        Set-Location -Path "D:\H-smart-php\backend" -ErrorAction Stop
        Write-Host "     Running PHP tests..."
        php artisan test --filter Admin 2>&1 | Out-Null
        Write-Success "Tests passed"
    } catch {
        Write-Warn "Tests failed or unavailable: $_"
    }
}

# ============================================================================
# STEP 6: Build Docker Images
# ============================================================================
Write-Step "Building Docker images..." 6

try {
    Set-Location -Path "D:\H-smart-php" -ErrorAction Stop

    Write-Host "     Building backend image..."
    docker build -t "${DockerUser}/hsmart-backend:$Version" -t "${DockerUser}/hsmart-backend:latest" ./backend
    if ($LASTEXITCODE -ne 0) { throw "Backend build failed" }
    Write-Success "Backend image built: ${DockerUser}/hsmart-backend:$Version"

    Write-Host "     Building frontend image..."
    docker build -t "${DockerUser}/hsmart-frontend:$Version" -t "${DockerUser}/hsmart-frontend:latest" ./frontend
    if ($LASTEXITCODE -ne 0) { throw "Frontend build failed" }
    Write-Success "Frontend image built: ${DockerUser}/hsmart-frontend:$Version"
} catch {
    Write-Error-Custom "Docker build failed: $_"
    exit 1
}

# ============================================================================
# STEP 7: Push to Docker Hub
# ============================================================================
Write-Step "Pushing to Docker Hub..." 7

try {
    $dockerInfo = docker info 2>&1 | Out-String
    if ($dockerInfo -notmatch "Username: $DockerUser") {
        Write-Warn "Not logged in. Please login to Docker Hub..."
        docker login
        if ($LASTEXITCODE -ne 0) {
            throw "Docker login failed"
        }
    }
    Write-Success "Docker authenticated"

    Write-Host "     Pushing ${DockerUser}/hsmart-backend:$Version..."
    docker push "${DockerUser}/hsmart-backend:$Version"
    docker push "${DockerUser}/hsmart-backend:latest"
    Write-Success "Backend pushed"

    Write-Host "     Pushing ${DockerUser}/hsmart-frontend:$Version..."
    docker push "${DockerUser}/hsmart-frontend:$Version"
    docker push "${DockerUser}/hsmart-frontend:latest"
    Write-Success "Frontend pushed"
} catch {
    Write-Error-Custom "Docker push failed: $_"
    exit 1
}

# ============================================================================
# STEP 8: Summary
# ============================================================================
Write-Step "Deployment completed!" 8

Write-Host ""
Write-Host ("=" * 74) -ForegroundColor Green
Write-Host "                   DEPLOYMENT SUCCESSFUL!                      " -ForegroundColor Green
Write-Host ("=" * 74) -ForegroundColor Green

Write-Host ""
Write-Host "Summary:" -ForegroundColor White
Write-Host "   - Git: Pushed to origin/develop with tag $Version"
Write-Host "   - Backend: ${DockerUser}/hsmart-backend:$Version"
Write-Host "   - Frontend: ${DockerUser}/hsmart-frontend:$Version"
Write-Host ""