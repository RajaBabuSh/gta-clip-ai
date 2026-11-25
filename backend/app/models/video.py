import uuid
from datetime import datetime
from sqlalchemy import Column, String, Integer, Float, BigInteger, DateTime, Enum
from sqlalchemy.orm import relationship
from app.core.database import Base

class Video(Base):
    __tablename__ = "videos"

    id = Column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    user_id = Column(String(36), nullable=True)
    project_id = Column(String(36), nullable=True)
    source_type = Column(String(16), nullable=False, default="UPLOAD")  # UPLOAD or YOUTUBE
    title = Column(String(255), nullable=False)
    original_filename = Column(String(255), nullable=True)
    stored_filename = Column(String(255), nullable=True)
    file_path = Column(String(512), nullable=True)
    file_size_bytes = Column(BigInteger, nullable=True)
    mime_type = Column(String(64), nullable=True)

    # Real media stream properties extracted via ffprobe
    duration_seconds = Column(Float, nullable=True)
    width = Column(Integer, nullable=True)
    height = Column(Integer, nullable=True)
    fps = Column(Float, nullable=True)
    video_codec = Column(String(32), nullable=True)
    audio_codec = Column(String(32), nullable=True)
    audio_channels = Column(Integer, nullable=True)
    audio_sample_rate = Column(Integer, nullable=True)

    # Optional YouTube fields (when source_type is YOUTUBE)
    youtube_id = Column(String(32), nullable=True)
    youtube_url = Column(String(512), nullable=True)
    channel_title = Column(String(255), nullable=True)
    thumbnail_url = Column(String(512), nullable=True)

    created_at = Column(DateTime, default=datetime.utcnow)

    # Relationships
    jobs = relationship("ProcessingJob", back_populates="video", cascade="all, delete-orphan")
    clips = relationship("Clip", back_populates="video", cascade="all, delete-orphan")
