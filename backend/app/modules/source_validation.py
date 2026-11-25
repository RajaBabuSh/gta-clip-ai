# backend/app/modules/source_validation.py
import re
from typing import Dict, Any, Optional
from app.services.youtube_service import YouTubeService

class SourceValidator:
    """Validates source streams and files from YouTube, Twitch, Kick, and direct uploads."""
    
    @staticmethod
    def validate_url(url: str) -> Dict[str, Any]:
        url = url.strip()
        if not url:
            return {"valid": False, "error": "URL cannot be empty."}

        # Check YouTube
        yt_match = re.search(r"(?:v=|\/)([0-9A-Za-z_-]{11}).*", url)
        if yt_match:
            video_id = yt_match.group(1)
            meta = YouTubeService.get_video_metadata(url)
            if meta:
                return {
                    "valid": True,
                    "platform": "YOUTUBE",
                    "video_id": video_id,
                    "metadata": meta,
                    "authorized": True
                }

        # Check Twitch
        if "twitch.tv" in url:
            return {
                "valid": True,
                "platform": "TWITCH",
                "video_id": url.split("/")[-1],
                "metadata": {"title": "Twitch Stream Recording", "duration": 3600},
                "authorized": True
            }

        # Check Kick
        if "kick.com" in url:
            return {
                "valid": True,
                "platform": "KICK",
                "video_id": url.split("/")[-1],
                "metadata": {"title": "Kick Stream Recording", "duration": 3600},
                "authorized": True
            }

        return {"valid": False, "error": "Unsupported video URL format. Please provide a valid YouTube, Twitch, or Kick URL."}
