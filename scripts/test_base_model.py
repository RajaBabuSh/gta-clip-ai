import sys
from pathlib import Path

backend_dir = Path(r"C:\xampp\htdocs\New folder\backend")
sys.path.insert(0, str(backend_dir))

from app.services.whisper_service import WhisperTranscriptionService

print("Testing Whisper model loading: base")
try:
    m = WhisperTranscriptionService.get_model("base")
    print("SUCCESS loading base model:", type(m))
except Exception as e:
    import traceback
    print("FAILED loading base model:", e)
    traceback.print_exc()
