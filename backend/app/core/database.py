import os
from sqlalchemy import create_engine, text
from sqlalchemy.orm import declarative_base, sessionmaker, Session
from app.core.config import settings
import logging

logger = logging.getLogger(__name__)

# Configure engine with strict 3s connect timeout to prevent serverless hanging
engine = create_engine(
    settings.database_url,
    pool_pre_ping=True,
    pool_recycle=3600,
    connect_args={"connect_timeout": 3},
    echo=False
)

SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

def check_db_connection() -> dict:
    """Verifies live connection to MySQL database."""
    # Fast path: In serverless environment if host is local loopback, don't attempt blocking connection
    if (os.environ.get("VERCEL") or os.environ.get("AWS_LAMBDA_FUNCTION_NAME")) and settings.MYSQL_HOST in ("127.0.0.1", "localhost", ""):
        return {
            "status": "unconfigured_local_fallback",
            "message": "Local loopback database not accessible in serverless runtime. Set MYSQL_HOST environment variable for cloud database.",
            "host": settings.MYSQL_HOST,
            "port": settings.MYSQL_PORT,
            "database": settings.MYSQL_DB
        }
    try:
        with engine.connect() as conn:
            result = conn.execute(text("SELECT 1")).scalar()
            return {
                "status": "connected" if result == 1 else "unexpected_result",
                "host": settings.MYSQL_HOST,
                "port": settings.MYSQL_PORT,
                "database": settings.MYSQL_DB
            }
    except Exception as e:
        logger.warning(f"Database connection check skipped/failed: {e}")
        return {
            "status": "error",
            "error": str(e),
            "host": settings.MYSQL_HOST,
            "port": settings.MYSQL_PORT,
            "database": settings.MYSQL_DB
        }
