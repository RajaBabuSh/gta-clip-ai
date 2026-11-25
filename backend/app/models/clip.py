import uuid
from datetime import datetime
from sqlalchemy import Column, String, Integer, Float, BigInteger, DateTime, ForeignKey, Text, JSON
from sqlalchemy.orm import relationship
from app.core.database import Base

class Clip(Base):
    __tablename__ = "clips"

    id = Column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    job_id = Column(String(36), ForeignKey("processing_jobs.id", ondelete="CASCADE"), nullable=True)
    video_id = Column(String(36), ForeignKey("videos.id", ondelete="CASCADE"), nullable=False)
    user_id = Column(String(36), nullable=True)
    project_id = Column(String(36), nullable=True)

    clip_index = Column(Integer, nullable=True, default=1)
    start_time = Column(Float, nullable=False)
    end_time = Column(Float, nullable=False)
    duration = Column(Float, nullable=False)

    title = Column(String(255), nullable=False)
    description = Column(Text, nullable=True)
    hashtags = Column(JSON, nullable=True)
    virality_score = Column(Float, default=0.0)
    status = Column(String(32), default="approved")

    file_path = Column(String(512), nullable=False)
    thumbnail_path = Column(String(512), nullable=True)
    file_size_bytes = Column(BigInteger, nullable=True)
    aspect_ratio = Column(String(16), default="9:16")
    views_count = Column(Integer, default=0)
    downloads_count = Column(Integer, default=0)
    editor_settings_json = Column(JSON, nullable=True)

    created_at = Column(DateTime, default=datetime.utcnow)

    # Relationships
    job = relationship("ProcessingJob", back_populates="clips")
    video = relationship("Video", back_populates="clips")
