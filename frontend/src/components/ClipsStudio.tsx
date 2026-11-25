"use client";

import React, { useState } from "react";
import { Download, Sparkles, Film, Copy, Check, Sliders, Play, RefreshCw, Layers, Archive, Clock, Eye, AlertCircle } from "lucide-react";

export interface ClipData {
  id: string;
  job_id: string;
  video_id: string;
  clip_index: number;
  start_time: number;
  end_time: number;
  duration: number;
  title: string;
  description?: string;
  hashtags?: string[];
  virality_score: number;
  aspect_ratio: string;
  file_size_bytes?: number;
  download_url: string;
  created_at: string;
}

interface ClipsStudioProps {
  clips: ClipData[];
  jobId?: string;
  apiBaseUrl: string;
  storageBaseUrl: string;
  onClipUpdated?: () => void;
}

export const ClipsStudio: React.FC<ClipsStudioProps> = ({
  clips,
  jobId,
  apiBaseUrl,
  storageBaseUrl,
  onClipUpdated
}) => {
  const [copiedId, setCopiedId] = useState<string | null>(null);
  const [sortBy, setSortBy] = useState<"virality" | "time" | "duration">("virality");
  const [editingClip, setEditingClip] = useState<ClipData | null>(null);
  const [newStartTime, setNewStartTime] = useState<number>(0);
  const [newEndTime, setNewEndTime] = useState<number>(0);
  const [isRetrimming, setIsRetrimming] = useState(false);
  const [retrimError, setRetrimError] = useState<string | null>(null);

  if (!clips || clips.length === 0) {
    return null;
  }

  const handleCopy = (id: string, text: string) => {
    navigator.clipboard.writeText(text);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  // Sort logic
  const sortedClips = [...clips].sort((a, b) => {
    if (sortBy === "virality") return b.virality_score - a.virality_score;
    if (sortBy === "time") return a.start_time - b.start_time;
    if (sortBy === "duration") return b.duration - a.duration;
    return 0;
  });

  const formatTime = (seconds: number) => {
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    if (hrs > 0) {
      return `${hrs}:${mins < 10 ? "0" : ""}${mins}:${secs < 10 ? "0" : ""}${secs}`;
    }
    return `${mins}:${secs < 10 ? "0" : ""}${secs}`;
  };

  const handleOpenRetrim = (clip: ClipData) => {
    setEditingClip(clip);
    setNewStartTime(clip.start_time);
    setNewEndTime(clip.end_time);
    setRetrimError(null);
  };

  const handleRetrimSubmit = async () => {
    if (!editingClip) return;
    if (newEndTime <= newStartTime) {
      setRetrimError("End time must be greater than start time.");
      return;
    }

    setIsRetrimming(true);
    setRetrimError(null);

    try {
      const res = await fetch(`${apiBaseUrl}/clips/${editingClip.id}/retrim`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          start_time: Number(newStartTime),
          end_time: Number(newEndTime)
        }),
      });

      if (!res.ok) {
        const data = await res.json();
        throw new Error(data.detail || "Failed to re-trim clip.");
      }

      setEditingClip(null);
      if (onClipUpdated) onClipUpdated();
    } catch (err: any) {
      setRetrimError(err.message || "Failed to re-trim clip.");
    } finally {
      setIsRetrimming(false);
    }
  };

  const effectiveJobId = jobId || clips[0]?.job_id;
  const zipDownloadUrl = `${apiBaseUrl}/clips/download-zip/${effectiveJobId}`;

  return (
    <div className="w-full bg-zinc-900/90 border border-zinc-800 rounded-2xl p-6 shadow-2xl space-y-6">
      {/* Studio Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-800 pb-5">
        <div>
          <div className="flex items-center gap-2">
            <span className="text-xs font-bold px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 flex items-center gap-1.5 shadow-sm shadow-emerald-950">
              <Sparkles className="w-3.5 h-3.5" /> Total Clips Generated: {clips.length}
            </span>
            <span className="text-xs text-zinc-400">1080x1920 Vertical Shorts</span>
          </div>
          <h3 className="text-xl font-extrabold text-zinc-100 mt-1.5">
            Viral Gaming Shorts Studio
          </h3>
          <p className="text-xs text-zinc-400">
            Independent peak-style story moments with burned subtitles and optimized reframing.
          </p>
        </div>

        {/* Actions: Download All ZIP + Sort */}
        <div className="flex flex-wrap items-center gap-3">
          {/* Sort Selector */}
          <div className="flex items-center gap-1.5 text-xs bg-zinc-950 px-3 py-1.5 rounded-xl border border-zinc-800 text-zinc-300">
            <Sliders className="w-3.5 h-3.5 text-rose-400" />
            <span>Sort:</span>
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as any)}
              className="bg-transparent text-zinc-100 font-semibold focus:outline-none cursor-pointer"
            >
              <option value="virality" className="bg-zinc-900">Highest Engagement</option>
              <option value="time" className="bg-zinc-900">Timestamp Order</option>
              <option value="duration" className="bg-zinc-900">Longest Duration</option>
            </select>
          </div>

          {/* Download All as ZIP */}
          <a
            href={zipDownloadUrl}
            download
            className="flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold text-xs shadow-lg shadow-emerald-950/40 transition"
          >
            <Archive className="w-4 h-4" />
            <span>Download All ({clips.length} Clips ZIP)</span>
          </a>
        </div>
      </div>

      {/* 10 Clips Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {sortedClips.map((clip, index) => {
          const videoSrc = `${storageBaseUrl.replace('/storage', '')}${clip.download_url}`;
          const fileSizeMb = clip.file_size_bytes
            ? (clip.file_size_bytes / (1024 * 1024)).toFixed(2) + " MB"
            : "N/A";

          return (
            <div
              key={clip.id}
              className="bg-zinc-950/80 border border-zinc-800 hover:border-zinc-700 rounded-2xl overflow-hidden shadow-xl flex flex-col justify-between transition-all group"
            >
              {/* Card Top: Rank Badge & Virality Score */}
              <div className="p-3.5 border-b border-zinc-800/80 flex items-center justify-between">
                <span className="text-[11px] font-bold px-2 py-0.5 rounded-md bg-zinc-800 text-zinc-300">
                  #{index + 1}
                </span>

                <div className="flex items-center gap-2">
                  <span className="text-xs font-extrabold px-2.5 py-0.5 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center gap-1">
                    🔥 {clip.virality_score}% Virality
                  </span>
                </div>
              </div>

              {/* 9:16 Vertical Video Player */}
              <div className="relative bg-black flex items-center justify-center overflow-hidden h-[340px] border-b border-zinc-800/80">
                <video
                  src={videoSrc}
                  controls
                  preload="metadata"
                  className="w-full h-full object-contain"
                >
                  Your browser does not support the video tag.
                </video>
              </div>

              {/* Metadata & Copy Tools */}
              <div className="p-4 space-y-3 flex-1 flex flex-col justify-between">
                <div className="space-y-2">
                  <h4 className="text-sm font-bold text-zinc-100 line-clamp-1">
                    {clip.title}
                  </h4>

                  <div className="flex items-center gap-3 text-[11px] text-zinc-400 font-medium">
                    <span className="flex items-center gap-1 text-zinc-300">
                      <Clock className="w-3 h-3 text-amber-400" />
                      {formatTime(clip.start_time)} - {formatTime(clip.end_time)} ({clip.duration}s)
                    </span>
                    <span>&bull;</span>
                    <span>{fileSizeMb}</span>
                  </div>

                  {/* Caption & Hashtags Snippet */}
                  {clip.description && (
                    <div className="bg-zinc-900/70 p-2.5 rounded-xl border border-zinc-800/80 text-[11px] text-zinc-300 relative group/caption">
                      <p className="line-clamp-2 leading-relaxed">
                        {clip.description}
                      </p>
                      <button
                        onClick={() => handleCopy(`desc-${clip.id}`, clip.description || "")}
                        className="mt-1.5 text-[10px] text-rose-400 hover:text-rose-300 flex items-center gap-1 font-semibold"
                      >
                        {copiedId === `desc-${clip.id}` ? (
                          <>
                            <Check className="w-3 h-3 text-emerald-400" /> Copied Caption!
                          </>
                        ) : (
                          <>
                            <Copy className="w-3 h-3" /> Copy Caption & Hashtags
                          </>
                        )}
                      </button>
                    </div>
                  )}
                </div>

                {/* Bottom Action Buttons */}
                <div className="pt-2 flex items-center gap-2 border-t border-zinc-800/60">
                  <a
                    href={videoSrc}
                    download={clip.title ? `${clip.title}.mp4` : `clip_${clip.clip_index}.mp4`}
                    className="flex-1 py-2 px-3 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-zinc-100 font-semibold text-xs flex items-center justify-center gap-1.5 transition border border-zinc-700"
                  >
                    <Download className="w-3.5 h-3.5 text-rose-400" />
                    <span>Download MP4</span>
                  </a>

                  <button
                    onClick={() => handleOpenRetrim(clip)}
                    className="p-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-400 hover:text-zinc-200 transition"
                    title="Edit Timestamps / Re-trim"
                  >
                    <Sliders className="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* Retrim Modal */}
      {editingClip && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
          <div className="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 max-w-md w-full space-y-4 shadow-2xl animate-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-zinc-800 pb-3">
              <h4 className="text-sm font-bold text-zinc-100 flex items-center gap-2">
                <Sliders className="w-4 h-4 text-rose-500" />
                Edit Clip Timestamps
              </h4>
              <button
                onClick={() => setEditingClip(null)}
                className="text-zinc-400 hover:text-zinc-200 text-xs"
              >
                ✕
              </button>
            </div>

            <p className="text-xs text-zinc-400">
              Adjust start and end cut points for <b className="text-zinc-200">{editingClip.title}</b>.
            </p>

            {retrimError && (
              <div className="p-3 rounded-xl bg-rose-950/40 border border-rose-800 text-rose-300 text-xs">
                {retrimError}
              </div>
            )}

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-semibold text-zinc-400 mb-1">
                  Start Time (seconds)
                </label>
                <input
                  type="number"
                  step="0.5"
                  value={newStartTime}
                  onChange={(e) => setNewStartTime(Number(e.target.value))}
                  className="w-full px-3 py-2 bg-zinc-950 border border-zinc-700 rounded-xl text-sm text-zinc-100 focus:outline-none focus:border-rose-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-zinc-400 mb-1">
                  End Time (seconds)
                </label>
                <input
                  type="number"
                  step="0.5"
                  value={newEndTime}
                  onChange={(e) => setNewEndTime(Number(e.target.value))}
                  className="w-full px-3 py-2 bg-zinc-950 border border-zinc-700 rounded-xl text-sm text-zinc-100 focus:outline-none focus:border-rose-500"
                />
              </div>
            </div>

            <div className="text-xs text-zinc-400">
              New Duration: <b className="text-zinc-200">{(newEndTime - newStartTime).toFixed(1)}s</b>
            </div>

            <div className="pt-2 flex items-center gap-3">
              <button
                onClick={handleRetrimSubmit}
                disabled={isRetrimming}
                className="flex-1 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs transition disabled:opacity-50"
              >
                {isRetrimming ? "Re-rendering Clip (NVENC)..." : "Save & Re-render Clip"}
              </button>
              <button
                onClick={() => setEditingClip(null)}
                disabled={isRetrimming}
                className="px-4 py-2.5 rounded-xl bg-zinc-800 text-zinc-300 text-xs hover:bg-zinc-700"
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
