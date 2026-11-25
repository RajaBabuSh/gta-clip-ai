from fastapi import APIRouter
from app.api.v1.endpoints.health import router as health_router
from app.api.v1.endpoints.videos import router as videos_router
from app.api.v1.endpoints.jobs import router as jobs_router
from app.api.v1.endpoints.clips import router as clips_router

api_router = APIRouter()

api_router.include_router(health_router, tags=["System Health"])
api_router.include_router(videos_router, prefix="/videos", tags=["Videos"])
api_router.include_router(jobs_router, prefix="/jobs", tags=["Jobs"])
api_router.include_router(clips_router, prefix="/clips", tags=["Clips"])
