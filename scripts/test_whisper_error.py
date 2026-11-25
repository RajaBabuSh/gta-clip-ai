import sys
import traceback
from pathlib import Path

backend_dir = Path(r"C:\xampp\htdocs\New folder\backend")
sys.path.insert(0, str(backend_dir))

from app.core.config import settings
from app.services.whisper_service import WhisperTranscriptionService

chunk_wav = settings.audio_dir / "test_chunk_0.wav"
print(f"chunk_wav: {chunk_wav}")
print(f"chunk_wav exists: {chunk_wav.exists()}")

try:
    # Test with str(chunk_wav)
    print("Testing Whisper with relative path str(chunk_wav)...")
    res = WhisperTranscriptionService.transcribe(chunk_wav, model_name="base")
    print("Relative path success!")
except Exception as e:
    print("Relative path FAILED:", type(e), e)
    traceback.print_exc()

try:
    # Test with chunk_wav.resolve()
    print("Testing Whisper with resolved absolute path...")
    res = WhisperTranscriptionService.transcribe(chunk_wav.resolve(), model_name="base")
    print("Absolute path success!")
except Exception as e:
    print("Absolute path FAILED:", type(e), e)
    traceback.print_exc()
