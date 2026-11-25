import sys
from pathlib import Path

backend_dir = Path(r"C:\xampp\htdocs\New folder\backend")
sys.path.insert(0, str(backend_dir))

from app.services.whisper_service import WhisperTranscriptionService

wav = Path(r"C:\xampp\htdocs\New folder\backend\storage\audio\master_audio_c7f699fa-2a6c-48cf-8ba4-b730b80d2cd8.wav")
print(f"Testing Whisper on: {wav} (exists: {wav.exists()})")

try:
    res = WhisperTranscriptionService.transcribe(wav, model_name="tiny")
    print("SUCCESS! Transcribed characters:", len(res.get("text", "")))
    print("Sample text:", res.get("text", "")[:100])
except Exception as e:
    import traceback
    print("FAILED with error:", e)
    traceback.print_exc()
