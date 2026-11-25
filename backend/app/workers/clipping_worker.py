import os
import sys
import math
import json
import time
import re
import uuid
import logging
import subprocess
import shutil
from pathlib import Path
from typing import Optional, List, Dict, Any
from datetime import datetime
from sqlalchemy.orm import Session

from app.core.config import settings
from app.core.paths import resolve_media_path
from app.core.database import SessionLocal
from app.models.video import Video
from app.models.job import ProcessingJob
from app.models.clip import Clip
from app.services.audio_analysis import AudioAnalysisService
from app.services.whisper_service import WhisperTranscriptionService
from app.services.moment_engine import SmartMomentEngine
from app.services.ffmpeg_render import FFmpegRenderEngine

logger = logging.getLogger(__name__)

def format_seconds(seconds: float) -> str:
    hrs = int(seconds // 3600)
    mins = int((seconds % 3600) // 60)
    secs = int(seconds % 60)
    if hrs > 0:
        return f"{hrs:02d}:{mins:02d}:{secs:02d}"
    return f"{mins:02d}:{secs:02d}"

def update_job_progress(
    job: ProcessingJob,
    db: Session,
    progress: int,
    step: str,
    stage: Optional[str] = None,
    metrics: Optional[Dict[str, Any]] = None
):
    """Updates job progress, step description, stage, real metrics, and worker heartbeat timestamp in MySQL."""
    job.progress_percentage = max(0, min(100, progress))
    job.current_step = step
    if stage:
        job.current_stage = stage
    if metrics:
        if "candidates_detected" in metrics:
            job.moments_detected_count = metrics["candidates_detected"]
        if "candidates_accepted" in metrics:
            job.clips_selected_count = metrics["candidates_accepted"]
        if "candidates_rejected" in metrics:
            job.candidates_rejected_count = metrics["candidates_rejected"]
        if "clips_rendered" in metrics:
            job.clips_rendered_count = metrics["clips_rendered"]
        if "clips_failed" in metrics:
            job.clips_failed_count = metrics["clips_failed"]
        job.metrics_json = json.dumps(metrics)
    job.worker_heartbeat_at = datetime.utcnow()
    try:
        db.commit()
    except Exception as ex:
        logger.warning(f"Failed to commit progress update: {ex}")
        db.rollback()

def download_youtube_video_slice(
    youtube_url: str,
    output_slice_path: Path,
    start_time: float,
    end_time: float
) -> Path:
    """
    Downloads an exact high-definition 1080p video slice for a detected moment using yt-dlp.
    Uses sys.executable to ensure active environment binary is used.
    """
    output_slice_path.parent.mkdir(parents=True, exist_ok=True)
    cmd = [
        sys.executable, "-m", "yt_dlp",
        "-f", "bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]/best[height<=1080][ext=mp4]/best",
        "--download-sections", f"*{round(start_time, 1)}-{round(end_time, 1)}",
        "-o", str(output_slice_path),
        "--force-overwrites",
        "--no-playlist",
        youtube_url
    ]

    result = subprocess.run(cmd, capture_output=True, text=True, timeout=180)
    if result.returncode != 0 or not output_slice_path.exists() or output_slice_path.stat().st_size == 0:
        raise RuntimeError(f"Failed to download YouTube video slice: {result.stderr[:250] if result.stderr else 'Unknown error'}")

    return output_slice_path

def run_clipping_pipeline(
    job_id: str,
    video_id: str,
    target_count: int = 10,
    burn_subtitles: bool = True
):
    """
    Intelligent Chunk-Based Long-Video Processing Engine:
    - Resolves local and remote media with universal path resolution.
    - Slices audio/video into sequential chunks for memory efficiency.
    - Runs acoustic RMS energy extraction and Whisper speech-to-text.
    - Checkpoint saving after every chunk for instant resume capability.
    - Real stage-based progress reporting and worker heartbeat updates.
    - Safe exception handling with transaction rollback.
    - Consolidates and deduplicates candidate moments across chunk boundaries.
    - Renders top N clips in 1080x1920 9:16 vertical format with NVENC / CPU encoding.
    """
    db: Session = SessionLocal()
    try:
        job = db.query(ProcessingJob).filter(ProcessingJob.id == job_id).first()
        video = db.query(Video).filter(Video.id == video_id).first()

        if not job or not video:
            logger.error(f"Job {job_id} or Video {video_id} not found in database.")
            return

        source_dur = float(video.duration_seconds or 60.0)
        job.video_total_seconds = source_dur
        job.clips_total_target = target_count
        job.status = "PROCESSING"
        update_job_progress(job, db, 2, "Job initialized by AI worker...", stage="Initialization")

        # Step 1: Ensure master 16kHz mono audio is available
        update_job_progress(job, db, 5, "Validating source video stream and media properties...", stage="Source Validation")

        # Determine how much source material is needed for this job
        # Each viral clip needs sufficient footage to identify distinct high-energy peaks
        requested_seconds = max(180.0, float(target_count) * 45.0)
        if source_dur > 60.0:
            needed_audio_seconds = min(source_dur, requested_seconds)
        else:
            needed_audio_seconds = requested_seconds

        master_audio_wav = settings.audio_dir / f"master_audio_{video.id}.wav"
        resolved_video_path = resolve_media_path(video.file_path)

        current_audio_dur = 0.0
        if master_audio_wav.exists() and master_audio_wav.stat().st_size > 1024:
            try:
                current_audio_dur = AudioAnalysisService.get_wav_duration(master_audio_wav)
            except Exception:
                current_audio_dur = 0.0

        needs_fresh_audio = (
            not master_audio_wav.exists()
            or master_audio_wav.stat().st_size < 1024
            or (current_audio_dur < needed_audio_seconds - 5.0 and source_dur > current_audio_dur + 5.0)
        )

        if needs_fresh_audio:
            update_job_progress(job, db, 7, f"Extracting master 16kHz mono audio stream ({format_seconds(needed_audio_seconds)} needed)...", stage="Audio Extraction")

            if video.source_type == "YOUTUBE":
                yt_url = video.youtube_url or f"https://www.youtube.com/watch?v={video.youtube_id}"
                temp_m4a = settings.audio_dir / f"yt_stream_{video.youtube_id}.m4a"
                
                # Cleanup any stale partial downloads
                if temp_m4a.exists():
                    try:
                        temp_m4a.unlink()
                    except Exception:
                        pass

                download_cmd = [
                    sys.executable, "-m", "yt_dlp",
                    "--js-runtimes", "node",
                    "--socket-timeout", "15",
                    "--retries", "3",
                    "--fragment-retries", "5",
                    "--concurrent-fragments", "4",
                    "-f", "ba[ext=m4a]/ba/b",
                    "-o", str(temp_m4a),
                    "--no-playlist",
                    "--force-overwrites",
                    "--download-sections", f"*0-{int(needed_audio_seconds)}",
                    yt_url
                ]

                logger.info(f"Downloading YouTube audio for job {job_id}: {' '.join(download_cmd)}")
                part_m4a = Path(str(temp_m4a) + ".part")
                target_bytes = max(10 * 1024 * 1024, int(needed_audio_seconds * 24 * 1024))
                
                # Execute with real-time progress capture
                try:
                    proc = subprocess.Popen(
                        download_cmd,
                        stdout=subprocess.PIPE,
                        stderr=subprocess.STDOUT,
                        text=True,
                        encoding="utf-8",
                        errors="replace"
                    )
                    
                    last_pct_update = time.time()
                    for line in iter(proc.stdout.readline, ''):
                        if not line:
                            break
                        line_str = line.strip()

                        # If we have acquired sufficient audio bytes for live/long stream, stop yt-dlp early
                        if part_m4a.exists() and part_m4a.stat().st_size >= target_bytes:
                            logger.info(f"Acquired sufficient audio stream buffer ({part_m4a.stat().st_size} bytes), finishing download.")
                            try:
                                proc.terminate()
                            except Exception:
                                pass
                            break

                        # Parse download progress: [download]  45.2% of ~ 15.00MiB at  2.50MiB/s
                        if "[download]" in line_str and "%" in line_str:
                            now = time.time()
                            if now - last_pct_update >= 1.5:
                                match_pct = re.search(r"(\d+(?:\.\d+)?)%", line_str)
                                if match_pct:
                                    pct_val = min(9.0, 7.0 + (float(match_pct.group(1)) / 100.0) * 2.0)
                                    speed_match = re.search(r"at\s+([^\s]+)", line_str)
                                    speed_str = f" ({speed_match.group(1)})" if speed_match else ""
                                    update_job_progress(job, db, int(pct_val), f"Downloading audio slice {int(pct_val)}%{speed_str}...", stage="Audio Extraction")
                                    last_pct_update = now

                    try:
                        proc.wait(timeout=180)
                    except subprocess.TimeoutExpired:
                        proc.kill()
                        logger.warning("yt-dlp slice download timed out after 180s, checking output file...")
                except Exception as e:
                    logger.warning(f"yt-dlp stream read warning: {e}")

                if temp_m4a.exists() and temp_m4a.stat().st_size > 1024:
                    AudioAnalysisService.extract_mono_wav(temp_m4a, master_audio_wav, duration_limit=needed_audio_seconds)
                    try:
                        temp_m4a.unlink()
                    except Exception:
                        pass
                elif part_m4a.exists() and part_m4a.stat().st_size > 1024:
                    logger.info(f"Extracting mono wav from stream buffer ({part_m4a.stat().st_size} bytes)")
                    AudioAnalysisService.extract_mono_wav(part_m4a, master_audio_wav, duration_limit=needed_audio_seconds)
                elif resolved_video_path and resolved_video_path.exists() and resolved_video_path.stat().st_size > 1024:
                    AudioAnalysisService.extract_mono_wav(resolved_video_path, master_audio_wav, duration_limit=needed_audio_seconds)
                else:
                    raise RuntimeError(f"Failed to acquire YouTube audio stream for {video.youtube_id}. Verify network connectivity.")
            elif resolved_video_path and resolved_video_path.exists() and resolved_video_path.stat().st_size > 1024:
                logger.info(f"Extracting mono audio from resolved local video: {resolved_video_path}")
                AudioAnalysisService.extract_mono_wav(resolved_video_path, master_audio_wav)
            else:
                raise FileNotFoundError(f"Source video media not found for video ID {video.id} (looked for {video.file_path})")

            # Reset checkpoints since fresh/expanded audio was demuxed
            job.checkpoint_chunk_index = 0
            job.checkpoint_data = None
            db.commit()

        # Verify master audio is valid
        if not master_audio_wav.exists() or master_audio_wav.stat().st_size == 0:
            raise RuntimeError(f"Master audio extraction produced empty or missing file: {master_audio_wav}")

        # Compute audio duration and chunk layout
        audio_dur = AudioAnalysisService.get_wav_duration(master_audio_wav)
        total_duration = max(audio_dur, source_dur)
        job.video_total_seconds = total_duration

        # Chunk size: 20s to 30s chunks so progress updates smoothly and checkpoints save rapidly
        chunk_duration = 20.0 if audio_dur <= 120.0 else 30.0
        total_chunks = max(1, int(math.ceil(audio_dur / chunk_duration)))

        update_job_progress(
            job, db, 10,
            f"Master audio prepared ({format_seconds(audio_dur)}). Divided into {total_chunks} processing chunks.",
            stage="Chunk Planning"
        )

        # Load checkpoint if resuming
        accumulated_moments = []
        start_chunk = 0
        if job.checkpoint_chunk_index and job.checkpoint_chunk_index > 0 and job.checkpoint_data:
            try:
                accumulated_moments = json.loads(job.checkpoint_data)
                start_chunk = min(job.checkpoint_chunk_index, total_chunks)
                logger.info(f"Resuming job {job_id} from chunk {start_chunk + 1}/{total_chunks}")
            except Exception as e:
                logger.warning(f"Failed to parse checkpoint data: {e}")
                accumulated_moments = []
                start_chunk = 0

        # Step 2: Sequential Chunk Analysis with Granular Sub-Stage Tracking (10% -> 65%)
        chunk_progress_span = 55.0  # 10% to 65%
        per_chunk_pct = chunk_progress_span / total_chunks

        for chunk_idx in range(start_chunk, total_chunks):
            # Check for user cancellation
            db.refresh(job)
            if job.status in ("CANCELLED", "FAILED"):
                logger.info(f"Job {job_id} was cancelled by user.")
                return

            t_start = round(chunk_idx * chunk_duration, 2)
            t_end = round(min(audio_dur, (chunk_idx + 1) * chunk_duration), 2)
            if t_end <= t_start:
                break

            chunk_base_progress = 10.0 + (chunk_idx * per_chunk_pct)

            # Sub-stage A: Audio Slicing
            update_job_progress(
                job, db,
                int(chunk_base_progress + (per_chunk_pct * 0.15)),
                f"Chunk {chunk_idx + 1}/{total_chunks} ({format_seconds(t_start)} - {format_seconds(t_end)}): Extracting 16kHz audio slice...",
                stage="Audio Slicing"
            )

            chunk_wav = settings.audio_dir / f"chunk_{video.id[:8]}_{chunk_idx}.wav"
            try:
                AudioAnalysisService.extract_chunk_mono_wav(
                    source_path=master_audio_wav,
                    output_wav_path=chunk_wav,
                    start_time=t_start,
                    duration=round(t_end - t_start, 2)
                )

                # Sub-stage B: Acoustic RMS Energy Analysis
                update_job_progress(
                    job, db,
                    int(chunk_base_progress + (per_chunk_pct * 0.35)),
                    f"Chunk {chunk_idx + 1}/{total_chunks}: Analyzing acoustic energy and reaction peaks...",
                    stage="Peak Detection"
                )
                audio_analysis = AudioAnalysisService.analyze_audio_energy(chunk_wav)

                # Sub-stage C: Whisper Transcription
                whisper_model_name = settings.WHISPER_MODEL or "tiny"
                update_job_progress(
                    job, db,
                    int(chunk_base_progress + (per_chunk_pct * 0.65)),
                    f"Chunk {chunk_idx + 1}/{total_chunks}: Transcribing dialogue with Whisper ({whisper_model_name})...",
                    stage="Whisper Transcription"
                )
                transcript = WhisperTranscriptionService.transcribe(chunk_wav, model_name=whisper_model_name)

                # Sub-stage D: Candidate Moments Extraction & Checkpoint Save
                chunk_candidates = SmartMomentEngine.analyze_chunk(
                    chunk_start=t_start,
                    chunk_end=t_end,
                    audio_analysis=audio_analysis,
                    transcript_data=transcript
                )
                accumulated_moments.extend(chunk_candidates)

                # Save Checkpoint in Database
                chunk_done_progress = int(10.0 + ((chunk_idx + 1) * per_chunk_pct))
                job.checkpoint_chunk_index = chunk_idx + 1
                job.checkpoint_data = json.dumps(accumulated_moments)
                job.video_analyzed_seconds = t_end
                job.moments_detected_count = len(accumulated_moments)
                update_job_progress(
                    job, db,
                    chunk_done_progress,
                    f"Analyzed Chunk {chunk_idx + 1}/{total_chunks} ({format_seconds(t_start)} - {format_seconds(t_end)}) | {len(accumulated_moments)} moments found | Checkpoint saved",
                    stage="Chunk Checkpoint"
                )

            except Exception as chunk_err:
                logger.error(f"Error processing chunk {chunk_idx + 1}: {chunk_err}")
                # Save partial moments and continue to next chunk or raise
                if not accumulated_moments and chunk_idx == 0:
                    raise chunk_err
            finally:
                if chunk_wav.exists():
                    try:
                        chunk_wav.unlink()
                    except Exception:
                        pass

        # Step 3: Consolidate and Rank Moments Across Entire Video (66% -> 70%)
        update_job_progress(job, db, 66, "Consolidating peak candidates, deduplicating, and scoring viral potential...", stage="Moment Selection")

        analyzed_duration = min(total_duration, audio_dur)
        ranked_result = SmartMomentEngine.consolidate_and_rank_moments(
            candidate_moments=accumulated_moments,
            total_duration=analyzed_duration,
            target_count=target_count,
            min_clip_duration=8.0,
            max_clip_duration=30.0
        )

        moments = ranked_result.get("moments", [])
        if not moments:
            # Fallback anchor if audio was silent
            moments = [{
                "clip_index": 1,
                "start_time": 0.0,
                "end_time": min(20.0, analyzed_duration),
                "duration": min(20.0, analyzed_duration),
                "title": "Epic Stream Highlight 🔥",
                "description": "Viral highlight automatically extracted by GTA CLIP AI.",
                "hashtags": "#Shorts #GTA5 #Gaming",
                "virality_score": 0.85,
                "words": []
            }]

        job.clips_selected_count = len(moments)
        update_job_progress(
            job, db, 70,
            f"Selected {len(moments)} best viral moments. Preparing 9:16 vertical render engine...",
            stage="Render Preparation"
        )

        # Step 4: Render Vertical Clips with NVENC / CPU (70% -> 95%)
        rendered_clips = []
        render_progress_span = 25.0  # 70% to 95%
        per_clip_pct = render_progress_span / max(1, len(moments))

        # Ensure source video is locally cached before entering rendering loop
        resolved_source_video = resolve_media_path(video.file_path)
        if not resolved_source_video or not resolved_source_video.exists():
            if video.source_type == "YOUTUBE" and video.youtube_id:
                candidate = settings.upload_dir / f"yt_{video.youtube_id}.mp4"
                if candidate.exists() and candidate.stat().st_size > 1_000_000:
                    resolved_source_video = candidate
                    video.file_path = f"storage/uploads/yt_{video.youtube_id}.mp4"
                    db.commit()
                    logger.info(f"Associated existing cached YouTube video: {candidate}")
                else:
                    logger.info(f"Ingesting master YouTube video for {video.youtube_id}...")
                    update_job_progress(job, db, 68, f"Downloading master video stream for {video.youtube_id}...", stage="Master Video Ingestion")
                    try:
                        from app.services.youtube_service import YouTubeService
                        ingest_res = YouTubeService.ingest_youtube_video(video.youtube_id, download_clip_duration_seconds=450)
                        candidate = Path(ingest_res["local_file_path"])
                        if candidate.exists() and candidate.stat().st_size > 1_000_000:
                            resolved_source_video = candidate
                            video.file_path = f"storage/uploads/{candidate.name}"
                            db.commit()
                    except Exception as yt_ingest_err:
                        logger.error(f"Failed to ingest master YouTube video: {yt_ingest_err}")

        for i, m in enumerate(moments):
            # Check for user cancellation
            db.refresh(job)
            if job.status in ("CANCELLED", "FAILED"):
                logger.info(f"Job {job_id} cancelled during rendering.")
                return

            clip_uuid = str(uuid.uuid4())
            output_clip_name = f"clip_{video.id[:8]}_{i + 1}_{clip_uuid[:6]}.mp4"
            output_clip_path = settings.output_dir / output_clip_name

            current_render_pct = int(70.0 + (i * per_clip_pct))
            score_pct = round(m["virality_score"] * 100, 1) if m.get("virality_score", 0) <= 1.0 else round(m["virality_score"], 1)

            update_job_progress(
                job, db,
                current_render_pct,
                f"Rendering 9:16 vertical clip {i + 1}/{len(moments)} (Score: {score_pct}%, NVENC)...",
                stage=f"Rendering Clip {i + 1}"
            )

            m_start = m["start_time"]
            m_end = m["end_time"]

            try:
                if not resolved_source_video or not resolved_source_video.exists():
                    raise FileNotFoundError(f"Source video media is not available on disk for rendering clip {i + 1}")

                # Render vertical clip with strict validation built-in
                FFmpegRenderEngine.render_vertical_clip(
                    source_video_path=resolved_source_video,
                    start_time=m_start,
                    end_time=m_end,
                    output_clip_path=output_clip_path,
                    words=m.get("words", []),
                    burn_subtitles=burn_subtitles
                )

                # Validate output strictly
                valid_meta = FFmpegRenderEngine.validate_rendered_clip(output_clip_path)

                # Generate high-quality thumbnail image at 1.0s mark
                thumb_name = f"clip_{video.id[:8]}_{i + 1}_{clip_uuid[:6]}_thumb.jpg"
                thumb_path = settings.output_dir / thumb_name
                try:
                    ffmpeg_bin = shutil.which("ffmpeg") or "ffmpeg"
                    subprocess.run(
                        [ffmpeg_bin, "-y", "-ss", "00:00:01", "-i", str(output_clip_path), "-vframes", "1", "-update", "1", "-q:v", "2", str(thumb_path)],
                        stdout=subprocess.DEVNULL,
                        stderr=subprocess.DEVNULL,
                        check=False
                    )
                except Exception as ex:
                    logger.warning(f"Could not render thumbnail for clip {clip_uuid}: {ex}")

                rel_clip_path = f"storage/output/{output_clip_name}"
                rel_thumb_path = f"storage/output/{thumb_name}" if thumb_path.exists() else None

                # Persist clip in MySQL ONLY AFTER validation succeeds
                db_clip = Clip(
                    id=clip_uuid,
                    job_id=job.id,
                    video_id=video.id,
                    user_id=getattr(job, "user_id", None) or getattr(video, "user_id", None),
                    project_id=getattr(video, "project_id", None),
                    clip_index=m["clip_index"],
                    start_time=m["start_time"],
                    end_time=m["end_time"],
                    duration=valid_meta["duration"],
                    title=m["title"],
                    description=m["description"],
                    hashtags=m["hashtags"],
                    virality_score=m["virality_score"],
                    file_path=rel_clip_path,
                    thumbnail_path=rel_thumb_path,
                    file_size_bytes=valid_meta["file_size"],
                    aspect_ratio="9:16"
                )
                db.add(db_clip)
                db.commit()
                rendered_clips.append(db_clip)
                job.clips_rendered_count = len(rendered_clips)
                db.commit()

            except Exception as clip_err:
                logger.error(f"Failed to render individual clip {i + 1}: {clip_err}")
                db.rollback()
                if output_clip_path.exists():
                    try:
                        output_clip_path.unlink()
                    except Exception:
                        pass

        # Step 5: Final Verification & Accurate Status Reporting (95% -> 100%)
        update_job_progress(job, db, 96, "Verifying generated MP4 video streams and thumbnails...", stage="Verification")

        rendered_count = len(rendered_clips)
        metrics_payload = {
            "target_requested": target_count,
            "candidates_detected": len(accumulated_moments),
            "candidates_accepted": len(moments),
            "candidates_rejected": max(0, len(accumulated_moments) - len(moments)),
            "clips_rendered": rendered_count,
            "clips_failed": max(0, len(moments) - rendered_count)
        }

        if rendered_count >= target_count and target_count > 0:
            job.status = "COMPLETED"
            update_job_progress(
                job, db, 100,
                f"Successfully generated all {rendered_count} viral 9:16 vertical clips!",
                stage="Completed",
                metrics=metrics_payload
            )
        elif rendered_count > 0:
            is_complete = (rendered_count >= len(moments))
            job.status = "COMPLETED" if is_complete else "PARTIALLY_COMPLETED"
            pct = 100 if is_complete else int((rendered_count / target_count) * 100)
            update_job_progress(
                job, db, pct,
                f"Successfully generated {rendered_count} viral clips ({rendered_count}/{target_count} target).",
                stage="Completed" if is_complete else "Partially Completed",
                metrics=metrics_payload
            )
        else:
            job.status = "FAILED"
            job.error_message = "No valid clips could be extracted from footage."
            update_job_progress(job, db, 100, "No usable clips generated.", stage="Failed", metrics=metrics_payload)

        job.clips_rendered_count = rendered_count
        db.commit()

    except Exception as e:
        logger.exception(f"Clipping pipeline error: {e}")
        try:
            db.rollback()
            job = db.query(ProcessingJob).filter(ProcessingJob.id == job_id).first()
            if job:
                job.status = "FAILED"
                err_detail = f"Failed in stage '{job.current_stage or 'Pipeline'}': {str(e)}"
                job.error_message = err_detail
                job.current_step = err_detail
                job.worker_heartbeat_at = datetime.utcnow()
                db.commit()
        except Exception as rollback_err:
            logger.error(f"Failed to record FAILED status for job {job_id}: {rollback_err}")
    finally:
        db.close()
