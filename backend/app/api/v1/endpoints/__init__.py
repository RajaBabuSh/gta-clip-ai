# backend/app/api/v1/endpoints/__init__.py
from app.api.v1.endpoints import health
from app.api.v1.endpoints import videos
from app.api.v1.endpoints import jobs
from app.api.v1.endpoints import clips

__all__ = ["health", "videos", "jobs", "clips"]
