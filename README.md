# GTA CLIP AI

AI-Powered Gaming Highlights & Vertical Shorts Studio optimized for Windows 11 with NVIDIA GPU hardware acceleration (RTX 3050 6GB VRAM) and CPU fallback.

---

## Architecture & Technology Stack

- **Frontend:** Next.js 16 (App Router) + TypeScript + Tailwind CSS
- **Backend:** Python + FastAPI + Uvicorn + SQLAlchemy + PyMySQL
- **Video & Audio Processing:** FFmpeg 9.0.2 with NVIDIA NVENC (`h264_nvenc`) & FFprobe
- **AI Speech Recognition:** OpenAI Whisper
- **Database:** MySQL 8.0 (Local XAMPP on port 3306)
- **Host OS:** Windows 11 (Intel Core i5 13th Gen, RTX 3050 6GB VRAM, 16GB RAM)

---

## Directory Structure

```text
├── backend/
│   ├── app/
│   │   ├── api/v1/endpoints/ # Health, Videos, Jobs endpoints
│   │   ├── core/             # Configuration, Database engine, Hardware detector
│   │   ├── models/           # SQLAlchemy models (Video, ProcessingJob, Clip)
│   │   ├── schemas/          # Pydantic validation schemas
│   │   └── services/         # FFprobe stream inspector, Modular YouTube service
│   ├── storage/              # Local NVMe buffer (uploads, audio, subtitles, output)
│   ├── requirements.txt
│   ├── .env.example
│   └── main.py
├── frontend/
│   ├── src/app/              # Next.js App Router (page.tsx, layout.tsx)
│   ├── src/components/       # UI Components (Navbar, HardwareBanner, UploadZone, etc.)
│   ├── package.json
│   └── .env.example
├── scripts/
│   ├── check_environment.bat # Windows environment diagnostic script
│   ├── check_environment.ps1 # PowerShell dependency checker
│   ├── start_backend.bat     # Launches FastAPI on port 8000
│   ├── start_frontend.bat    # Launches Next.js on port 3000
│   └── start_all.bat         # Launches both servers & opens browser
├── .env.example
└── README.md
```

---

## Quick Start (Windows 11)

### 1. Check System Dependencies
Double-click `scripts/check_environment.bat` or run:
```powershell
powershell -ExecutionPolicy Bypass -File scripts/check_environment.ps1
```

### 2. Launch Stack
Run `scripts/start_all.bat` to automatically launch the FastAPI backend, Next.js frontend, and open your browser at `http://localhost:3000`.

Or run individually:
- **Backend:** `scripts/start_backend.bat` (serves `http://127.0.0.1:8000`)
- **Frontend:** `scripts/start_frontend.bat` (serves `http://localhost:3000`)
