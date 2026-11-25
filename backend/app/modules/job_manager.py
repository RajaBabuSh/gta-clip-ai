# backend/app/modules/job_manager.py
from typing import Optional, Dict, Any

class JobManager:
    """Manages job state transitions, checkpoints, and timeout monitors."""

    @staticmethod
    def get_step_description(phase: int, chunk_current: int, chunk_total: int) -> str:
        descriptions = {
            1: f"Chunk Ingestion ({chunk_current}/{chunk_total}) - Audio stream slicing",
            2: f"Whisper Speech AI ({chunk_current}/{chunk_total}) - Timestamped dialogue alignment",
            3: f"RMS Energy Peaks ({chunk_current}/{chunk_total}) - Excitement & loudness spike detection",
            4: "GPU NVENC Render - 9:16 vertical 1080x1920 MP4 encoding"
        }
        return descriptions.get(phase, "Processing media...")
