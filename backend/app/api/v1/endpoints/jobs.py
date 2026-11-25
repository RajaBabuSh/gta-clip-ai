from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from app.core.database import get_db
from app.models.job import ProcessingJob
from app.schemas.job import JobResponse

router = APIRouter()

@router.get("/{job_id}", response_model=JobResponse)
def get_job_status(job_id: str, db: Session = Depends(get_db)):
    """Retrieves current processing status of a job."""
    job = db.query(ProcessingJob).filter(ProcessingJob.id == job_id).first()
    if not job:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"Job with ID '{job_id}' not found."
        )
    return job

@router.get("/by-video/{video_id}", response_model=JobResponse)
def get_job_by_video(video_id: str, db: Session = Depends(get_db)):
    """Retrieves active job associated with a given video ID."""
    job = db.query(ProcessingJob).filter(ProcessingJob.video_id == video_id).order_by(ProcessingJob.created_at.desc()).first()
    if not job:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=f"No processing job found for video ID '{video_id}'."
        )
    return job
