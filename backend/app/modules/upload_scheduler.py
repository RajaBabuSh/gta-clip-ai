# backend/app/modules/upload_scheduler.py
from datetime import datetime
from typing import Dict, Any

class UploadSchedulerEngine:
    """Evaluates slot timings and manages automated publishing triggers."""

    @staticmethod
    def is_slot_due(target_time_str: str, current_time: datetime) -> bool:
        try:
            target = datetime.strptime(target_time_str, "%H:%M:%S").time()
            return current_time.hour == target.hour and abs(current_time.minute - target.minute) <= 5
        except Exception:
            return False
