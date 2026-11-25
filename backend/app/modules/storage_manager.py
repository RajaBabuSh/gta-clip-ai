# backend/app/modules/storage_manager.py
import shutil
from pathlib import Path
from typing import Dict, Any

class StorageManager:
    """Manages disk usage, media cleanup, and storage limits."""

    @staticmethod
    def get_disk_metrics(path: Path) -> Dict[str, float]:
        try:
            total, used, free = shutil.disk_usage(path)
            return {
                "total_gb": round(total / (1024**3), 2),
                "used_gb": round(used / (1024**3), 2),
                "free_gb": round(free / (1024**3), 2)
            }
        except Exception:
            return {"total_gb": 0.0, "used_gb": 0.0, "free_gb": 0.0}
