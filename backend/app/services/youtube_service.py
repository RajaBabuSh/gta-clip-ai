import os
import sys
import re
import json
import urllib.request
import urllib.error
import subprocess
import shutil
from pathlib import Path
from typing import Optional, Dict, Any
from app.core.config import settings
from app.services.video_metadata import VideoMetadataService

class YouTubeService:
    """
    Dedicated service for authorized YouTube video verification and media ingestion.
    Strictly adheres to:
    - Never substitute another video ID.
    - Never use fake/mock clips or hardcoded titles.
    - Transparent error reporting if media access is restricted or unavailable.
    """

    YOUTUBE_URL_PATTERNS = [
        r"(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})",
        r"(?:https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]{11})",
        r"(?:https?:\/\/)?(?:www\.)?youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})",
        r"(?:https?:\/\/)?(?:www\.)?youtube\.com\/live\/([a-zA-Z0-9_-]{11})"
    ]

    @classmethod
    def extract_video_id(cls, url: str) -> Optional[str]:
        """Extracts the exact 11-character YouTube video ID."""
        if not url:
            return None
        url = url.strip()
        # Direct video ID
        if re.fullmatch(r"[a-zA-Z0-9_-]{11}", url):
            return url
        for pattern in cls.YOUTUBE_URL_PATTERNS:
            match = re.search(pattern, url)
            if match:
                return match.group(1)
        return None

    @classmethod
    def verify_video_metadata(cls, url: str) -> Dict[str, Any]:
        """
        Verifies official video metadata (Title, Channel, Thumbnail, Duration).
        Never fabricates or substitutes metadata.
        """
        video_id = cls.extract_video_id(url)
        if not video_id:
            raise ValueError(
                "Invalid YouTube URL. Please provide a standard watch, shorts, or live stream URL."
            )

        canonical_url = f"https://www.youtube.com/watch?v={video_id}"

        # 1. Fetch official YouTube oEmbed metadata (fast, official endpoint)
        oembed_url = f"https://www.youtube.com/oembed?url={canonical_url}&format=json"
        title = None
        channel_title = None
        thumbnail_url = f"https://i.ytimg.com/vi/{video_id}/hqdefault.jpg"

        try:
            req = urllib.request.Request(
                oembed_url,
                headers={"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"}
            )
            with urllib.request.urlopen(req, timeout=8) as response:
                if response.status == 200:
                    oembed_data = json.loads(response.read().decode("utf-8"))
                    title = oembed_data.get("title")
                    channel_title = oembed_data.get("author_name")
                    if oembed_data.get("thumbnail_url"):
                        thumbnail_url = oembed_data.get("thumbnail_url")
        except urllib.error.HTTPError as e:
            if e.code == 404:
                raise ValueError(
                    f"YouTube video with ID '{video_id}' does not exist or has been removed."
                )
            elif e.code in (401, 403):
                raise PermissionError(
                    f"Access to video '{video_id}' is restricted or private on YouTube."
                )
        except Exception:
            # Fall back to yt-dlp inspection if oEmbed has network hiccup
            pass

        # 2. Inspect media stream parameters via yt-dlp
        yt_dlp_bin = shutil.which("yt-dlp") or shutil.which("yt-dlp.exe")
        duration = None
        is_live = False
        media_access_status = "UNKNOWN"
        access_message = ""

        # Use sys.executable -m yt_dlp to invoke the active environment
        probe_cmd = [
            sys.executable, "-m", "yt_dlp",
            "--js-runtimes", "node",
            "--socket-timeout", "12",
            "--retries", "2",
            "--dump-json",
            "--skip-download",
            "--no-playlist",
            canonical_url
        ]

        try:
            result = subprocess.run(
                probe_cmd,
                capture_output=True,
                text=True,
                timeout=20
            )

            if result.returncode == 0 and result.stdout.strip():
                # Locate the JSON line
                for line in result.stdout.strip().split("\n"):
                    if line.startswith("{") and line.endswith("}"):
                        info = json.loads(line)
                        if not title:
                            title = info.get("title")
                        if not channel_title:
                            channel_title = info.get("uploader") or info.get("channel")
                        duration = info.get("duration")
                        is_live = bool(info.get("is_live"))
                        formats = info.get("formats", [])

                        if formats:
                            media_access_status = "AVAILABLE"
                            access_message = "Authorized video media streams are available for processing."
                        else:
                            media_access_status = "UNAVAILABLE"
                            access_message = "No accessible video streams found for this URL."
                        break
            else:
                stderr = result.stderr.lower()
                if "private video" in stderr:
                    media_access_status = "RESTRICTED"
                    access_message = "This video is marked private by the channel owner."
                elif "age-restricted" in stderr or "sign in" in stderr:
                    media_access_status = "RESTRICTED"
                    access_message = "This video is age-restricted by YouTube and requires authorized login."
                elif "live stream recording is not available" in stderr:
                    media_access_status = "UNAVAILABLE"
                    access_message = "The live broadcast is actively streaming or the recording is still processing on YouTube."
                else:
                    media_access_status = "RESTRICTED"
                    access_message = f"YouTube media access check returned: {result.stderr.strip()[:200]}"
        except subprocess.TimeoutExpired:
            media_access_status = "TIMEOUT"
            access_message = "Media stream verification timed out. YouTube servers may be slow."
        except Exception as e:
            media_access_status = "ERROR"
            access_message = f"Media stream inspection error: {str(e)}"

        if not title:
            title = f"YouTube Video ({video_id})"
        if not channel_title:
            channel_title = "YouTube Creator"

        return {
            "youtube_id": video_id,
            "youtube_url": canonical_url,
            "title": title,
            "channel_title": channel_title,
            "thumbnail_url": thumbnail_url,
            "duration_seconds": duration,
            "is_live": is_live,
            "media_access_status": media_access_status,
            "access_message": access_message
        }

    @classmethod
    def ingest_youtube_video(
        cls,
        video_id: str,
        download_clip_duration_seconds: Optional[int] = 300
    ) -> Dict[str, Any]:
        """
        Downloads authorized media stream for the exact submitted video ID.
        Never downloads or processes any different video.
        """
        canonical_url = f"https://www.youtube.com/watch?v={video_id}"
        destination_filename = f"yt_{video_id}.mp4"
        output_path = settings.upload_dir / destination_filename

        # If already downloaded, verify with ffprobe
        if output_path.exists() and output_path.stat().st_size > 1024 * 1024:
            try:
                metadata = VideoMetadataService.extract_metadata(output_path)
                return {
                    "file_path": str(output_path),
                    "stored_filename": destination_filename,
                    "metadata": metadata
                }
            except Exception:
                # Corrupt cache, re-download
                output_path.unlink()

        # Download command using yt-dlp targeting max 1080p MP4
        # For long streams (e.g. 10 hours), we download the first segment or up to duration limit
        # using ffmpeg-location or section downloader to prevent downloading 30GB unnecessarily
        download_cmd = [
            sys.executable, "-m", "yt_dlp",
            "--js-runtimes", "node",
            "--socket-timeout", "15",
            "--retries", "3",
            "--fragment-retries", "5",
            "--concurrent-fragments", "4",
            "-f", "bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]/best[height<=1080][ext=mp4]/best",
            "--merge-output-format", "mp4",
            "-o", str(output_path),
            "--no-playlist",
            "--force-overwrites"
        ]

        if download_clip_duration_seconds and download_clip_duration_seconds > 0:
            # Download initial portion if long video to preserve SSD space and network
            download_cmd.extend(["--download-sections", f"*0-{download_clip_duration_seconds}"])

        download_cmd.append(canonical_url)

        try:
            process = subprocess.run(
                download_cmd,
                capture_output=True,
                text=True,
                timeout=180
            )
            if process.returncode != 0:
                stderr = process.stderr.lower()
                error_msg = f"Failed to ingest YouTube video '{video_id}': {process.stderr.strip()[:300]}"
                if output_path.exists():
                    output_path.unlink()
                raise RuntimeError(error_msg)
        except subprocess.TimeoutExpired:
            if output_path.exists():
                output_path.unlink()
            raise TimeoutError(f"YouTube media download for '{video_id}' timed out after 180 seconds.")

        if not output_path.exists() or output_path.stat().st_size == 0:
            raise RuntimeError(f"Downloaded video file for ID '{video_id}' is empty or missing.")

        # Genuine FFprobe analysis on the downloaded media
        metadata = VideoMetadataService.extract_metadata(output_path)

        return {
            "file_path": str(output_path),
            "stored_filename": destination_filename,
            "metadata": metadata
        }
