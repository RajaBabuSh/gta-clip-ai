import sys
import traceback
from pathlib import Path

backend_dir = Path(r"C:\xampp\htdocs\New folder\backend")
sys.path.insert(0, str(backend_dir))

from app.core.config import settings
from app.services.audio_analysis import AudioAnalysisService
from app.services.whisper_service import WhisperTranscriptionService

master_wav = Path(r"C:\xampp\htdocs\New folder\backend\storage\audio\master_audio_c7f699fa-2a6c-48cf-8ba4-b730b80d2cd8.wav")
chunk_wav = settings.audio_dir / "test_chunk_0.wav"

print(f"Master wav: {master_wav} (exists: {master_wav.exists()})")
try:
    print("Extracting chunk mono wav 0.0 to 20.0...")
    AudioAnalysisService.extract_chunk_mono_wav(
        source_path=master_wav,
        output_wav_path=chunk_wav,
        start_time=0.0,
        duration=20.0
    )
    print(f"Chunk wav created: {chunk_wav} (size: {chunk_wav.stat().st_size})")

    print("Analyzing audio energy...")
    energy = AudioAnalysisService.analyze_audio_energy(chunk_wav)
    print(f"Energy analysis success! Sample rate: {energy.get('sample_rate')}")

    print("Transcribing with Whisper...")
    res = WhisperTranscriptionService.transcribe(chunk_wav, model_name="tiny")
    print(f"Whisper transcription success! Words: {len(res.get('segments', []))}")
except Exception as e:
    print("Caught Exception:", type(e), e)
    traceback.print_exc()
