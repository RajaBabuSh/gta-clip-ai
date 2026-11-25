# backend/app/modules/scene_detection.py
from pathlib import Path
from typing import List, Dict, Any

class SceneDetection:
    """Detects visual scene transitions and camera cuts using FFmpeg scene filter."""

    @staticmethod
    def detect_scene_cuts(video_path: Path, start_time: float, duration: float, threshold: float = 0.35) -> List[float]:
        # Fast scene transition timestamp extraction
        import subprocess
        cuts = []
        try:
            cmd = [
                "ffmpeg", "-ss", str(start_time), "-t", str(duration),
                "-i", str(video_path),
                "-filter:v", f"select='gt(scene,{threshold})',showinfo",
                "-f", "null", "-"
            ]
            res = subprocess.run(cmd, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True, check=False)
            import re
            for match in re.finditer(r"pts_time:([0-9\.]+)", res.stderr):
                cuts.append(float(match.group(1)) + start_time)
        except Exception:
            pass
        return cuts
