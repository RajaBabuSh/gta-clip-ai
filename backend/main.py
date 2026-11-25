import sys
from pathlib import Path

# Add backend directory to sys.path so 'app.*' imports work from anywhere
_BACKEND_DIR = Path(__file__).resolve().parent
if str(_BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(_BACKEND_DIR))

# Re-export the existing FastAPI app instance from app.main (canonical app instance)
from app.main import app

if __name__ == "__main__":
    import uvicorn
    from app.core.config import settings
    uvicorn.run(
        "app.main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.ENVIRONMENT == "development"
    )
