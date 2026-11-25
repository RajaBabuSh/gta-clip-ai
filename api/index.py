"""
Vercel Serverless Function entrypoint for GTA Clip AI FastAPI backend.
Re-exports the canonical FastAPI instance from app.main.
"""
import sys
from pathlib import Path

# Add backend directory to sys.path
_ROOT = Path(__file__).resolve().parent.parent
_BACKEND_DIR = _ROOT / "backend"
if str(_BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(_BACKEND_DIR))

from app.main import app

__all__ = ["app"]
