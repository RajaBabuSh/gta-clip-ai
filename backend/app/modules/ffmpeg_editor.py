# backend/app/modules/ffmpeg_editor.py
from pathlib import Path
from typing import List, Dict, Any, Optional
from app.services.ffmpeg_render import FFmpegRenderEngine

class FFmpegEditor:
    """9:16 vertical 1080x1920 cropping, NVENC GPU rendering, animated captions, and thumbnails."""

    @staticmethod
    def render_clip(
        source_video: Path,
        start_time: float,
        end_time: float,
        output_clip_path: Path,
        words: List[Dict[str, Any]] = None,
        burn_subtitles: bool = True
    ) -> bool:
        return FFmpegRenderEngine.render_vertical_clip(
            source_video_path=source_video,
            start_time=start_time,
            end_time=end_time,
            output_clip_path=output_clip_path,
            words=words or [],
            burn_subtitles=burn_subtitles
        )

    @staticmethod
    def generate_thumbnail(video_path: Path, output_thumb_path: Path, timestamp: float = 1.0) -> bool:
        import subprocess
        try:
            cmd = [
                "ffmpeg", "-y", "-ss", str(timestamp),
                "-i", str(video_path),
                "-vframes", "1", "-update", "1",
                "-q:v", "2",
                str(output_thumb_path)
            ]
            res = subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=False)
            return output_thumb_path.exists() and output_thumb_path.stat().st_size > 0
        except Exception:
            return False
