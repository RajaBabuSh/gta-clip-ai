from datetime import datetime
from typing import Optional, List, Any
from pydantic import BaseModel

class ClipResponse(BaseModel):
    id: str
    job_id: str
    video_id: str
    clip_index: int
    start_time: float
    end_time: float
    duration: float
    title: str
    description: Optional[str] = None
    hashtags: Optional[List[str]] = None
    virality_score: float
    aspect_ratio: str
    file_size_bytes: Optional[int] = None
    download_url: Optional[str] = None
    created_at: datetime

    class Config:
        from_attributes = True
