# backend/app/modules/youtube_oauth.py
import urllib.request
import urllib.parse
import json
from typing import Dict, Any, Optional

class YouTubeOAuth:
    """Manages Google OAuth 2.0 token refreshes and channel authorization."""

    @staticmethod
    def refresh_access_token(client_id: str, client_secret: str, refresh_token: str) -> Optional[Dict[str, Any]]:
        try:
            url = "https://oauth2.googleapis.com/token"
            data = urllib.parse.urlencode({
                "client_id": client_id,
                "client_secret": client_secret,
                "refresh_token": refresh_token,
                "grant_type": "refresh_token"
            }).encode("utf-8")
            req = urllib.request.Request(url, data=data)
            with urllib.request.urlopen(req, timeout=15) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except Exception:
            return None
