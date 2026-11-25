@echo off
title GTA CLIP AI ULTIMATE - Master Launcher
echo ================================================================
echo Launching GTA CLIP AI ULTIMATE Platform
echo ================================================================

cd /d "%~dp0"

echo [1/4] Checking MySQL & Apache status...
netstat -ano | findstr ":80 " >nul
if %errorlevel% neq 0 (
    echo [!] Apache does not appear to be running on port 80.
    echo     Please start Apache in the XAMPP Control Panel.
) else (
    echo [✓] Apache web server is active on port 80.
)

netstat -ano | findstr ":3306 " >nul
if %errorlevel% neq 0 (
    echo [!] MySQL does not appear to be running on port 3306.
    echo     Please start MySQL in the XAMPP Control Panel.
) else (
    echo [✓] MySQL database server is active on port 3306.
)

echo.
echo [2/4] Starting AI GPU Worker (FastAPI on http://127.0.0.1:8000)...
start "GTA CLIP AI - Remote AI Worker (Port 8000)" cmd /c "call "%~dp0start_backend.bat""

echo.
echo [3/4] Starting Frontend Server (Next.js on http://localhost:3000)...
start "GTA CLIP AI - Frontend (Port 3000)" cmd /c "call "%~dp0start_frontend.bat""

echo Waiting for servers to initialize...
timeout /t 5 /nobreak >nul

echo.
echo [4/4] Opening GTA CLIP AI in browser...
start http://localhost:3000
start http://localhost/New%20folder/

echo.
echo ================================================================
echo GTA CLIP AI ULTIMATE is now active!
echo.
echo Next.js Frontend:     http://localhost:3000
echo Core PHP Platform:    http://localhost/New folder/
echo Admin Portal:         http://localhost/New folder/admin/login.php
echo AI GPU Worker API:    http://127.0.0.1:8000
echo.
echo Default User Account:  user@gtaclip.ai  / User@12345
echo Master Admin Account:  admin@gtaclip.ai / Admin@12345
echo ================================================================
echo.
pause
