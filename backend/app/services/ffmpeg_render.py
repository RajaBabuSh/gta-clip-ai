import os
import json
import shutil
import logging
import subprocess
from pathlib import Path
from typing import List, Dict, Any, Optional
from app.core.config import settings
from app.core.hardware import HardwareDetector

logger = logging.getLogger(__name__)

class FFmpegRenderEngine:
    @staticmethod
    def format_ass_time(seconds: float) -> str:
        """Formats seconds to ASS time format: H:MM:SS.cs"""
        hrs = int(seconds // 3600)
        mins = int((seconds % 3600) // 60)
        secs = int(seconds % 60)
        centis = int(round((seconds - int(seconds)) * 100))
        if centis >= 100:
            centis = 99
        return f"{hrs}:{mins:02d}:{secs:02d}.{centis:02d}"

    @staticmethod
    def format_srt_time(seconds: float) -> str:
        """Formats seconds to SRT time format: HH:MM:SS,mmm"""
        hrs = int(seconds // 3600)
        mins = int((seconds % 3600) // 60)
        secs = int(seconds % 60)
        millis = int(round((seconds - int(seconds)) * 1000))
        if millis >= 1000:
            millis = 999
        return f"{hrs:02d}:{mins:02d}:{secs:02d},{millis:03d}"

    @staticmethod
    def format_vtt_time(seconds: float) -> str:
        """Formats seconds to WebVTT time format: HH:MM:SS.mmm"""
        hrs = int(seconds // 3600)
        mins = int((seconds % 3600) // 60)
        secs = int(seconds % 60)
        millis = int(round((seconds - int(seconds)) * 1000))
        if millis >= 1000:
            millis = 999
        return f"{hrs:02d}:{mins:02d}:{secs:02d}.{millis:03d}"

    @classmethod
    def generate_srt(
        cls,
        words: List[Dict[str, Any]],
        output_srt_path: Path
    ) -> Optional[Path]:
        """Generates standard SRT subtitles from word-level timestamps."""
        if not words:
            return None
        output_srt_path.parent.mkdir(parents=True, exist_ok=True)
        chunk_size = 5
        entries = []
        entry_idx = 1
        for i in range(0, len(words), chunk_size):
            chunk = words[i : i + chunk_size]
            if not chunk:
                continue
            start_str = cls.format_srt_time(chunk[0]["start"])
            end_str = cls.format_srt_time(chunk[-1]["end"])
            text = " ".join(w["word"] for w in chunk)
            entries.append(f"{entry_idx}\n{start_str} --> {end_str}\n{text}\n")
            entry_idx += 1

        output_srt_path.write_text("\n".join(entries), encoding="utf-8")
        return output_srt_path

    @classmethod
    def generate_vtt(
        cls,
        words: List[Dict[str, Any]],
        output_vtt_path: Path
    ) -> Optional[Path]:
        """Generates standard WebVTT subtitles."""
        if not words:
            return None
        output_vtt_path.parent.mkdir(parents=True, exist_ok=True)
        chunk_size = 5
        lines = ["WEBVTT\n"]
        for i in range(0, len(words), chunk_size):
            chunk = words[i : i + chunk_size]
            if not chunk:
                continue
            start_str = cls.format_vtt_time(chunk[0]["start"])
            end_str = cls.format_vtt_time(chunk[-1]["end"])
            text = " ".join(w["word"] for w in chunk)
            lines.append(f"{start_str} --> {end_str}\n{text}\n")

        output_vtt_path.write_text("\n".join(lines), encoding="utf-8")
        return output_vtt_path

    @classmethod
    def generate_ass_subtitles(
        cls,
        words: List[Dict[str, Any]],
        output_ass_path: Path,
        style_preset: str = "mrbeast"
    ) -> Optional[Path]:
        """
        Generates Shorts-style karaoke animated ASS subtitles.
        Active word highlighted in vibrant yellow (&H0000FFFF) with thick outline.
        Supports presets: 'mrbeast', 'hormozi', 'cyber', 'minimal'.
        """
        if not words:
            return None

        output_ass_path.parent.mkdir(parents=True, exist_ok=True)

        primary_color = "&H00FFFFFF"
        active_color = "&H0000FFFF"  # Yellow default
        outline_color = "&H00000000"
        font_name = "Arial"
        font_size = 68

        if style_preset == "cyber":
            active_color = "&H00FFFF00"  # Cyan
            outline_color = "&H00FF00FF" # Magenta shadow
            font_size = 72
        elif style_preset == "hormozi":
            active_color = "&H0022FF22"  # Bright lime green
            font_name = "Impact"
            font_size = 74
        elif style_preset == "minimal":
            active_color = "&H00E0E0E0"
            font_size = 56

        header = f"""[Script Info]
ScriptType: v4.00+
PlayResX: 1080
PlayResY: 1920
ScaledBorderAndShadow: yes

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,{font_name},{font_size},{primary_color},{active_color},{outline_color},&H90000000,-1,0,0,0,100,100,1,0,1,5.0,2.5,2,40,40,340,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
"""
        dialogues = []
        chunk_size = 4
        for i in range(0, len(words), chunk_size):
            chunk = words[i : i + chunk_size]
            if not chunk:
                continue

            for target_idx, active_word in enumerate(chunk):
                w_start = active_word["start"]
                w_end = active_word["end"]
                if w_end <= w_start:
                    w_end = w_start + 0.2

                phrase_parts = []
                for idx, w in enumerate(chunk):
                    w_text = w["word"].upper()
                    if idx == target_idx:
                        phrase_parts.append(f"{{\\c{active_color}\\fscx108\\fscy108}}{w_text}{{\\rDefault}}")
                    else:
                        phrase_parts.append(f"{{\\c{primary_color}}}{w_text}")

                line_text = " ".join(phrase_parts)
                t_start_str = cls.format_ass_time(w_start)
                t_end_str = cls.format_ass_time(w_end)
                dialogues.append(f"Dialogue: 0,{t_start_str},{t_end_str},Default,,0,0,0,,{line_text}")

        if not dialogues:
            return None

        content = header + "\n".join(dialogues) + "\n"
        output_ass_path.write_text(content, encoding="utf-8")
        return output_ass_path

    @classmethod
    def extract_audio_mp3(
        cls,
        source_video_path: Path,
        start_time: float,
        end_time: float,
        output_mp3_path: Path
    ) -> Path:
        """Extracts high quality MP3 audio from a video segment."""
        output_mp3_path.parent.mkdir(parents=True, exist_ok=True)
        ffmpeg_bin = shutil.which("ffmpeg") or "ffmpeg"
        duration = max(1.0, end_time - start_time)

        cmd = [
            ffmpeg_bin,
            "-y",
            "-ss", str(round(start_time, 2)),
            "-i", str(source_video_path),
            "-t", str(round(duration, 2)),
            "-vn",
            "-c:a", "libmp3lame",
            "-b:a", "192k",
            str(output_mp3_path)
        ]
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
        if result.returncode != 0:
            raise RuntimeError(f"FFmpeg MP3 extraction failed: {result.stderr[-300:]}")
        return output_mp3_path

    @classmethod
    def validate_rendered_clip(cls, clip_path: Path) -> Dict[str, Any]:
        """
        Strictly validates the rendered MP4 file before accepting it:
        1. Output file exists.
        2. File size is meaningfully greater than zero (> 100 KB).
           Empty MP4 containers (e.g. 261 bytes) are strictly rejected.
        3. FFprobe successfully reads format and streams without error.
        4. At least one valid video stream exists with valid dimensions.
        5. Duration is > 0.5s.
        6. Video codec is browser-compatible.
        Returns verified metadata dictionary or raises RuntimeError.
        """
        if not clip_path.exists():
            raise RuntimeError(f"Output clip file does not exist: {clip_path}")

        file_size = clip_path.stat().st_size
        if file_size < 100_000:
            raise RuntimeError(
                f"Rendered clip is abnormally small ({file_size} bytes). "
                f"Empty MP4 containers (e.g. 261 bytes) are strictly rejected as invalid."
            )

        ffprobe_bin = shutil.which("ffprobe") or "ffprobe"
        cmd = [
            ffprobe_bin,
            "-v", "error",
            "-show_format",
            "-show_streams",
            "-of", "json",
            str(clip_path)
        ]
        res = subprocess.run(cmd, capture_output=True, text=True, timeout=15)
        if res.returncode != 0:
            raise RuntimeError(f"FFprobe failed to inspect output clip: {res.stderr[:200]}")

        try:
            data = json.loads(res.stdout)
        except Exception as e:
            raise RuntimeError(f"Invalid JSON from FFprobe on output clip: {e}")

        streams = data.get("streams", [])
        video_stream = next((s for s in streams if s.get("codec_type") == "video"), None)
        if not video_stream:
            raise RuntimeError("No video stream found in rendered clip file (empty container).")

        width = int(video_stream.get("width") or 0)
        height = int(video_stream.get("height") or 0)
        if width <= 0 or height <= 0:
            raise RuntimeError(f"Invalid video dimensions in rendered clip: {width}x{height}")

        format_info = data.get("format", {})
        duration = float(format_info.get("duration") or video_stream.get("duration") or 0.0)
        if duration < 0.5:
            raise RuntimeError(f"Rendered clip duration is too short or zero: {duration}s")

        v_codec = video_stream.get("codec_name", "").lower()
        if v_codec not in ("h264", "avc", "hevc", "h265", "vp9", "av1"):
            raise RuntimeError(f"Unsupported video codec in output clip: {v_codec}")

        audio_stream = next((s for s in streams if s.get("codec_type") == "audio"), None)

        return {
            "file_size": file_size,
            "duration": duration,
            "width": width,
            "height": height,
            "video_codec": v_codec,
            "audio_codec": audio_stream.get("codec_name") if audio_stream else None,
            "has_audio": audio_stream is not None
        }

    @classmethod
    def render_vertical_clip(
        cls,
        source_video_path: Path,
        start_time: float,
        end_time: float,
        output_clip_path: Path,
        words: Optional[List[Dict[str, Any]]] = None,
        burn_subtitles: bool = True,
        aspect_ratio: str = "9:16",
        layout_mode: str = "blurred_background"
    ) -> Path:
        """
        Renders an aspect-ratio-formatted clip:
        - aspect_ratio: '9:16' (1080x1920), '16:9' (1920x1080), '1:1' (1080x1080), '4:5' (1080x1350)
        - layout_mode: 'blurred_background' (EiziClip style), 'fit_center' (padded), 'crop_fill'
        - Hardware accelerated via NVIDIA NVENC with CPU fallback.
        """
        output_clip_path.parent.mkdir(parents=True, exist_ok=True)
        ffmpeg_bin = shutil.which("ffmpeg")
        if not ffmpeg_bin:
            raise RuntimeError("FFmpeg executable not found in PATH.")

        # Target dimensions based on aspect ratio
        dim_map = {
            "9:16": (1080, 1920),
            "16:9": (1920, 1080),
            "1:1": (1080, 1080),
            "4:5": (1080, 1350)
        }
        target_w, target_h = dim_map.get(aspect_ratio, (1080, 1920))

        ass_path = None
        if burn_subtitles and words:
            ass_filename = f"sub_{output_clip_path.stem}.ass"
            ass_path = settings.subtitles_dir / ass_filename
            ass_path = cls.generate_ass_subtitles(words, ass_path)

        # Build filter graph according to layout_mode and aspect_ratio
        if aspect_ratio == "16:9":
            # Native widescreen - keep clean
            base_filter = f"[0:v]scale={target_w}:{target_h}:force_original_aspect_ratio=decrease,pad={target_w}:{target_h}:(ow-iw)/2:(oh-ih)/2[v]"
        elif layout_mode == "blurred_background":
            # EiziClip signature blurred background mode
            base_filter = (
                f"[0:v]scale={target_w}:{target_h}:force_original_aspect_ratio=increase,crop={target_w}:{target_h},boxblur=20:5[bg];"
                f"[0:v]scale={target_w}:-1[fg];"
                f"[bg][fg]overlay=0:(H-h)/2[v]"
            )
        elif layout_mode == "crop_fill":
            base_filter = f"[0:v]scale={target_w}:{target_h}:force_original_aspect_ratio=increase,crop={target_w}:{target_h}[v]"
        else: # fit_center
            base_filter = f"[0:v]scale={target_w}:{target_h}:force_original_aspect_ratio=decrease,pad={target_w}:{target_h}:(ow-iw)/2:(oh-ih)/2:color=black[v]"

        final_filter = base_filter
        if ass_path and ass_path.exists():
            escaped_ass = str(ass_path.resolve()).replace("\\", "/").replace(":", "\\:")
            final_filter = f"{base_filter};[v]subtitles='{escaped_ass}'[vout]"
            map_v = "[vout]"
        else:
            map_v = "[v]"

        duration = max(1.0, end_time - start_time)
        encoder = HardwareDetector.get_video_encoder()

        cmd = [
            ffmpeg_bin,
            "-y",
            "-ss", str(round(start_time, 2)),
            "-i", str(source_video_path),
            "-t", str(round(duration, 2)),
            "-filter_complex", final_filter,
            "-map", map_v,
            "-map", "0:a?",
            "-c:v", encoder,
            "-preset", "p4" if encoder == "h264_nvenc" else "fast",
            "-pix_fmt", "yuv420p",
            "-c:a", "aac",
            "-b:a", "192k",
            "-ar", "44100",
            "-movflags", "+faststart",
            str(output_clip_path)
        ]

        render_success = False
        last_error = ""

        try:
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
            if result.returncode == 0:
                try:
                    cls.validate_rendered_clip(output_clip_path)
                    render_success = True
                except Exception as val_ex:
                    logger.warning(f"Primary render validation failed: {val_ex}. Retrying without subtitles...")
                    last_error = str(val_ex)
            else:
                last_error = result.stderr[-300:] if result.stderr else f"Exit code {result.returncode}"

            if not render_success:
                # Retry 1: without subtitles
                cmd_nosub = list(cmd)
                cmd_nosub[cmd_nosub.index("-filter_complex") + 1] = base_filter
                cmd_nosub[cmd_nosub.index("-map") + 1] = "[v]"
                result = subprocess.run(cmd_nosub, capture_output=True, text=True, timeout=120)
                if result.returncode == 0:
                    try:
                        cls.validate_rendered_clip(output_clip_path)
                        render_success = True
                    except Exception as val_ex2:
                        last_error = str(val_ex2)

            if not render_success and encoder == "h264_nvenc":
                # Retry 2: with CPU libx264 fallback
                logger.warning("NVENC render produced invalid output. Retrying with CPU libx264...")
                cmd_cpu = list(cmd_nosub if 'cmd_nosub' in locals() else cmd)
                if "-filter_complex" in cmd_cpu:
                    cmd_cpu[cmd_cpu.index("-filter_complex") + 1] = base_filter
                if "-map" in cmd_cpu:
                    cmd_cpu[cmd_cpu.index("-map") + 1] = "[v]"
                if "h264_nvenc" in cmd_cpu:
                    cmd_cpu[cmd_cpu.index("h264_nvenc")] = "libx264"
                if "p4" in cmd_cpu:
                    cmd_cpu[cmd_cpu.index("p4")] = "fast"
                result = subprocess.run(cmd_cpu, capture_output=True, text=True, timeout=180)
                if result.returncode == 0:
                    try:
                        cls.validate_rendered_clip(output_clip_path)
                        render_success = True
                    except Exception as val_ex3:
                        last_error = str(val_ex3)

            if not render_success:
                if output_clip_path.exists():
                    output_clip_path.unlink()
                raise RuntimeError(f"FFmpeg render failed validation: {last_error}")

        except subprocess.TimeoutExpired:
            if output_clip_path.exists():
                output_clip_path.unlink()
            raise TimeoutError(f"Rendering clip {output_clip_path.name} timed out.")

        # Final guarantee validation
        cls.validate_rendered_clip(output_clip_path)
        return output_clip_path
