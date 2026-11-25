from datetime import datetime
from typing import Optional
from pydantic import BaseModel, Field

class VideoBase(BaseModel):
    title: str
    source_type: str = "UPLOAD"
    original_filename: Optional[str] = None

class VideoCreate(VideoBase):
    pass

class VideoResponse(BaseModel):
    id: str
    title: str
    source_type: str
    original_filename: Optional[str] = None
    stored_filename: Optional[str] = None
    file_size_bytes: Optional[int] = None
    mime_type: Optional[str] = None
    duration_seconds: Optional[float] = None
    width: Optional[int] = None
    height: Optional[int] = None
    fps: Optional[float] = None
    video_codec: Optional[str] = None
    audio_codec: Optional[str] = None
    audio_channels: Optional[int] = None
    audio_sample_rate: Optional[int] = None
    youtube_id: Optional[str] = None
    youtube_url: Optional[str] = None
    channel_title: Optional[str] = None
    thumbnail_url: Optional[str] = None
    created_at: datetime

    class Config:
        from_attributes = True

class YouTubeVerifyRequest(BaseModel):
    url: str

class YouTubeVerifyResponse(BaseModel):
    youtube_id: str
    youtube_url: str
    title: str
    channel_title: str
    thumbnail_url: str
    duration_seconds: Optional[float] = None
    is_live: bool = False
    media_access_status: str
    access_message: str

class YouTubeConfirmRequest(BaseModel):
    youtube_id: str
    custom_title: Optional[str] = None
    duration_limit_seconds: Optional[int] = 300
