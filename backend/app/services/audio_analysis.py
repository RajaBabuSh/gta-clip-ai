import sys
import wave
import subprocess
import shutil
from pathlib import Path
from typing import Dict, Any, List, Optional
import numpy as np
from app.core.config import settings

class AudioAnalysisService:
    @staticmethod
    def extract_mono_wav(video_path: Path, output_wav_path: Path, duration_limit: Optional[float] = None) -> Path:
        """
        Demuxes audio to 16kHz mono 16-bit PCM WAV for Whisper and acoustic energy analysis.
        Supports duration_limit to cleanly cap multi-hour broadcasts to needed duration.
        """
        output_wav_path.parent.mkdir(parents=True, exist_ok=True)
        ffmpeg_bin = shutil.which("ffmpeg")
        if not ffmpeg_bin:
            raise RuntimeError("FFmpeg executable not found in PATH.")

        cmd = [
            ffmpeg_bin,
            "-y",
            "-i", str(video_path)
        ]
        if duration_limit and duration_limit > 0:
            cmd.extend(["-t", str(float(duration_limit))])
            
        cmd.extend([
            "-vn",
            "-acodec", "pcm_s16le",
            "-ar", "16000",
            "-ac", "1",
            str(output_wav_path)
        ])

        result = subprocess.run(cmd, capture_output=True, text=True, timeout=180)
        if result.returncode != 0:
            raise RuntimeError(f"FFmpeg audio extraction failed: {result.stderr[:200]}")

        if not output_wav_path.exists() or output_wav_path.stat().st_size == 0:
            raise RuntimeError(f"Extracted audio file is missing or empty: {output_wav_path}")

        return output_wav_path

    @staticmethod
    def extract_chunk_mono_wav(
        source_path: Path,
        output_wav_path: Path,
        start_time: float,
        duration: float
    ) -> Path:
        """
        Extracts a specific audio time slice from a local media file to 16kHz mono WAV.
        """
        output_wav_path.parent.mkdir(parents=True, exist_ok=True)
        ffmpeg_bin = shutil.which("ffmpeg")
        if not ffmpeg_bin:
            raise RuntimeError("FFmpeg executable not found in PATH.")

        cmd = [
            ffmpeg_bin,
            "-y",
            "-ss", str(round(start_time, 2)),
            "-i", str(source_path),
            "-t", str(round(duration, 2)),
            "-vn",
            "-acodec", "pcm_s16le",
            "-ar", "16000",
            "-ac", "1",
            str(output_wav_path)
        ]

        result = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
        if result.returncode != 0:
            raise RuntimeError(f"FFmpeg chunk audio extraction failed: {result.stderr[:200]}")

        if not output_wav_path.exists() or output_wav_path.stat().st_size == 0:
            raise RuntimeError(f"Extracted chunk audio is missing or empty: {output_wav_path}")

        return output_wav_path

    @staticmethod
    def extract_youtube_audio_chunk(
        youtube_url: str,
        output_wav_path: Path,
        start_time: float,
        duration: float
    ) -> Path:
        """
        Downloads a targeted audio time slice from YouTube using yt-dlp and converts to 16kHz mono WAV.
        Cleans up raw slice after conversion to minimize storage.
        """
        output_wav_path.parent.mkdir(parents=True, exist_ok=True)
        temp_m4a = output_wav_path.parent / f"temp_{output_wav_path.stem}.m4a"

        end_time = start_time + duration
        download_cmd = [
            sys.executable, "-m", "yt_dlp",
            "-f", "ba[ext=m4a]/ba",
            "--download-sections", f"*{round(start_time, 1)}-{round(end_time, 1)}",
            "-o", str(temp_m4a),
            "--force-overwrites",
            "--no-playlist",
            youtube_url
        ]

        try:
            result = subprocess.run(download_cmd, capture_output=True, text=True, timeout=180)
            if result.returncode != 0 or not temp_m4a.exists() or temp_m4a.stat().st_size == 0:
                raise RuntimeError(f"yt-dlp audio slice download failed: {result.stderr[:250]}")

            # Convert to 16kHz mono WAV
            ffmpeg_bin = shutil.which("ffmpeg")
            conv_cmd = [
                ffmpeg_bin,
                "-y",
                "-i", str(temp_m4a),
                "-vn",
                "-acodec", "pcm_s16le",
                "-ar", "16000",
                "-ac", "1",
                str(output_wav_path)
            ]
            conv_res = subprocess.run(conv_cmd, capture_output=True, text=True, timeout=60)
            if conv_res.returncode != 0:
                raise RuntimeError(f"FFmpeg WAV conversion failed: {conv_res.stderr[:200]}")

            return output_wav_path
        finally:
            if temp_m4a.exists():
                try:
                    temp_m4a.unlink()
                except Exception:
                    pass

    @staticmethod
    def get_wav_duration(wav_path: Path) -> float:
        """Calculates accurate duration in seconds from a WAV header."""
        if not wav_path.exists():
            return 0.0
        with wave.open(str(wav_path), "rb") as wf:
            framerate = wf.getframerate()
            n_frames = wf.getnframes()
            return round(n_frames / framerate, 2) if framerate > 0 else 0.0

    @staticmethod
    def analyze_audio_energy(wav_path: Path, window_size_sec: float = 0.5) -> Dict[str, Any]:
        """
        Analyzes audio RMS energy, peaks, and silence intervals from a 16kHz mono WAV file.
        """
        if not wav_path.exists():
            raise FileNotFoundError(f"WAV file not found: {wav_path}")

        with wave.open(str(wav_path), "rb") as wf:
            framerate = wf.getframerate()
            n_frames = wf.getnframes()
            raw_data = wf.readframes(n_frames)

        samples = np.frombuffer(raw_data, dtype=np.int16).astype(np.float32)
        total_duration = len(samples) / framerate if framerate > 0 else 0.0

        if total_duration == 0:
            return {"timeline": [], "duration": 0.0, "peak_timestamps": []}

        window_samples = int(framerate * window_size_sec)
        n_windows = len(samples) // window_samples

        timeline = []
        rms_values = []

        for i in range(n_windows):
            chunk = samples[i * window_samples : (i + 1) * window_samples]
            rms = np.sqrt(np.mean(chunk**2)) if len(chunk) > 0 else 0.0
            rms_values.append(rms)

        if not rms_values:
            return {"timeline": [], "duration": total_duration, "peak_timestamps": []}

        max_rms = max(rms_values) if max(rms_values) > 0 else 1.0
        normalized_rms = [float(r / max_rms) for r in rms_values]

        # Determine peak threshold (e.g. top 20% energy or > 0.40 normalized)
        threshold = np.percentile(normalized_rms, 80) if len(normalized_rms) > 5 else 0.5
        threshold = max(0.3, float(threshold))

        peak_timestamps = []
        for i, norm_val in enumerate(normalized_rms):
            timestamp = round(i * window_size_sec, 2)
            is_peak = norm_val >= threshold
            timeline.append({
                "time": timestamp,
                "energy": round(norm_val, 3),
                "is_peak": is_peak
            })
            if is_peak:
                peak_timestamps.append(timestamp)

        return {
            "duration": round(total_duration, 2),
            "timeline": timeline,
            "peak_timestamps": peak_timestamps,
            "average_energy": round(float(np.mean(normalized_rms)), 3),
            "peak_count": len(peak_timestamps)
        }
