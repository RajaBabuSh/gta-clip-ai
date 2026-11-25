"use client";

import React, { useState } from "react";
import {
  CheckCircle2,
  Clapperboard,
  Clock,
  Film,
  Volume2,
  ShieldCheck,
  FileCheck,
  Layers,
  Sparkles,
  Zap,
  Loader2,
  Check,
  Cpu,
  StopCircle,
  Database,
  Flame,
  Tv
} from "lucide-react";

export interface VideoData {
  id: string;
  title: string;
  source_type: string;
  original_filename?: string;
  stored_filename?: string;
  file_size_bytes?: number;
  duration_seconds?: number;
  width?: number;
  height?: number;
  fps?: number;
  video_codec?: string;
  audio_codec?: string;
  audio_channels?: number;
  audio_sample_rate?: number;
  youtube_id?: string;
  youtube_url?: string;
  channel_title?: string;
  thumbnail_url?: string;
  created_at: string;
}

export interface ActiveJobData {
  id: string;
  status: string;
  progress_percentage: number;
  current_step?: string;
  error_message?: string;
  video_analyzed_seconds?: number;
  video_total_seconds?: number;
  moments_detected_count?: number;
  clips_selected_count?: number;
  clips_rendered_count?: number;
  clips_total_target?: number;
  checkpoint_chunk_index?: number;
}

interface VideoDetailsCardProps {
  video: VideoData;
  storageBaseUrl: string;
  onGenerateClips?: (targetCount: number, burnSubtitles: boolean) => void;
  onCancelJob?: (jobId: string) => void;
  isGenerating?: boolean;
  activeJob?: ActiveJobData | null;
  clipsCount?: number;
}

export const VideoDetailsCard: React.FC<VideoDetailsCardProps> = ({
  video,
  storageBaseUrl,
  onGenerateClips,
  onCancelJob,
  isGenerating = false,
  activeJob,
  clipsCount = 0
}) => {
  const [targetCount, setTargetCount] = useState<number>(10);
  const [burnSubtitles, setBurnSubtitles] = useState<boolean>(true);

  const formatDuration = (seconds?: number) => {
    if (!seconds || seconds <= 0) return "0:00";
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    if (hrs > 0) {
      return `${hrs}h ${mins}m ${secs < 10 ? "0" : ""}${secs}s`;
    }
    return `${mins}:${secs < 10 ? "0" : ""}${secs}`;
  };

  const videoUrl = `${storageBaseUrl}/uploads/${video.stored_filename}`;
  const fileSizeMb = video.file_size_bytes
    ? (video.file_size_bytes / (1024 * 1024)).toFixed(2) + " MB"
    : "Unknown";

  const handleStart = () => {
    if (onGenerateClips && !isGenerating) {
      onGenerateClips(targetCount, burnSubtitles);
    }
  };

  const progress = activeJob?.progress_percentage ?? 0;
  const currentStep = activeJob?.current_step || "Analyzing video stream & audio energy...";
  const totalSecs = activeJob?.video_total_seconds || video.duration_seconds || 0;
  const analyzedSecs = activeJob?.video_analyzed_seconds || 0;
  const remainingSecs = Math.max(0, totalSecs - analyzedSecs);

  return (
    <div className="w-full bg-zinc-900/90 border border-zinc-800 rounded-2xl p-6 shadow-2xl space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-800 pb-4">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1">
              <CheckCircle2 className="w-3.5 h-3.5" /> Media Stream Verified
            </span>
            {video.source_type === "YOUTUBE" ? (
              <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-500/10 text-red-400 border border-red-500/20 flex items-center gap-1">
                <Tv className="w-3.5 h-3.5" /> YouTube ID: {video.youtube_id}
              </span>
            ) : (
              <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">
                Direct Upload
              </span>
            )}
            <span className="text-xs text-zinc-500 font-mono">ID: {video.id.slice(0, 8)}...</span>
          </div>
          <h3 className="text-xl font-extrabold text-zinc-100 mt-1.5">{video.title}</h3>
          <p className="text-xs text-zinc-400">
            {video.source_type === "YOUTUBE" && video.channel_title
              ? `Channel: ${video.channel_title} • ${video.youtube_url}`
              : video.original_filename}
          </p>
        </div>

        <div className="text-xs text-zinc-400 bg-zinc-950 px-3 py-1.5 rounded-xl border border-zinc-800 self-start sm:self-auto flex items-center gap-1.5">
          <Database className="w-3.5 h-3.5 text-zinc-400" />
          <span>MySQL Source Recorded</span>
        </div>
      </div>

      {/* Main Content: Video Player + Stream Inspection Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* HTML5 Video Player */}
        <div className="lg:col-span-7 bg-zinc-950 rounded-xl overflow-hidden border border-zinc-800/80 shadow-inner flex flex-col justify-center">
          <video
            src={videoUrl}
            controls
            className="w-full max-h-[380px] object-contain rounded-xl"
            preload="metadata"
          >
            Your browser does not support the video tag.
          </video>
        </div>

        {/* Real FFprobe Extracted Metadata Grid */}
        <div className="lg:col-span-5 flex flex-col justify-between space-y-3">
          <div className="space-y-2">
            <h4 className="text-xs uppercase tracking-wider text-zinc-400 font-bold flex items-center gap-1.5">
              <Film className="w-4 h-4 text-rose-500" />
              Verified Stream Attributes (FFprobe)
            </h4>

            <div className="grid grid-cols-2 gap-2 text-xs">
              {/* Duration */}
              <div className="bg-zinc-950/70 border border-zinc-800 rounded-xl p-3">
                <div className="text-zinc-400 flex items-center gap-1 text-[11px]">
                  <Clock className="w-3 h-3 text-amber-400" /> Source Duration
                </div>
                <div className="text-zinc-100 font-bold mt-0.5">
                  {formatDuration(video.duration_seconds)}
                </div>
              </div>

              {/* Resolution */}
              <div className="bg-zinc-950/70 border border-zinc-800 rounded-xl p-3">
                <div className="text-zinc-400 flex items-center gap-1 text-[11px]">
                  <Layers className="w-3 h-3 text-cyan-400" /> Native Dimensions
                </div>
                <div className="text-zinc-100 font-bold mt-0.5">
                  {video.width} x {video.height}
                </div>
              </div>

              {/* Framerate */}
              <div className="bg-zinc-950/70 border border-zinc-800 rounded-xl p-3">
                <div className="text-zinc-400 flex items-center gap-1 text-[11px]">
                  <Clapperboard className="w-3 h-3 text-rose-400" /> Framerate
                </div>
                <div className="text-zinc-100 font-bold mt-0.5">{video.fps} FPS</div>
              </div>

              {/* Video Codec */}
              <div className="bg-zinc-950/70 border border-zinc-800 rounded-xl p-3">
                <div className="text-zinc-400 flex items-center gap-1 text-[11px]">
                  <Film className="w-3 h-3 text-violet-400" /> Video Codec
                </div>
                <div className="text-zinc-100 font-bold mt-0.5 uppercase">
                  {video.video_codec || "N/A"}
                </div>
              </div>

              {/* Audio Stream */}
              <div className="bg-zinc-950/70 border border-zinc-800 rounded-xl p-3">
                <div className="text-zinc-400 flex items-center gap-1 text-[11px]">
                  <Volume2 className="w-3 h-3 text-emerald-400" /> Audio Stream
                </div>
                <div className="text-zinc-100 font-bold mt-0.5">
                  {video.audio_codec ? `${video.audio_codec.toUpperCase()} (${video.audio_channels}ch)` : "No Audio"}
                </div>
              </div>

              {/* File Size */}
              <div className="bg-zinc-950/70 border border-zinc-800 rounded-xl p-3">
                <div className="text-zinc-400 flex items-center gap-1 text-[11px]">
                  <FileCheck className="w-3 h-3 text-blue-400" /> Ingested Cache
                </div>
                <div className="text-zinc-100 font-bold mt-0.5">{fileSizeMb}</div>
              </div>
            </div>
          </div>

          {/* Engine Status Callout */}
          <div className="bg-gradient-to-r from-rose-950/30 to-amber-950/20 border border-rose-900/30 rounded-xl p-3">
            <div className="text-xs font-semibold text-rose-200 flex items-center gap-1.5">
              <Cpu className="w-3.5 h-3.5 text-amber-400" />
              NVIDIA RTX 3050 NVENC & Whisper Engine
            </div>
            <p className="text-[11px] text-zinc-300 mt-1 leading-relaxed">
              Full-stream chunk-based AI scanner with checkpointing, RMS peak detection, speech velocity analysis, and 1080x1920 vertical reframing.
            </p>
          </div>
        </div>
      </div>

      {/* INTELLIGENT PEAK CLIP GENERATOR CONTROLS & MONITOR */}
      <div className="bg-zinc-950/90 border border-rose-500/30 rounded-xl p-5 shadow-inner space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold">
              <Flame className="w-3 h-3 text-rose-400" /> Intelligent Peak Clip Generator
            </div>
            <h4 className="text-sm font-bold text-zinc-100 mt-1">
              Automatic Full Video Analysis (Chunk-Based Engine)
            </h4>
            <p className="text-xs text-zinc-400">
              The AI analyzes the entire source video, identifies funny conversations, police chases, fails, and high-energy reactions, and selects the strongest moments.
            </p>
          </div>

          {/* Clip Quantity & Subtitles Options */}
          <div className="flex flex-wrap items-center gap-3">
            {/* 10, 20, 30, 40 Best Clips Options */}
            <div className="flex items-center gap-1 bg-zinc-900 p-1 rounded-xl border border-zinc-800 text-xs">
              <span className="text-[11px] text-zinc-400 px-2 font-medium">Clips:</span>
              {[
                { count: 10, label: "10 Best" },
                { count: 20, label: "20 Best" },
                { count: 30, label: "30 Best" },
                { count: 40, label: "40 Best" }
              ].map(({ count, label }) => (
                <button
                  key={count}
                  disabled={isGenerating}
                  onClick={() => setTargetCount(count)}
                  className={`px-2.5 py-1 rounded-lg text-xs font-bold transition ${
                    targetCount === count
                      ? "bg-rose-600 text-white shadow-sm"
                      : "text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60"
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>

            {/* Subtitles Toggle */}
            <button
              disabled={isGenerating}
              onClick={() => setBurnSubtitles(!burnSubtitles)}
              className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition ${
                burnSubtitles
                  ? "bg-amber-500/10 border-amber-500/30 text-amber-300"
                  : "bg-zinc-900 border-zinc-800 text-zinc-400 hover:text-zinc-200"
              }`}
            >
              <span className={`w-2 h-2 rounded-full ${burnSubtitles ? "bg-amber-400" : "bg-zinc-600"}`} />
              Animated Captions
            </button>
          </div>
        </div>

        {/* DYNAMIC PROGRESS MONITOR (LONG VIDEO PROCESSING) */}
        {isGenerating ? (
          <div className="space-y-4 bg-zinc-900/90 border border-rose-500/40 rounded-xl p-5 shadow-xl">
            {/* Step & Progress Bar */}
            <div className="space-y-2">
              <div className="flex items-center justify-between text-xs font-semibold">
                <span className="text-rose-400 flex items-center gap-2">
                  <Loader2 className="w-4 h-4 animate-spin text-rose-500" />
                  {currentStep}
                </span>
                <span className="text-zinc-100 font-mono font-extrabold text-base">
                  {progress}%
                </span>
              </div>

              <div className="w-full bg-zinc-950 rounded-full h-3.5 overflow-hidden border border-zinc-800">
                <div
                  className="bg-gradient-to-r from-rose-500 via-amber-500 to-emerald-500 h-full transition-all duration-300 rounded-full"
                  style={{ width: `${Math.max(4, progress)}%` }}
                />
              </div>
            </div>

            {/* Real Progress Metrics Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 pt-1 text-xs">
              {/* Video Analyzed */}
              <div className="bg-zinc-950/80 p-2.5 rounded-xl border border-zinc-800">
                <div className="text-[10px] text-zinc-400 uppercase font-semibold">Video Analyzed</div>
                <div className="text-zinc-100 font-bold font-mono mt-0.5">
                  {formatDuration(analyzedSecs)}
                </div>
              </div>

              {/* Video Remaining */}
              <div className="bg-zinc-950/80 p-2.5 rounded-xl border border-zinc-800">
                <div className="text-[10px] text-zinc-400 uppercase font-semibold">Video Remaining</div>
                <div className="text-zinc-100 font-bold font-mono mt-0.5">
                  {formatDuration(remainingSecs)}
                </div>
              </div>

              {/* Moments Detected */}
              <div className="bg-zinc-950/80 p-2.5 rounded-xl border border-zinc-800">
                <div className="text-[10px] text-zinc-400 uppercase font-semibold">Moments Found</div>
                <div className="text-amber-400 font-bold font-mono mt-0.5">
                  {activeJob?.moments_detected_count || 0}
                </div>
              </div>

              {/* Clips Selected */}
              <div className="bg-zinc-950/80 p-2.5 rounded-xl border border-zinc-800">
                <div className="text-[10px] text-zinc-400 uppercase font-semibold">Clips Selected</div>
                <div className="text-cyan-400 font-bold font-mono mt-0.5">
                  {activeJob?.clips_selected_count || 0} / {activeJob?.clips_total_target || targetCount}
                </div>
              </div>

              {/* Clips Rendered */}
              <div className="bg-zinc-950/80 p-2.5 rounded-xl border border-zinc-800">
                <div className="text-[10px] text-zinc-400 uppercase font-semibold">Clips Rendered</div>
                <div className="text-emerald-400 font-bold font-mono mt-0.5">
                  {activeJob?.clips_rendered_count || 0} / {activeJob?.clips_total_target || targetCount}
                </div>
              </div>

              {/* Checkpoint Status */}
              <div className="bg-zinc-950/80 p-2.5 rounded-xl border border-zinc-800">
                <div className="text-[10px] text-zinc-400 uppercase font-semibold">Checkpoint</div>
                <div className="text-rose-400 font-bold font-mono mt-0.5 text-[11px] truncate">
                  {activeJob?.checkpoint_chunk_index
                    ? `Chunk #${activeJob.checkpoint_chunk_index} Saved`
                    : "Active"}
                </div>
              </div>
            </div>

            {/* Cancel Action */}
            {activeJob?.id && onCancelJob && (
              <div className="flex justify-end pt-1">
                <button
                  onClick={() => onCancelJob(activeJob.id)}
                  className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-xs transition"
                >
                  <StopCircle className="w-3.5 h-3.5 text-rose-400" />
                  <span>Stop Processing</span>
                </button>
              </div>
            )}
          </div>
        ) : (
          <div className="flex flex-col sm:flex-row items-center justify-between gap-3 pt-1">
            <button
              onClick={handleStart}
              className="w-full sm:w-auto px-6 py-3 rounded-xl bg-gradient-to-r from-rose-600 via-red-600 to-amber-600 hover:from-rose-500 hover:via-red-500 hover:to-amber-500 text-white font-extrabold text-sm shadow-lg shadow-rose-950/50 flex items-center justify-center gap-2.5 transition active:scale-[0.98]"
            >
              <Zap className="w-4 h-4 fill-current text-amber-300" />
              <span>
                {clipsCount > 0
                  ? `Regenerate ${targetCount} Best Clips (PeakCut AI)`
                  : `Generate ${targetCount} Best Clips (PeakCut AI)`}
              </span>
            </button>

            {clipsCount > 0 && (
              <div className="flex items-center gap-1.5 text-xs text-emerald-400 font-semibold bg-emerald-500/10 px-3 py-1.5 rounded-xl border border-emerald-500/20">
                <Check className="w-3.5 h-3.5" />
                <span>{clipsCount} viral vertical clips generated & available below</span>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
};
