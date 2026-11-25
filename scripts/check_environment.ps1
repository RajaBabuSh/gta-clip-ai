# ====================================================================
# GTA CLIP AI - Environment Diagnostic & Verification Script
# ====================================================================

Write-Host ""
Write-Host "=====================================================" -ForegroundColor Magenta
Write-Host "        GTA CLIP AI - HARDWARE & TOOL DIAGNOSTIC      " -ForegroundColor Yellow
Write-Host "=====================================================" -ForegroundColor Magenta
Write-Host ""

$allPassed = $true

# 1. Python Check
Write-Host "[1/5] Checking Python..." -NoNewline
try {
    $pythonVersion = python --version 2>&1
    Write-Host " OK ($pythonVersion)" -ForegroundColor Green
} catch {
    Write-Host " FAILED (Python not found in PATH)" -ForegroundColor Red
    $allPassed = $false
}

# 2. Node.js & npm Check
Write-Host "[2/5] Checking Node.js & npm..." -NoNewline
try {
    $nodeVersion = node --version 2>&1
    $npmVersion = npm --version 2>&1
    Write-Host " OK (Node $nodeVersion, npm $npmVersion)" -ForegroundColor Green
} catch {
    Write-Host " FAILED (Node.js or npm not found in PATH)" -ForegroundColor Red
    $allPassed = $false
}

# 3. FFmpeg & NVENC Check
Write-Host "[3/5] Checking FFmpeg & NVENC Hardware Encoder..." -NoNewline
try {
    $ffmpegVersion = ffmpeg -version 2>&1 | Select-Object -First 1
    $nvenc = ffmpeg -encoders 2>&1 | Select-String "h264_nvenc"
    if ($nvenc) {
        Write-Host " OK (FFmpeg with NVIDIA NVENC support)" -ForegroundColor Green
    } else {
        Write-Host " WARNING (FFmpeg found, but h264_nvenc missing - CPU fallback active)" -ForegroundColor Yellow
    }
} catch {
    Write-Host " FAILED (FFmpeg not found in PATH)" -ForegroundColor Red
    $allPassed = $false
}

# 4. NVIDIA GPU (nvidia-smi) Check
Write-Host "[4/5] Checking NVIDIA RTX GPU..." -NoNewline
try {
    $gpuName = nvidia-smi --query-gpu=name,memory.total --format=csv,noheader 2>&1 | Select-Object -First 1
    Write-Host " OK ($gpuName)" -ForegroundColor Green
} catch {
    Write-Host " WARNING (nvidia-smi not available. CPU mode will be used)" -ForegroundColor Yellow
}

# 5. MySQL Database Check
Write-Host "[5/5] Checking MySQL Database connection on port 3306..." -NoNewline
try {
    $tcp = Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -WarningAction SilentlyContinue
    if ($tcp.TcpTestSucceeded) {
        Write-Host " OK (Port 3306 open)" -ForegroundColor Green
    } else {
        Write-Host " FAILED (MySQL port 3306 is not listening. Start XAMPP MySQL)" -ForegroundColor Red
        $allPassed = $false
    }
} catch {
    Write-Host " FAILED (Unable to test MySQL connection)" -ForegroundColor Red
    $allPassed = $false
}

Write-Host ""
if ($allPassed) {
    Write-Host "All core systems are operational and ready for GTA CLIP AI!" -ForegroundColor Green
} else {
    Write-Host "Some dependencies require attention before proceeding." -ForegroundColor Red
}
Write-Host ""
