import uuid
from datetime import datetime
from sqlalchemy import Column, String, Integer, Float, DateTime, ForeignKey, Text
from sqlalchemy.orm import relationship
from app.core.database import Base

class ProcessingJob(Base):
    __tablename__ = "processing_jobs"

    id = Column(String(36), primary_key=True, default=lambda: str(uuid.uuid4()))
    video_id = Column(String(36), ForeignKey("videos.id", ondelete="CASCADE"), nullable=False)
    
    # State tracking: PENDING, INGESTING, TRANSCRIBING, ANALYZING, RENDERING, COMPLETED, FAILED
    status = Column(String(32), nullable=False, default="PENDING")
    progress_percentage = Column(Integer, default=0)
    current_step = Column(String(255), default="Initialized")
    current_stage = Column(String(64), default="Initialized")
    error_message = Column(Text, nullable=True)

    # Granular Long-Video Processing & Checkpoint Metrics
    video_analyzed_seconds = Column(Float, default=0.0)
    video_total_seconds = Column(Float, default=0.0)
    moments_detected_count = Column(Integer, default=0)
    clips_selected_count = Column(Integer, default=0)
    clips_rendered_count = Column(Integer, default=0)
    clips_total_target = Column(Integer, default=10)
    candidates_rejected_count = Column(Integer, default=0)
    clips_failed_count = Column(Integer, default=0)
    metrics_json = Column(Text, nullable=True)
    checkpoint_chunk_index = Column(Integer, default=0)
    checkpoint_data = Column(Text, nullable=True)

    created_at = Column(DateTime, default=datetime.utcnow)
    updated_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)
    worker_heartbeat_at = Column(DateTime, default=datetime.utcnow, onupdate=datetime.utcnow)

    # Relationships
    video = relationship("Video", back_populates="jobs")
    clips = relationship("Clip", back_populates="job", cascade="all, delete-orphan")
