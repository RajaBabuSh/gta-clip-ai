import sys
import uuid
sys.path.insert(0, r"C:\xampp\htdocs\New folder\backend")

from app.core.database import SessionLocal
from app.models.clip import Clip
from app.models.job import ProcessingJob
from app.models.video import Video

db = SessionLocal()
job = db.query(ProcessingJob).first()
video = db.query(Video).first()

test_clip = Clip(
    id=str(uuid.uuid4()),
    job_id=job.id,
    video_id=video.id,
    user_id=None,
    project_id=None,
    clip_index=99,
    start_time=0.0,
    end_time=15.0,
    duration=15.0,
    title="Test Clip Commit",
    file_path="storage/output/test.mp4"
)
try:
    db.add(test_clip)
    db.commit()
    print("SUCCESS: Committed clip!")
    db.delete(test_clip)
    db.commit()
except Exception as e:
    print("FAILED TO COMMIT CLIP:", e)
finally:
    db.close()
