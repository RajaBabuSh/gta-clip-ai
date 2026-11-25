# backend/app/modules/seo_generator.py
from typing import Dict, Any, List

class SeoGenerator:
    """Generates engaging, compliant YouTube Shorts and TikTok titles, descriptions, and hashtags."""

    @staticmethod
    def generate_seo(title: str, description: str = "", extra_tags: List[str] = None) -> Dict[str, Any]:
        tags = extra_tags or ["#GTAV", "#GTA5", "#GamingShorts", "#Shorts", "#ViralGaming"]
        clean_title = title.replace("[Clip", "").replace("]", "").strip()
        
        return {
            "youtube_title": f"{clean_title} 🔥 #Shorts",
            "instagram_caption": f"Watch till the end! 🎮 What a crazy GTA moment. Follow for daily clips! #GTA5",
            "description": f"{title}\n\nWatch until the end for top viral gaming highlights! Subscribe for daily GTA clips! 🎮🔥\n\n{' '.join(tags)}",
            "hashtags": tags,
            "keywords": ["gta 5", "gta funny moments", "gaming shorts", "stream highlights"],
            "suggested_category": "Gaming",
            "thumbnail_text": "NO WAY! 💀"
        }
