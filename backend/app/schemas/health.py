from typing import Dict, Any, Optional
from pydantic import BaseModel

class HealthResponse(BaseModel):
    status: str
    project: str
    environment: str
    database: Dict[str, Any]
    hardware: Dict[str, Any]
    storage: Dict[str, Any]
    worker: Optional[Dict[str, Any]] = None
