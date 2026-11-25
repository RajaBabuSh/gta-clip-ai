# backend/app/modules/source_ingestion.py
from pathlib import Path
from typing import Optional, Dict, Any
from app.services.youtube_service import YouTubeService

class SourceIngestion:
    """Manages audio slicing, stream chunking, and local media persistence."""

    @staticmethod
    def extract_audio_chunk(source_path: Path, output_wav: Path, start_time: float, duration: float) -> bool:
        from app.services.ffmpeg_render import FFmpegRenderEngine
        return FFmpegRenderEngine.extract_audio(
            source_video_path=source_path,
            output_audio_path=output_wav,
            start_time=start_time,
            duration=duration
        )

    @staticmethod
    def download_stream_slice(url: str, output_path: Path, start_time: float, end_time: float) -> bool:
        try:
            from app.services.youtube_service import download_youtube_video_slice
            download_youtube_video_slice(
                youtube_url=url,
                output_slice_path=output_path,
                start_time=start_time,
                end_time=end_time
            )
            return output_path.exists() and output_path.stat().st_size > 0
        except Exception:
            return False
