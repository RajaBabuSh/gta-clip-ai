import os
import sys
import uuid
import time
from pathlib import Path

sys.path.insert(0, r"C:\xampp\htdocs\New folder\backend")

from app.core.config import settings
from app.core.database import SessionLocal
from app.models.video import Video
from app.models.job import ProcessingJob
from app.models.clip import Clip
from app.workers.clipping_worker import run_clipping_pipeline

def run_test():
    print("=====================================================")
    print("TESTING FULL PIPELINE END-TO-END")
    print("=====================================================")

    db = SessionLocal()
    video = db.query(Video).filter(Video.source_type == "YOUTUBE").first()
    if not video:
        print("ERROR: No test video found in database.")
        return False

    print(f"Using Video: {video.id} - '{video.title}' (Duration: {video.duration_seconds}s)")

    # Create a fresh test job
    job_id = str(uuid.uuid4())
    test_job = ProcessingJob(
        id=job_id,
        video_id=video.id,
        status="PENDING",
        progress_percentage=0,
        current_step="Test job created",
        clips_total_target=5
    )
    db.add(test_job)
    db.commit()
    print(f"Created Test Job: {job_id}")

    # Run clipping pipeline
    print("\n--- Executing run_clipping_pipeline ---")
    start_time = time.time()
    run_clipping_pipeline(
        job_id=job_id,
        video_id=video.id,
        target_count=5,
        burn_subtitles=True
    )
    elapsed = time.time() - start_time
    print(f"Pipeline executed in {elapsed:.1f} seconds.\n")

    # Verify Job record in DB
    db.refresh(test_job)
    print("--- Job Final Status ---")
    print(f"Status: {test_job.status}")
    print(f"Progress: {test_job.progress_percentage}%")
    print(f"Stage: {test_job.current_stage}")
    print(f"Current Step: {test_job.current_step}")
    print(f"Clips Selected: {test_job.clips_selected_count}, Rendered: {test_job.clips_rendered_count}")
    print(f"Worker Heartbeat: {test_job.worker_heartbeat_at}")
    print(f"Error Message: {test_job.error_message}")

    # Check if job moved past 10%
    if test_job.progress_percentage <= 10:
        print("\n❌ FAILED: Job is still stuck at or below 10%!")
        return False
    else:
        print(f"\n✅ PASSED: Job successfully progressed to {test_job.progress_percentage}% (Moved past 10% threshold)!")

    # Check clips generated in DB and on disk
    clips = db.query(Clip).filter(Clip.job_id == job_id).all()
    print(f"\nFound {len(clips)} clip records in MySQL.")

    base_dir = Path(r"C:\xampp\htdocs\New folder")
    all_files_exist = True
    for c in clips:
        disk_path = base_dir / c.file_path
        exists = disk_path.exists()
        size = disk_path.stat().st_size if exists else 0
        print(f"  - Clip #{c.clip_index} (Virality: {c.virality_score}%): {c.title} -> File: {c.file_path} (Exists: {exists}, Size: {size} bytes)")
        if not exists or size == 0:
            all_files_exist = False

    if test_job.status in ("COMPLETED", "PARTIALLY_COMPLETED") and len(clips) > 0 and all_files_exist:
        print("\n=====================================================")
        print("🎉 ALL PIPELINE TESTS PASSED 100%!")
        print("=====================================================")
        return True
    else:
        print("\nPipeline finished with issues.")
        return False

if __name__ == "__main__":
    success = run_test()
    sys.exit(0 if success else 1)
