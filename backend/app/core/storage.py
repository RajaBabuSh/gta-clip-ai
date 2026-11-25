"""
Storage Manager for Python AI Worker
Handles local structured file paths and optional Cloudflare R2 / S3 object upload.
"""
import os
import shutil
import logging
from pathlib import Path
from typing import Optional, Dict, Any

from app.core.config import settings
from app.core.paths import get_storage_path, resolve_media_path

logger = logging.getLogger("gtaclip.storage")

class StorageDriver:
    LOCAL = "local"
    R2 = "cloudflare_r2"

def get_storage_driver() -> str:
    return os.environ.get("STORAGE_DRIVER", StorageDriver.LOCAL)

def ensure_storage_dirs():
    """Ensure all required storage directories exist."""
    dirs = [
        get_storage_path("uploads"),
        get_storage_path("clips"),
        get_storage_path("audio"),
        get_storage_path("subtitles"),
        get_storage_path("temp"),
    ]
    for d in dirs:
        d.mkdir(parents=True, exist_ok=True)

def store_rendered_clip(local_file_path: str, clip_filename: Optional[str] = None) -> Dict[str, Any]:
    """
    Ensure the clip is stored in the correct clips storage directory.
    Returns relative path and storage metadata.
    """
    ensure_storage_dirs()
    src_path = Path(local_file_path)
    if not src_path.exists():
        raise FileNotFoundError(f"Rendered clip not found: {local_file_path}")

    filename = clip_filename or src_path.name
    clips_dir = get_storage_path("clips")
    dest_path = clips_dir / filename

    # If src is not already dest, copy/move it
    if src_path.resolve() != dest_path.resolve():
        shutil.copy2(str(src_path), str(dest_path))

    file_size = dest_path.stat().st_size
    rel_path = f"backend/storage/clips/{filename}"

    return {
        "driver": get_storage_driver(),
        "local_path": str(dest_path),
        "file_path": rel_path,
        "file_size": file_size,
        "filename": filename
    }

def purge_job_temp_artifacts(job_id: str):
    """Clean up temporary audio wav files and slice chunks for a specific job."""
    try:
        storage_root = get_storage_path()
        audio_dir = storage_root / "audio"
        temp_dir = storage_root / "temp"

        # Remove audio files matching job_id
        for d in [audio_dir, temp_dir]:
            if d.exists():
                for f in d.glob(f"*{job_id}*"):
                    try:
                        if f.is_file():
                            f.unlink(missing_ok=True)
                            logger.info(f"Purged temp artifact: {f.name}")
                    except Exception as e:
                        logger.warning(f"Could not purge {f.name}: {e}")
    except Exception as ex:
        logger.warning(f"Error during temp artifact purge for job {job_id}: {ex}")
