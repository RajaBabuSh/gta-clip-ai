@echo off
title GTA CLIP AI - AI Worker Server (FastAPI Port 8000)
cd /d "%~dp0..\backend"
echo ===================================================
echo Starting GTA CLIP AI Worker on http://127.0.0.1:8000
echo ===================================================

:RUN_LOOP
echo [%TIME%] Starting FastAPI Worker on http://127.0.0.1:8000...
python main.py
echo.
echo [%TIME%] AI Worker process exited with code %errorlevel%.
echo [%TIME%] Auto-restarting in 3 seconds (Press Ctrl+C to abort)...
timeout /t 3 /nobreak >nul
goto RUN_LOOP
