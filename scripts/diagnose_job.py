import sys
from pathlib import Path

backend_dir = Path(r"C:\xampp\htdocs\New folder\backend")
sys.path.insert(0, str(backend_dir))

from app.core.database import SessionLocal
from app.models.job import ProcessingJob
from app.models.video import Video

db = SessionLocal()
job = db.query(ProcessingJob).filter(ProcessingJob.id == "81fc09d9-7cf7-4a14-8dcb-56a9b4be5efd").first()
if job:
    print("Job found:")
    print("  id:", job.id)
    print("  video_id:", job.video_id)
    print("  status:", job.status)
    print("  stage:", job.current_stage)
    print("  step:", job.current_step)
    print("  error_message:", job.error_message)
    print("  progress:", job.progress_percentage)
    print("  checkpoint_chunk_index:", job.checkpoint_chunk_index)
    v = db.query(Video).filter(Video.id == job.video_id).first()
    if v:
        print("Video found:")
        print("  title:", v.title)
        print("  file_path:", v.file_path)
        print("  source_type:", v.source_type)
        print("  youtube_id:", v.youtube_id)
        print("  duration:", v.duration_seconds)
else:
    print("Job not found.")
