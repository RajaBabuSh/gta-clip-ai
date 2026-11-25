import sys
from pathlib import Path

# Add backend directory to sys.path so 'app.*' imports work from anywhere (root, backend dir, Vercel, etc.)
_BACKEND_DIR = Path(__file__).resolve().parent.parent
if str(_BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(_BACKEND_DIR))
_APP_DIR = Path(__file__).resolve().parent
if str(_APP_DIR) not in sys.path:
    sys.path.insert(0, str(_APP_DIR))

from contextlib import asynccontextmanager
import os
from fastapi import FastAPI, Request
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles

from app.core.config import settings
from app.core.database import Base, engine
from app.api.v1.router import api_router
import app.models  # Ensure all SQLAlchemy models are registered

@asynccontextmanager
async def lifespan(app: FastAPI):
    # In serverless environments (Vercel/Lambda), do NOT execute blocking DDL create_all on request startup
    is_serverless = bool(os.environ.get("VERCEL") or os.environ.get("AWS_LAMBDA_FUNCTION_NAME"))
    if not is_serverless:
        try:
            Base.metadata.create_all(bind=engine)
        except Exception as e:
            import logging
            logging.getLogger("uvicorn.error").warning(f"Database connection skipped during startup: {e}")
    # Ensure storage subdirectories exist if writable
    try:
        settings.upload_dir
        settings.audio_dir
        settings.subtitles_dir
        settings.output_dir
    except Exception as e:
        import logging
        logging.getLogger("uvicorn.error").warning(f"Storage directories init skipped: {e}")
    yield

app = FastAPI(
    title=settings.PROJECT_NAME,
    version="1.0.0",
    description="AI-powered video clipping backend optimized for Windows 11 with NVIDIA NVENC & Whisper acceleration.",
    lifespan=lifespan
)

# CORS configuration
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.ALLOWED_CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Mount local storage as static files for media playback/previews if directory exists
try:
    storage_path = settings.storage_path
    if storage_path.exists():
        app.mount("/storage", StaticFiles(directory=str(storage_path)), name="storage")
except Exception as e:
    import logging
    logging.getLogger("uvicorn.error").warning(f"Static /storage mount skipped: {e}")

# Include API v1 Router
app.include_router(api_router, prefix=settings.API_V1_STR)

from app.api.v1.endpoints.health import get_system_health

@app.get("/health")
def root_health():
    return get_system_health()

@app.get("/ready")
def root_ready():
    health = get_system_health()
    return {"ready": health["status"] == "healthy", "worker": health["worker"]}

@app.get("/worker/status")
def root_worker_status():
    health = get_system_health()
    return health["worker"]

@app.get("/", response_class=HTMLResponse)
def root(request: Request):
    # If API client explicitly requests application/json, return JSON payload
    accept = request.headers.get("accept", "")
    if "application/json" in accept and "text/html" not in accept:
        return JSONResponse({
            "project": settings.PROJECT_NAME,
            "version": "1.0.0",
            "status": "operational",
            "docs_url": "/docs",
            "health_url": f"{settings.API_V1_STR}/health"
        })

    html_content = """<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GTA Clip AI - Production AI Gateway</title>
  <style>
    :root {
      --bg: #0d0b18;
      --card: rgba(23, 20, 39, 0.85);
      --accent: #8b5cf6;
      --accent-glow: rgba(139, 92, 246, 0.25);
      --text: #ffffff;
      --muted: #94a3b8;
      --success: #10b981;
      --border: rgba(255, 255, 255, 0.08);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: radial-gradient(circle at 80% 20%, #2a1650 0%, #0d0b18 60%);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .container {
      max-width: 640px;
      width: 100%;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 36px;
      backdrop-filter: blur(20px);
      box-shadow: 0 20px 40px rgba(0,0,0,0.5), 0 0 40px var(--accent-glow);
    }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(16, 185, 129, 0.12);
      border: 1px solid rgba(16, 185, 129, 0.3);
      color: var(--success);
      font-size: 13px;
      font-weight: 600;
      padding: 4px 14px;
      border-radius: 999px;
      margin-bottom: 20px;
    }
    .badge-dot {
      width: 8px;
      height: 8px;
      background: var(--success);
      border-radius: 50%;
      animation: pulse 2s infinite;
    }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
    h1 { font-size: 28px; font-weight: 800; margin-bottom: 8px; letter-spacing: -0.5px; }
    p.subtitle { color: var(--muted); font-size: 15px; margin-bottom: 24px; line-height: 1.5; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
    .stat-card {
      background: rgba(255,255,255,0.03);
      border: 1px solid var(--border);
      padding: 16px;
      border-radius: 12px;
    }
    .stat-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
    .stat-value { font-size: 15px; font-weight: 700; color: #fff; margin-top: 4px; }
    .links { display: flex; gap: 12px; flex-wrap: wrap; }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 22px;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      border-radius: 10px;
      transition: all 0.2s ease;
    }
    .btn-primary { background: var(--accent); color: white; box-shadow: 0 4px 14px var(--accent-glow); }
    .btn-primary:hover { background: #7c3aed; }
    .btn-outline { background: rgba(255,255,255,0.05); color: white; border: 1px solid var(--border); }
    .btn-outline:hover { background: rgba(255,255,255,0.1); }
  </style>
</head>
<body>
  <div class="container">
    <div class="badge"><div class="badge-dot"></div> Production AI Gateway Active</div>
    <h1>GTA Clip AI Cloud Engine</h1>
    <p class="subtitle">High-performance AI clipping microservice. Serving real-time metadata verification, highlights detection and cloud worker orchestration.</p>
    <div class="grid">
      <div class="stat-card">
        <div class="stat-label">Service Health</div>
        <div class="stat-value" style="color:var(--success);">Operational</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Runtime Engine</div>
        <div class="stat-value">FastAPI + ASGI</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">API Gateway</div>
        <div class="stat-value">/api/v1</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Architecture</div>
        <div class="stat-value">Cloud Worker Queue</div>
      </div>
    </div>
    <div class="links">
      <a href="/docs" class="btn btn-primary">⚡ Interactive API Docs</a>
      <a href="/api/v1/health" class="btn btn-outline">🩺 Health Diagnostic</a>
    </div>
  </div>
</body>
</html>"""
    return HTMLResponse(content=html_content, status_code=200)

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app.main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.ENVIRONMENT == "development"
    )
