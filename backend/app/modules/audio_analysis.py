# backend/app/modules/audio_analysis.py
from pathlib import Path
from typing import List, Dict, Any
from app.services.audio_analysis import AudioPeakDetector

class AudioAnalysis:
    """Detects RMS loudness spikes, streamer laughter, and excitement peaks."""

    @staticmethod
    def detect_peaks(audio_path: Path, min_peak_distance: float = 12.0) -> List[Dict[str, Any]]:
        return AudioPeakDetector.detect_peaks(audio_path, min_peak_distance=min_peak_distance)
