import zipfile
from pathlib import Path
from typing import List, Dict, Any
from app.core.config import settings

class ZipService:
    @staticmethod
    def create_clips_zip(job_id: str, video_title: str, clip_paths: List[Path]) -> Path:
        """
        Creates a zip file containing all rendered MP4 clips for a job.
        """
        clean_title = "".join(c for c in video_title if c.isalnum() or c in (" ", "_", "-")).rstrip()
        clean_title = clean_title.replace(" ", "_")[:30] or "clips"
        zip_filename = f"{clean_title}_{job_id[:8]}_all_clips.zip"
        zip_path = settings.output_dir / zip_filename

        with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zipf:
            for p in clip_paths:
                if p.exists():
                    zipf.write(p, arcname=p.name)

        return zip_path
