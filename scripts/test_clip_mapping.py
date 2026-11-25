import sys
sys.path.insert(0, r"C:\xampp\htdocs\New folder\backend")

from app.core.database import SessionLocal
from app.models.clip import Clip
from app.models.job import ProcessingJob
from app.models.video import Video

db = SessionLocal()
print("Clip mapped correctly? Querying 1 clip...")
c = db.query(Clip).first()
print("Found clip:", c.id if c else "None")
