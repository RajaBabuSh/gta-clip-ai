import os
import sys
import traceback
from pathlib import Path

# Add backend to sys.path
sys.path.insert(0, r"C:\xampp\htdocs\New folder\backend")

from app.core.config import settings
from app.core.database import SessionLocal
from app.models.video import Video
from app.models.job import ProcessingJob
from app.workers.clipping_worker import run_clipping_pipeline

db = SessionLocal()
job = db.query(ProcessingJob).filter(ProcessingJob.progress_percentage == 10).first()
if job:
    print(f"Testing reproduction for Job: {job.id}, Video: {job.video_id}")
    try:
        run_clipping_pipeline(
            job_id=job.id,
            video_id=job.video_id,
            target_count=10,
            burn_subtitles=True
        )
    except Exception as e:
        print("EXCEPTION IN TEST:")
        traceback.print_exc()
else:
    print("No 10% job found.")
