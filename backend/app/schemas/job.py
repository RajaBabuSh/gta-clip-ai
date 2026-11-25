from datetime import datetime
from typing import Optional
from pydantic import BaseModel

class JobResponse(BaseModel):
    id: str
    video_id: str
    status: str
    progress_percentage: int
    current_step: Optional[str] = None
    current_stage: Optional[str] = None
    error_message: Optional[str] = None
    worker_heartbeat_at: Optional[datetime] = None

    # Granular Long-Video Progress Metrics
    video_analyzed_seconds: Optional[float] = 0.0
    video_total_seconds: Optional[float] = 0.0
    moments_detected_count: Optional[int] = 0
    clips_selected_count: Optional[int] = 0
    clips_rendered_count: Optional[int] = 0
    clips_total_target: Optional[int] = 10
    checkpoint_chunk_index: Optional[int] = 0

    created_at: datetime
    updated_at: datetime

    class Config:
        from_attributes = True
