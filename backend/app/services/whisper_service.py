import os
import re
from pathlib import Path
from typing import Dict, Any, List
import whisper
import torch
from app.core.config import settings

class WhisperTranscriptionService:
    _model = None
    _loaded_model_name = None

    @classmethod
    def get_model(cls, model_name: str = "tiny"):
        """Loads and caches the Whisper model in memory."""
        device = "cuda" if torch.cuda.is_available() and settings.USE_NVIDIA_GPU else "cpu"
        if cls._model is None or cls._loaded_model_name != model_name:
            cls._model = whisper.load_model(model_name, device=device)
            cls._loaded_model_name = model_name
        return cls._model

    @classmethod
    def transcribe(cls, wav_path: Path, model_name: str = "tiny") -> Dict[str, Any]:
        """
        Transcribes audio with word-level timestamps and segment markers.
        """
        if not wav_path.exists():
            raise FileNotFoundError(f"Audio file not found: {wav_path}")

        model = cls.get_model(model_name)

        # Transcribe with word timestamps enabled
        result = model.transcribe(
            str(wav_path),
            word_timestamps=True,
            verbose=False,
            fp16=torch.cuda.is_available()
        )

        segments = []
        for s in result.get("segments", []):
            words = []
            for w in s.get("words", []):
                words.append({
                    "word": w.get("word", "").strip(),
                    "start": round(float(w.get("start", 0)), 2),
                    "end": round(float(w.get("end", 0)), 2),
                    "probability": round(float(w.get("probability", 1.0)), 2)
                })

            clean_text = s.get("text", "").strip()
            segments.append({
                "id": s.get("id"),
                "start": round(float(s.get("start", 0)), 2),
                "end": round(float(s.get("end", 0)), 2),
                "text": clean_text,
                "words": words
            })

        return {
            "text": result.get("text", "").strip(),
            "language": result.get("language", "en"),
            "segments": segments
        }
