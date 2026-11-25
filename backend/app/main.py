from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from pathlib import Path

from app.core.config import settings
from app.core.database import Base, engine
from app.api.v1.router import api_router
import app.models  # Ensure all SQLAlchemy models are registered

@asynccontextmanager
async def lifespan(app: FastAPI):
    # Create tables automatically on startup
    Base.metadata.create_all(bind=engine)
    # Ensure storage subdirectories exist
    settings.upload_dir
    settings.audio_dir
    settings.subtitles_dir
    settings.output_dir
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

# Mount local storage as static files for media playback/previews
storage_path = Path(settings.STORAGE_DIR)
storage_path.mkdir(parents=True, exist_ok=True)
app.mount("/storage", StaticFiles(directory=str(storage_path)), name="storage")

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

@app.get("/")
def root():
    return {
        "project": settings.PROJECT_NAME,
        "version": "1.0.0",
        "status": "operational",
        "docs_url": "/docs",
        "health_url": f"{settings.API_V1_STR}/health"
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "app.main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.ENVIRONMENT == "development"
    )
