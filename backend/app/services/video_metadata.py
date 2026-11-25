import json
import subprocess
import shutil
from pathlib import Path
from typing import Dict, Any

class VideoMetadataService:
    @staticmethod
    def extract_metadata(file_path: Path) -> Dict[str, Any]:
        """
        Uses ffprobe to inspect genuine media stream properties.
        Never mocks or fabricates video duration or dimensions.
        """
        if not file_path.exists():
            raise FileNotFoundError(f"Video file not found at: {file_path}")

        ffprobe_bin = shutil.which("ffprobe")
        if not ffprobe_bin:
            raise RuntimeError(
                "ffprobe executable not found in system PATH. Ensure FFmpeg is installed."
            )

        cmd = [
            ffprobe_bin,
            "-v", "quiet",
            "-print_format", "json",
            "-show_format",
            "-show_streams",
            str(file_path)
        ]

        try:
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                check=True,
                timeout=15
            )
        except subprocess.CalledProcessError as e:
            raise ValueError(f"ffprobe failed to analyze video: {e.stderr or e}")
        except subprocess.TimeoutExpired:
            raise TimeoutError("ffprobe timed out while analyzing video stream")

        try:
            data = json.loads(result.stdout)
        except json.JSONDecodeError as e:
            raise ValueError(f"Failed to parse ffprobe output as JSON: {e}")

        streams = data.get("streams", [])
        fmt = data.get("format", {})

        video_stream = next((s for s in streams if s.get("codec_type") == "video"), None)
        audio_stream = next((s for s in streams if s.get("codec_type") == "audio"), None)

        if not video_stream:
            raise ValueError("The provided file does not contain a valid video stream.")

        # Parse frame rate (e.g., '60/1', '30000/1001')
        fps = None
        fps_str = video_stream.get("r_frame_rate", "")
        if "/" in fps_str:
            num, den = fps_str.split("/")
            if float(den) > 0:
                fps = round(float(num) / float(den), 2)
        elif fps_str:
            try:
                fps = round(float(fps_str), 2)
            except ValueError:
                fps = None

        # Parse duration from format or video stream
        duration = None
        duration_raw = fmt.get("duration") or video_stream.get("duration")
        if duration_raw:
            try:
                duration = round(float(duration_raw), 2)
            except ValueError:
                duration = None

        width = video_stream.get("width")
        height = video_stream.get("height")
        video_codec = video_stream.get("codec_name")

        audio_codec = audio_stream.get("codec_name") if audio_stream else None
        audio_channels = audio_stream.get("channels") if audio_stream else None
        audio_sample_rate = int(audio_stream.get("sample_rate")) if audio_stream and audio_stream.get("sample_rate") else None

        file_size = int(fmt.get("size", file_path.stat().st_size))

        return {
            "duration_seconds": duration,
            "width": width,
            "height": height,
            "fps": fps,
            "video_codec": video_codec,
            "audio_codec": audio_codec,
            "audio_channels": audio_channels,
            "audio_sample_rate": audio_sample_rate,
            "file_size_bytes": file_size,
            "format_name": fmt.get("format_name")
        }
