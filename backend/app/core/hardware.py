import subprocess
import shutil
import logging
import torch
from app.core.config import settings

logger = logging.getLogger(__name__)

class HardwareDetector:
    _cached_nvenc_available = None
    _cached_nvidia_smi = None

    @classmethod
    def check_nvidia_smi(cls) -> dict:
        """Inspects NVIDIA GPU via nvidia-smi command."""
        smi_path = shutil.which("nvidia-smi")
        if not smi_path:
            return {"available": False, "gpu_name": None, "vram_total": None}

        try:
            cmd = [
                smi_path,
                "--query-gpu=name,memory.total,memory.free,driver_version",
                "--format=csv,noheader,nounits"
            ]
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=3)
            if result.returncode == 0 and result.stdout.strip():
                lines = result.stdout.strip().split("\n")
                first = [x.strip() for x in lines[0].split(",")]
                return {
                    "available": True,
                    "gpu_name": first[0] if len(first) > 0 else "Unknown NVIDIA GPU",
                    "vram_total_mb": int(first[1]) if len(first) > 1 and first[1].isdigit() else 6144,
                    "vram_free_mb": int(first[2]) if len(first) > 2 and first[2].isdigit() else None,
                    "driver_version": first[3] if len(first) > 3 else "Unknown"
                }
        except Exception as e:
            logger.warning(f"nvidia-smi check failed: {e}")

        return {"available": False, "gpu_name": None, "vram_total_mb": None}

    @classmethod
    def check_nvenc_encoder(cls) -> bool:
        """Checks if ffmpeg supports h264_nvenc hardware acceleration."""
        if cls._cached_nvenc_available is not None:
            return cls._cached_nvenc_available

        ffmpeg_bin = shutil.which("ffmpeg")
        if not ffmpeg_bin:
            cls._cached_nvenc_available = False
            return False

        try:
            cmd = [ffmpeg_bin, "-encoders"]
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=5)
            cls._cached_nvenc_available = "h264_nvenc" in result.stdout
        except Exception as e:
            logger.warning(f"FFmpeg encoder inspection error: {e}")
            cls._cached_nvenc_available = False

        return cls._cached_nvenc_available

    @classmethod
    def get_video_encoder(cls) -> str:
        """Returns 'h264_nvenc' if settings allow and hardware supports it, else 'libx264' CPU fallback."""
        if settings.USE_NVENC_ENCODING and cls.check_nvenc_encoder():
            return "h264_nvenc"
        return "libx264"

    @classmethod
    def get_whisper_device(cls) -> str:
        """Determines optimal Whisper compute device (cuda or cpu)."""
        if settings.WHISPER_DEVICE == "cuda" and torch.cuda.is_available():
            return "cuda"
        if settings.WHISPER_DEVICE == "auto":
            return "cuda" if torch.cuda.is_available() else "cpu"
        return "cpu"

    @classmethod
    def get_system_diagnostic(cls) -> dict:
        """Comprehensive hardware and toolchain diagnostic."""
        gpu_info = cls.check_nvidia_smi()
        nvenc_ok = cls.check_nvenc_encoder()
        torch_cuda = torch.cuda.is_available()
        ffmpeg_bin = shutil.which("ffmpeg")
        ffprobe_bin = shutil.which("ffprobe")

        return {
            "gpu": {
                "detected": gpu_info["available"],
                "name": gpu_info.get("gpu_name"),
                "vram_total_mb": gpu_info.get("vram_total_mb"),
                "driver_version": gpu_info.get("driver_version")
            },
            "ai_acceleration": {
                "torch_cuda": torch_cuda,
                "whisper_device": cls.get_whisper_device(),
                "mode": "GPU (CUDA)" if cls.get_whisper_device() == "cuda" else "CPU Optimized"
            },
            "video_acceleration": {
                "ffmpeg_installed": ffmpeg_bin is not None,
                "ffprobe_installed": ffprobe_bin is not None,
                "nvenc_supported": nvenc_ok,
                "active_encoder": cls.get_video_encoder(),
                "mode": "NVIDIA NVENC (RTX 3050)" if cls.get_video_encoder() == "h264_nvenc" else "CPU (libx264)"
            }
        }
