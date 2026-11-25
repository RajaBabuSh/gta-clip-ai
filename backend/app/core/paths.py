# backend/app/core/paths.py
import os
from pathlib import Path
from typing import Optional
from app.core.config import settings, BASE_DIR

def resolve_media_path(path_str: Optional[str]) -> Optional[Path]:
    """
    Robust universal path resolver:
    Handles absolute paths, paths relative to backend, paths relative to web root,
    paths prefixed with 'backend/' or 'storage/', and fallback to filename in upload_dir.
    """
    if not path_str or not str(path_str).strip():
        return None

    clean_str = str(path_str).strip()
    raw = Path(clean_str)

    # 1. Direct absolute path
    if raw.is_absolute() and raw.exists():
        return raw

    # 2. Relative to workspace root (C:\xampp\htdocs\New folder)
    root_dir = BASE_DIR.parent
    c1 = (root_dir / clean_str).resolve()
    if c1.exists():
        return c1

    # 3. Relative to backend directory (C:\xampp\htdocs\New folder\backend)
    c2 = (BASE_DIR / clean_str).resolve()
    if c2.exists():
        return c2

    # 4. Relative to settings.storage_path
    norm = clean_str.replace("\\", "/").lstrip("/")
    if norm.startswith("backend/"):
        norm_no_backend = norm[len("backend/"):]
        c3 = (BASE_DIR / norm_no_backend).resolve()
        if c3.exists():
            return c3
        c3b = (root_dir / norm).resolve()
        if c3b.exists():
            return c3b

    if norm.startswith("storage/"):
        c4 = (settings.storage_path / norm[len("storage/"):]).resolve()
        if c4.exists():
            return c4

    # 5. Check in upload_dir by filename
    filename = Path(clean_str).name
    c5 = (settings.upload_dir / filename).resolve()
    if c5.exists():
        return c5

    # 6. Check in root storage/uploads by filename
    c6 = (root_dir / "storage" / "uploads" / filename).resolve()
    if c6.exists():
        return c6

    return None
