"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { DashboardLayout } from "@/components/DashboardLayout";
import { Film, Download, Play, Flame, Filter, SlidersHorizontal, ArrowUpDown, X, Share2, Sparkles } from "lucide-react";

export default function MyClipsPage() {
  const [clips, setClips] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [aspectFilter, setAspectFilter] = useState<string>("all");
  const [sortBy, setSortBy] = useState<"virality" | "date" | "duration">("virality");
  const [previewClip, setPreviewClip] = useState<any | null>(null);

  const fetchClips = async () => {
    try {
      setLoading(true);
      const res = await fetch(`/api/clips?aspect_ratio=${aspectFilter}`);
      if (res.ok) {
        const data = await res.json();
        setClips(data);
      }
    } catch (e) {
      console.error("Failed to load clips:", e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchClips();
  }, [aspectFilter]);

  const sortedClips = [...clips].sort((a, b) => {
    if (sortBy === "virality") return (b.virality_score || 0) - (a.virality_score || 0);
    if (sortBy === "duration") return (b.duration || 0) - (a.duration || 0);
    return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
  });

  const formatDuration = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs < 10 ? "0" : ""}${secs}`;
  };

  return (
    <DashboardLayout pageTitle="My Viral Clips Library">
      {/* Header & Filter Controls */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
          <h2 className="text-xl sm:text-2xl font-extrabold text-white">Generated Video Clips</h2>
          <p className="text-xs text-zinc-400 mt-1">
            Browse, preview, and download your 9:16 vertical shorts and gaming highlights.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          {/* Aspect Ratio Filter */}
          <div className="flex bg-black/40 p-1 rounded-xl border border-white/10 text-xs">
            {["all", "9:16", "16:9", "1:1"].map((ratio) => (
              <button
                key={ratio}
                onClick={() => setAspectFilter(ratio)}
                className={`px-3 py-1.5 rounded-lg font-bold transition-all ${
                  aspectFilter === ratio
                    ? "bg-purple-600 text-white shadow-sm"
                    : "text-zinc-400 hover:text-white"
                }`}
              >
                {ratio === "all" ? "All Formats" : ratio}
              </button>
            ))}
          </div>

          {/* Sort Selector */}
          <div className="flex items-center gap-2 bg-white/5 px-3 py-1.5 rounded-xl border border-white/10 text-xs text-zinc-300">
            <ArrowUpDown className="w-3.5 h-3.5 text-zinc-500" />
            <select
              value={sortBy}
              onChange={(e) => setSortBy(e.target.value as any)}
              className="bg-transparent text-white focus:outline-none cursor-pointer"
            >
              <option value="virality" className="bg-zinc-900">Highest Virality</option>
              <option value="date" className="bg-zinc-900">Most Recent</option>
              <option value="duration" className="bg-zinc-900">Duration</option>
            </select>
          </div>
        </div>
      </div>

      {/* Clips Grid */}
      {loading ? (
        <div className="py-20 text-center text-zinc-500">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mx-auto mb-3" />
          <p className="text-xs">Loading viral clips...</p>
        </div>
      ) : sortedClips.length === 0 ? (
        <div className="p-16 text-center rounded-3xl bg-[#0f0c1e]/60 border border-white/10">
          <Film className="w-12 h-12 mx-auto text-zinc-600 mb-4" />
          <h3 className="text-base font-bold text-white mb-1">No Clips Found</h3>
          <p className="text-xs text-zinc-400 max-w-md mx-auto mb-6">
            There are no clips matching the current format filter. Generate clips from your stream in the studio!
          </p>
          <Link
            href="/studio"
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-purple-600 hover:bg-purple-500"
          >
            <Sparkles className="w-3.5 h-3.5" /> Launch AI Studio
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          {sortedClips.map((clip) => (
            <div
              key={clip.id}
              className="group rounded-2xl bg-[#0f0c1e]/80 border border-white/10 hover:border-purple-500/40 overflow-hidden shadow-lg transition-all duration-300 flex flex-col justify-between"
            >
              {/* Media Card */}
              <div
                onClick={() => setPreviewClip(clip)}
                className="relative aspect-[9/16] bg-black max-h-[380px] overflow-hidden flex items-center justify-center cursor-pointer"
              >
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent z-10" />

                <div className="w-12 h-12 rounded-full bg-purple-600/90 text-white flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform z-20">
                  <Play className="w-5 h-5 fill-white ml-0.5" />
                </div>

                <div className="absolute top-3 left-3 z-20 flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-black/70 border border-white/15 text-[11px] font-extrabold text-amber-400 backdrop-blur-md">
                  <Flame className="w-3.5 h-3.5 fill-amber-400" />
                  <span>{clip.virality_score || 95} Virality</span>
                </div>

                <div className="absolute top-3 right-3 z-20 px-2 py-0.5 rounded text-[10px] font-bold bg-purple-600/80 text-white uppercase backdrop-blur-md">
                  {clip.aspect_ratio || "9:16"}
                </div>

                <div className="absolute bottom-3 right-3 z-20 px-2 py-0.5 rounded text-[10px] font-bold bg-black/80 text-zinc-300 backdrop-blur-md">
                  {formatDuration(clip.duration || 45)}
                </div>
              </div>

              {/* Details & Actions */}
              <div className="p-4 flex-1 flex flex-col justify-between">
                <div>
                  <h4 className="text-sm font-bold text-white line-clamp-2 mb-1">
                    {clip.title || "Viral Gaming Highlight Moment"}
                  </h4>
                  <p className="text-[11px] text-zinc-400 line-clamp-2 leading-relaxed">
                    {clip.description || "Auto-detected peak clutch moment with Whisper karaoke animated subtitles."}
                  </p>
                </div>

                <div className="mt-4 pt-3 border-t border-white/10 flex items-center justify-between">
                  <button
                    onClick={() => setPreviewClip(clip)}
                    className="text-xs text-purple-400 hover:text-purple-300 font-bold"
                  >
                    Preview
                  </button>
                  <a
                    href={clip.download_url || "#"}
                    download
                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-white bg-purple-600 hover:bg-purple-500 shadow-sm transition-colors"
                  >
                    <Download className="w-3.5 h-3.5" /> Export MP4
                  </a>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Modal Preview Player */}
      {previewClip && (
        <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
          <div className="bg-[#0f0c1e] border border-white/20 rounded-3xl max-w-xl w-full p-6 relative shadow-2xl">
            <button
              onClick={() => setPreviewClip(null)}
              className="absolute top-4 right-4 p-2 rounded-full bg-white/10 hover:bg-white/20 text-zinc-400 hover:text-white"
            >
              <X className="w-5 h-5" />
            </button>

            <div className="flex items-center gap-2 mb-4">
              <span className="px-2.5 py-1 rounded-full bg-purple-500/20 text-purple-300 text-xs font-bold">
                {previewClip.aspect_ratio || "9:16"} Format
              </span>
              <span className="text-amber-400 text-xs font-extrabold flex items-center gap-1">
                <Flame className="w-4 h-4 fill-amber-400" /> Virality Score: {previewClip.virality_score}/100
              </span>
            </div>

            <div className="relative aspect-[9/16] bg-black max-h-[420px] rounded-2xl overflow-hidden mx-auto mb-4 border border-purple-500/30 flex items-center justify-center">
              <Film className="w-16 h-16 text-purple-500 animate-pulse" />
              <div className="absolute bottom-4 inset-x-4 p-3 bg-black/80 rounded-xl text-center border border-yellow-400/30">
                <span className="text-yellow-300 font-black text-sm uppercase drop-shadow">
                  &quot;NO WAY HE HIT THAT SHOT!&quot;
                </span>
              </div>
            </div>

            <h3 className="text-base font-bold text-white mb-2">{previewClip.title}</h3>
            <p className="text-xs text-zinc-400 mb-6">{previewClip.description}</p>

            <div className="flex gap-3">
              <a
                href={previewClip.download_url || "#"}
                download
                className="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl font-bold text-xs text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500"
              >
                <Download className="w-4 h-4" /> Download 1080p MP4
              </a>
              <button
                onClick={() => {
                  navigator.clipboard.writeText(previewClip.title + " #gaming #shorts");
                  alert("Hashtags & title copied to clipboard!");
                }}
                className="px-4 py-3 rounded-xl font-bold text-xs text-zinc-300 bg-white/5 hover:bg-white/10 border border-white/10"
              >
                <Share2 className="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      )}
    </DashboardLayout>
  );
}
