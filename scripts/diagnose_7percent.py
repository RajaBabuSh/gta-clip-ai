import sys
import os
from pathlib import Path

backend_dir = Path(r"C:\xampp\htdocs\New folder\backend")
sys.path.insert(0, str(backend_dir))

from app.core.config import settings
from app.core.database import SessionLocal
from app.models.video import Video
from app.models.job import ProcessingJob

db = SessionLocal()
print("Backend BASE_DIR:", settings.STORAGE_DIR)
print("upload_dir:", settings.upload_dir)
print("audio_dir:", settings.audio_dir)
print("output_dir:", settings.output_dir)

videos = db.query(Video).order_by(Video.created_at.desc()).limit(5).all()
for v in videos:
    print(f"\nVideo ID: {v.id}")
    print(f"  Title: {v.title}")
    print(f"  Source Type: {v.source_type}")
    print(f"  Stored file_path: {v.file_path}")
    if v.file_path:
        p_raw = Path(v.file_path)
        p_abs1 = (backend_dir / v.file_path).resolve()
        p_abs2 = (backend_dir.parent / v.file_path).resolve()
        print(f"  Path(raw).exists(): {p_raw.exists()} ({p_raw.resolve()})")
        print(f"  backend/path exists: {p_abs1.exists()} ({p_abs1})")
        print(f"  root/path exists: {p_abs2.exists()} ({p_abs2})")

jobs = db.query(ProcessingJob).order_by(ProcessingJob.created_at.desc()).limit(5).all()
for j in jobs:
    print(f"\nJob ID: {j.id}")
    print(f"  Status: {j.status}")
    print(f"  Progress: {j.progress_percentage}%")
    print(f"  Stage: {j.current_stage}")
    print(f"  Step: {j.current_step}")
    print(f"  Error: {j.error_message}")
