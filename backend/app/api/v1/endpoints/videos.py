import os
import uuid
import aiofiles
from pathlib import Path
from typing import List
from fastapi import APIRouter, Depends, HTTPException, UploadFile, File, Form, status
from sqlalchemy.orm import Session

from app.core.config import settings
from app.core.database import get_db
from app.models.video import Video
from app.models.job import ProcessingJob
from app.schemas.video import (
    VideoResponse,
    YouTubeVerifyRequest,
    YouTubeVerifyResponse,
    YouTubeConfirmRequest
)
from app.services.video_metadata import VideoMetadataService
from app.services.youtube_service import YouTubeService

router = APIRouter()

ALLOWED_EXTENSIONS = {".mp4", ".mov", ".mkv", ".webm"}

@router.post("/upload", response_model=VideoResponse, status_code=status.HTTP_201_CREATED)
async def upload_video(
    file: UploadFile = File(...),
    title: str = Form(None),
    db: Session = Depends(get_db)
):
    """
    Ingests an authorized video file, saves it to local NVMe/SSD storage,
    and runs genuine FFprobe inspection to populate media stream properties.
    """
    if not file.filename:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Uploaded file must have a filename."
        )

    file_ext = Path(file.filename).suffix.lower()
    if file_ext not in ALLOWED_EXTENSIONS:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Unsupported format '{file_ext}'. Allowed formats: {', '.join(ALLOWED_EXTENSIONS)}"
        )

    # Generate secure UUID filename
    video_id = str(uuid.uuid4())
    stored_filename = f"{video_id}{file_ext}"
    destination_path = settings.upload_dir / stored_filename

    # Stream file to local storage chunk by chunk to prevent RAM spikes
    bytes_written = 0
    max_bytes = settings.MAX_UPLOAD_SIZE_MB * 1024 * 1024

    try:
        async with aiofiles.open(destination_path, "wb") as out_file:
            while chunk := await file.read(1024 * 1024):  # 1MB buffer
                bytes_written += len(chunk)
                if bytes_written > max_bytes:
                    if destination_path.exists():
                        destination_path.unlink()
                    raise HTTPException(
                        status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
                        detail=f"File exceeds maximum allowed limit of {settings.MAX_UPLOAD_SIZE_MB}MB."
                    )
                await out_file.write(chunk)
    except Exception as e:
        if destination_path.exists():
            destination_path.unlink()
        if isinstance(e, HTTPException):
            raise e
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to write file to disk: {str(e)}"
        )

    # Run genuine FFprobe analysis
    try:
        metadata = VideoMetadataService.extract_metadata(destination_path)
    except Exception as e:
        if destination_path.exists():
            destination_path.unlink()
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_CONTENT,
            detail=f"Media validation error: {str(e)}"
        )

    video_title = title.strip() if title and title.strip() else Path(file.filename).stem

    # Store record in MySQL
    db_video = Video(
        id=video_id,
        source_type="UPLOAD",
        title=video_title,
        original_filename=file.filename,
        stored_filename=stored_filename,
        file_path=str(destination_path),
        file_size_bytes=bytes_written,
        mime_type=file.content_type or "video/mp4",
        duration_seconds=metadata.get("duration_seconds"),
        width=metadata.get("width"),
        height=metadata.get("height"),
        fps=metadata.get("fps"),
        video_codec=metadata.get("video_codec"),
        audio_codec=metadata.get("audio_codec"),
        audio_channels=metadata.get("audio_channels"),
        audio_sample_rate=metadata.get("audio_sample_rate")
    )
    db.add(db_video)

    # Initialize a baseline ProcessingJob record
    db_job = ProcessingJob(
        video_id=video_id,
        status="READY_FOR_CLIPPING",
        progress_percentage=100,
        current_step="Video uploaded and stream properties verified."
    )
    db.add(db_job)

    db.commit()
    db.refresh(db_video)

    return db_video

@router.post("/verify", response_model=YouTubeVerifyResponse)
@router.post("/youtube/verify", response_model=YouTubeVerifyResponse)
def verify_youtube_video(req: YouTubeVerifyRequest):
    """
    Verifies submitted YouTube URL without downloading or substituting video media.
    Checks video ID, official title, creator channel, and availability status.
    """
    try:
        data = YouTubeService.verify_video_metadata(req.url)
        return data
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )
    except PermissionError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e)
        )
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Failed to inspect YouTube URL: {str(e)}"
        )

@router.post("/youtube/confirm", response_model=VideoResponse, status_code=status.HTTP_201_CREATED)
def confirm_youtube_ingestion(req: YouTubeConfirmRequest, db: Session = Depends(get_db)):
    """
    Ingests verified media stream for the exact confirmed YouTube video ID.
    Rejects processing if media is unavailable, restricted, or DRM-protected.
    """
    video_id = YouTubeService.extract_video_id(req.youtube_id)
    if not video_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Invalid YouTube video ID '{req.youtube_id}'."
        )

    # Verify official metadata first
    try:
        verified_meta = YouTubeService.verify_video_metadata(video_id)
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Failed to verify source video: {str(e)}"
        )

    # Media stream check
    if verified_meta.get("media_access_status") in ("RESTRICTED", "UNAVAILABLE"):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=f"Media access unavailable: {verified_meta.get('access_message')}"
        )

    # Perform genuine ingestion for the exact video ID
    try:
        ingest_res = YouTubeService.ingest_youtube_video(
            video_id=video_id,
            download_clip_duration_seconds=req.duration_limit_seconds
        )
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_422_UNPROCESSABLE_CONTENT,
            detail=f"Failed to ingest source YouTube media: {str(e)}"
        )

    meta = ingest_res["metadata"]
    db_id = str(uuid.uuid4())
    final_title = req.custom_title.strip() if req.custom_title and req.custom_title.strip() else verified_meta["title"]

    db_video = Video(
        id=db_id,
        source_type="YOUTUBE",
        title=final_title,
        original_filename=f"{video_id}.mp4",
        stored_filename=ingest_res["stored_filename"],
        file_path=ingest_res["file_path"],
        file_size_bytes=meta.get("file_size_bytes"),
        mime_type="video/mp4",
        duration_seconds=verified_meta.get("duration_seconds") or meta.get("duration_seconds"),
        width=meta.get("width"),
        height=meta.get("height"),
        fps=meta.get("fps"),
        video_codec=meta.get("video_codec"),
        audio_codec=meta.get("audio_codec"),
        audio_channels=meta.get("audio_channels"),
        audio_sample_rate=meta.get("audio_sample_rate"),
        youtube_id=video_id,
        youtube_url=verified_meta["youtube_url"],
        channel_title=verified_meta["channel_title"],
        thumbnail_url=verified_meta["thumbnail_url"]
    )
    db.add(db_video)

    db_job = ProcessingJob(
        video_id=db_id,
        status="READY_FOR_CLIPPING",
        progress_percentage=100,
        current_step="Source YouTube stream ingested and verified with FFprobe."
    )
    db.add(db_job)

    db.commit()
    db.refresh(db_video)

    return db_video

@router.get("/{video_id}", response_model=VideoResponse)
def get_video_detail(video_id: str, db: Session = Depends(get_db)):
    """Retrieves full verified stream details for a video."""
    video = db.query(Video).filter(Video.id == video_id).first()
    if not video:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Video with ID '{video_id}' not found."
        )
    return video

@router.get("/", response_model=List[VideoResponse])
def list_videos(skip: int = 0, limit: int = 20, db: Session = Depends(get_db)):
    """Lists source videos ordered by creation date."""
    return db.query(Video).order_by(Video.created_at.desc()).offset(skip).limit(limit).all()
