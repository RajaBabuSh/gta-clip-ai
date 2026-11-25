# backend/app/modules/highlight_detection.py
from typing import List, Dict, Any
from app.services.moment_engine import SmartMomentEngine

class HighlightDetection:
    """Multi-signal highlight extraction across audio energy, sentiment, and gaming triggers."""

    @staticmethod
    def detect_moments(whisper_result: Dict[str, Any], peaks: List[Dict[str, Any]], chunk_offset: float = 0.0) -> List[Dict[str, Any]]:
        return SmartMomentEngine.detect_moments(
            whisper_result=whisper_result,
            peaks=peaks,
            chunk_offset=chunk_offset
        )
