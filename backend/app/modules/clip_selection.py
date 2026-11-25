# backend/app/modules/clip_selection.py
from typing import List, Dict, Any
from app.services.moment_engine import SmartMomentEngine

class ClipSelection:
    """Consolidates candidate moments across long streams and ranks top target clips."""

    @staticmethod
    def select_top_clips(candidate_moments: List[Dict[str, Any]], total_duration: float, target_count: int = 10) -> Dict[str, Any]:
        return SmartMomentEngine.consolidate_and_rank_moments(
            candidate_moments=candidate_moments,
            total_duration=total_duration,
            target_count=target_count
        )
