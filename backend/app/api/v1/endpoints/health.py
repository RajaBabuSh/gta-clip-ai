import os
import shutil
from fastapi import APIRouter
from app.core.config import settings
from app.core.database import check_db_connection
from app.core.hardware import HardwareDetector
from app.schemas.health import HealthResponse

router = APIRouter()

@router.get("/health", response_model=HealthResponse)
def get_system_health():
    """
    Returns full diagnostic of system resources, database, and hardware acceleration.
    """
    db_status = check_db_connection()
    hardware_status = HardwareDetector.get_system_diagnostic()

    # Disk space calculation
    try:
        disk = shutil.disk_usage(str(settings.STORAGE_DIR))
        disk_free_gb = round(disk.free / (1024**3), 2)
        disk_total_gb = round(disk.total / (1024**3), 2)
    except Exception:
        disk_free_gb = 0.0
        disk_total_gb = 0.0

    storage_status = {
        "upload_dir_exists": settings.upload_dir.exists(),
        "audio_dir_exists": settings.audio_dir.exists(),
        "output_dir_exists": settings.output_dir.exists(),
        "storage_root": str(settings.STORAGE_DIR),
        "disk_free_gb": disk_free_gb,
        "disk_total_gb": disk_total_gb
    }

    worker_status = {
        "online": True,
        "max_concurrent_jobs": 4,
        "queue_status": "active",
        "whisper_model": settings.WHISPER_MODEL,
        "whisper_device": hardware_status["ai_acceleration"]["whisper_device"],
        "gpu_available": hardware_status["gpu"]["detected"],
        "cuda_available": hardware_status["ai_acceleration"]["torch_cuda"],
        "ffmpeg_available": hardware_status["video_acceleration"]["ffmpeg_installed"],
        "nvenc_available": hardware_status["video_acceleration"]["nvenc_supported"]
    }

    overall_status = "healthy" if db_status["status"] == "connected" else "degraded"

    return {
        "status": overall_status,
        "project": settings.PROJECT_NAME,
        "environment": settings.ENVIRONMENT,
        "database": db_status,
        "hardware": hardware_status,
        "storage": storage_status,
        "worker": worker_status
    }
