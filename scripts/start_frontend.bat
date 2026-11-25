@echo off
title GTA CLIP AI - Frontend Server (Next.js Port 3000)
cd /d "%~dp0..\frontend"
echo ===================================================
echo Starting GTA CLIP AI Frontend on http://localhost:3000
echo ===================================================

:RUN_LOOP
echo [%TIME%] Starting Next.js Dev Server on http://localhost:3000...
call npm run dev
echo.
echo [%TIME%] Next.js frontend exited with code %errorlevel%.
echo [%TIME%] Auto-restarting in 3 seconds (Press Ctrl+C to abort)...
timeout /t 3 /nobreak >nul
goto RUN_LOOP
