from fastapi import APIRouter
from app.api.v1.endpoints import health, videos, jobs, clips

api_router = APIRouter()

api_router.include_router(health.router, tags=["System Health"])
api_router.include_router(videos.router, prefix="/videos", tags=["Videos"])
api_router.include_router(jobs.router, prefix="/jobs", tags=["Jobs"])
api_router.include_router(clips.router, prefix="/clips", tags=["Clips"])
