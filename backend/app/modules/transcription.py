# backend/app/modules/transcription.py
from pathlib import Path
from typing import Dict, Any
from app.services.whisper_service import WhisperService

class TranscriptionModule:
    """Timestamped speech transcription with word-level alignment using Whisper."""

    @staticmethod
    def transcribe(audio_path: Path, language: str = None) -> Dict[str, Any]:
        return WhisperService.transcribe(audio_path, language=language)
