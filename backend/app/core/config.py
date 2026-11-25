import os
from pathlib import Path
from typing import List
from pydantic_settings import BaseSettings, SettingsConfigDict
from pydantic import Field

# Backend root directory
BASE_DIR = Path(__file__).resolve().parent.parent.parent

class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=str(BASE_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore"
    )

    ENVIRONMENT: str = "development"
    PROJECT_NAME: str = "GTA CLIP AI"
    API_V1_STR: str = "/api/v1"
    PORT: int = 8000
    HOST: str = "127.0.0.1"
    BASE_DIR: Path = BASE_DIR

    # MySQL Configuration
    MYSQL_HOST: str = "127.0.0.1"
    MYSQL_PORT: int = 3306
    MYSQL_USER: str = "root"
    MYSQL_PASSWORD: str = ""
    MYSQL_DB: str = "gtaclipai"

    # Storage Paths
    STORAGE_DIR: str = str(BASE_DIR / "storage")
    MAX_UPLOAD_SIZE_MB: int = 500

    # Hardware & Model Settings
    USE_NVIDIA_GPU: bool = True
    USE_NVENC_ENCODING: bool = True
    WHISPER_DEVICE: str = "auto"
    WHISPER_MODEL: str = os.getenv("WHISPER_MODEL", "tiny")

    # Security & Worker Tokens
    AI_WORKER_API_TOKEN: str = "gtaclip_gpu_secret_token_2026"
    REDIS_URL: str = "redis://127.0.0.1:6379/0"

    # Optional YouTube API Key
    YOUTUBE_API_KEY: str = ""
    ALLOWED_CORS_ORIGINS: List[str] = [
        "*",
        "http://localhost",
        "http://127.0.0.1",
        "http://localhost:3000",
        "http://127.0.0.1:3000",
        "http://localhost:8000",
        "http://127.0.0.1:8000"
    ]

    @property
    def database_url(self) -> str:
        # PyMySQL synchronous driver for robust local connectivity
        pwd = f":{self.MYSQL_PASSWORD}" if self.MYSQL_PASSWORD else ""
        return f"mysql+pymysql://{self.MYSQL_USER}{pwd}@{self.MYSQL_HOST}:{self.MYSQL_PORT}/{self.MYSQL_DB}?charset=utf8mb4"

    @property
    def storage_path(self) -> Path:
        raw = Path(self.STORAGE_DIR)
        if raw.is_absolute():
            p = raw
        else:
            p = (BASE_DIR / self.STORAGE_DIR).resolve()
        p.mkdir(parents=True, exist_ok=True)
        return p

    @property
    def upload_dir(self) -> Path:
        p = self.storage_path / "uploads"
        p.mkdir(parents=True, exist_ok=True)
        return p

    @property
    def uploads_dir(self) -> Path:
        return self.upload_dir

    @property
    def audio_dir(self) -> Path:
        p = self.storage_path / "audio"
        p.mkdir(parents=True, exist_ok=True)
        return p

    @property
    def subtitles_dir(self) -> Path:
        p = self.storage_path / "subtitles"
        p.mkdir(parents=True, exist_ok=True)
        return p

    @property
    def output_dir(self) -> Path:
        p = self.storage_path / "output"
        p.mkdir(parents=True, exist_ok=True)
        return p

settings = Settings()
