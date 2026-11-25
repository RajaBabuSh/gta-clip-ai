"use client";

import React, { useState, useEffect, useRef, Suspense } from "react";
import { useSearchParams } from "next/navigation";
import { DashboardLayout } from "@/components/DashboardLayout";
import {
  Play,
  Pause,
  Sparkles,
  Scissors,
  Download,
  Film,
  Video,
  Flame,
  Layers,
  Clock,
  Sliders,
  Check,
  RefreshCw,
  Share2,
  Upload,
  Volume2,
  AlertCircle
} from "lucide-react";

function StudioContent() {
  const searchParams = useSearchParams();
  const initialVideoId = searchParams.get("video_id");

  const [videos, setVideos] = useState<any[]>([]);
  const [selectedVideo, setSelectedVideo] = useState<any | null>(null);
  const [clips, setClips] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  // Studio Player state
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState(12.4);
  const [trimStart, setTrimStart] = useState(0);
  const [trimEnd, setTrimEnd] = useState(45);
  const [format, setFormat] = useState<"9:16" | "16:9" | "1:1" | "4:5">("9:16");
  const [subtitleStyle, setSubtitleStyle] = useState<"mrbeast" | "neon" | "clean">("mrbeast");

  // Job generation state
  const [targetClips, setTargetClips] = useState<number>(5);
  const [isGenerating, setIsGenerating] = useState(false);
  const [jobProgress, setJobProgress] = useState<number>(0);
  const [statusText, setStatusText] = useState<string>("");
  const [activeJobId, setActiveJobId] = useState<string | null>(null);

  // Fetch videos on mount
  useEffect(() => {
    const fetchVideos = async () => {
      try {
        setLoading(true);
        const res = await fetch("/api/videos?limit=20");
        if (res.ok) {
          const data = await res.json();
          setVideos(data);
          if (data.length > 0) {
            const found = initialVideoId ? data.find((v: any) => v.id === initialVideoId) : data[0];
            setSelectedVideo(found || data[0]);
          }
        }
      } catch (e) {
        console.error("Failed to load videos:", e);
      } finally {
        setLoading(false);
      }
    };
    fetchVideos();
  }, [initialVideoId]);

  // Fetch clips when selectedVideo changes
  useEffect(() => {
    if (!selectedVideo) return;
    const fetchClips = async () => {
      try {
        const res = await fetch(`/api/clips?video_id=${selectedVideo.id}`);
        if (res.ok) {
          const data = await res.json();
          setClips(data);
        }
      } catch (e) {
        console.error("Failed to load clips for video:", e);
      }
    };
    fetchClips();
  }, [selectedVideo]);

  const handleStartGeneration = async () => {
    if (!selectedVideo) return;
    setIsGenerating(true);
    setJobProgress(10);
    setStatusText("Extracting 16kHz audio track...");

    try {
      const res = await fetch("/api/jobs", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ video_id: selectedVideo.id, target_clips: targetClips }),
      });

      if (res.ok) {
        const data = await res.json();
        setActiveJobId(data.job?.id || null);

        // Simulation progression for UX feedback
        setTimeout(() => {
          setJobProgress(35);
          setStatusText("Whisper model transcribing dialogue & word timestamps...");
        }, 1200);

        setTimeout(() => {
          setJobProgress(65);
          setStatusText("Analyzing acoustic loudness peaks & emotional energy...");
        }, 2500);

        setTimeout(() => {
          setJobProgress(90);
          setStatusText("NVENC rendering 9:16 vertical cuts & karaoke subtitles...");
        }, 4000);

        setTimeout(async () => {
          setJobProgress(100);
          setStatusText("Clipping complete! Generated viral shorts ready.");
          setIsGenerating(false);

          // Refresh clips list
          const cRes = await fetch(`/api/clips?video_id=${selectedVideo.id}`);
          if (cRes.ok) {
            setClips(await cRes.json());
          }
        }, 5500);
      } else {
        setIsGenerating(false);
        alert("Failed to initiate clipping job.");
      }
    } catch {
      setIsGenerating(false);
      alert("Error contacting clipping worker.");
    }
  };

  const formatSeconds = (s: number) => {
    const mins = Math.floor(s / 60);
    const secs = Math.floor(s % 60);
    return `${mins}:${secs < 10 ? "0" : ""}${secs}`;
  };

  return (
    <DashboardLayout pageTitle="AI Clipping Studio">
      {/* 1. TOP VIDEO SELECTOR & STATS BAR */}
      <div className="p-4 sm:p-6 rounded-2xl bg-[#0f0c1e]/80 border border-white/10 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="p-2.5 rounded-xl bg-purple-600/20 text-purple-400">
            <Video className="w-5 h-5" />
          </div>
          <div>
            <label className="block text-[10px] font-bold uppercase tracking-wider text-zinc-400 mb-0.5">
              Active Source Video
            </label>
            <select
              value={selectedVideo?.id || ""}
              onChange={(e) => {
                const vid = videos.find((v) => v.id === e.target.value);
                if (vid) setSelectedVideo(vid);
              }}
              className="bg-black/60 border border-white/10 rounded-xl px-3 py-1.5 text-xs font-bold text-white focus:outline-none focus:ring-2 focus:ring-purple-500 cursor-pointer min-w-[240px]"
            >
              {videos.map((v) => (
                <option key={v.id} value={v.id} className="bg-zinc-900">
                  {v.title || "Footage"} ({formatSeconds(v.duration_seconds || 180)})
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Format Selector */}
        <div className="flex items-center gap-2">
          <span className="text-xs font-semibold text-zinc-400">Format:</span>
          <div className="flex bg-black/40 p-1 rounded-xl border border-white/10 text-xs">
            {(["9:16", "16:9", "1:1", "4:5"] as const).map((fmt) => (
              <button
                key={fmt}
                onClick={() => setFormat(fmt)}
                className={`px-3 py-1 rounded-lg font-bold transition-all ${
                  format === fmt
                    ? "bg-purple-600 text-white shadow-sm"
                    : "text-zinc-400 hover:text-white"
                }`}
              >
                {fmt}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* 2. DUAL COLUMN STUDIO WORKSPACE */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        {/* LEFT: Video Player & Timeline Editor */}
        <div className="lg:col-span-7 space-y-6">
          <div className="bg-[#0f0c1e] border border-white/10 rounded-3xl p-6 shadow-2xl">
            {/* Aspect Ratio Preview Stage */}
            <div className="flex justify-center mb-6">
              <div
                className={`relative bg-black rounded-2xl overflow-hidden border border-purple-500/40 shadow-2xl transition-all duration-300 flex flex-col justify-between ${
                  format === "9:16"
                    ? "w-[260px] h-[460px]"
                    : format === "16:9"
                    ? "w-[480px] h-[270px]"
                    : format === "4:5"
                    ? "w-[320px] h-[400px]"
                    : "w-[340px] h-[340px]"
                }`}
              >
                <div className="absolute inset-0 bg-gradient-to-b from-purple-950/30 via-zinc-950 to-black" />
                <div className="absolute inset-0 flex items-center justify-center opacity-25">
                  <Film className="w-20 h-20 text-purple-400 animate-pulse" />
                </div>

                {/* Top Overlay Badge */}
                <div className="relative z-10 p-3 flex justify-between items-center text-xs">
                  <span className="px-2 py-0.5 rounded bg-black/70 text-amber-400 font-bold border border-white/10 flex items-center gap-1">
                    <Flame className="w-3 h-3 fill-amber-400" /> 97 Virality
                  </span>
                  <span className="px-2 py-0.5 rounded bg-purple-600/80 text-white font-bold uppercase text-[10px]">
                    {format}
                  </span>
                </div>

                {/* Subtitle Karaoke Simulation */}
                <div className="relative z-10 p-4 text-center">
                  {subtitleStyle === "mrbeast" && (
                    <div className="inline-block bg-black/80 px-3 py-1.5 rounded-lg border border-yellow-400/50">
                      <span className="text-lg font-black uppercase text-yellow-300 drop-shadow-[0_2px_10px_rgba(234,179,8,0.8)]">
                        &quot;LOOK AT THIS DRIVE-BY! 🔥&quot;
                      </span>
                    </div>
                  )}
                  {subtitleStyle === "neon" && (
                    <div className="inline-block bg-black/80 px-3 py-1.5 rounded-lg border border-cyan-400/50">
                      <span className="text-lg font-black uppercase text-cyan-400 drop-shadow-[0_0_12px_rgba(34,211,238,0.8)]">
                        ⚡ INSANE 1v4 CLUTCH ⚡
                      </span>
                    </div>
                  )}
                  {subtitleStyle === "clean" && (
                    <div className="inline-block bg-white/95 px-3 py-1 rounded text-black font-extrabold text-xs">
                      High speed getaway in Los Santos
                    </div>
                  )}
                </div>

                {/* Bottom Scrub Controls */}
                <div className="relative z-10 p-3 bg-gradient-to-t from-black via-black/80 to-transparent">
                  <div className="flex items-center justify-between text-[11px] text-zinc-300 mb-2">
                    <button
                      onClick={() => setIsPlaying(!isPlaying)}
                      className="p-1 rounded-lg bg-purple-600 text-white hover:bg-purple-500"
                    >
                      {isPlaying ? <Pause className="w-3.5 h-3.5" /> : <Play className="w-3.5 h-3.5 fill-white" />}
                    </button>
                    <span className="font-mono text-zinc-400">
                      {formatSeconds(currentTime)} / {formatSeconds(selectedVideo?.duration_seconds || 180)}
                    </span>
                  </div>
                  <div className="w-full bg-white/20 h-1.5 rounded-full overflow-hidden">
                    <div className="bg-purple-500 h-full w-[40%]" />
                  </div>
                </div>
              </div>
            </div>

            {/* Timeline Trimming Controls */}
            <div className="space-y-4 pt-4 border-t border-white/10">
              <div className="flex items-center justify-between">
                <span className="text-xs font-bold text-white flex items-center gap-1.5">
                  <Scissors className="w-3.5 h-3.5 text-purple-400" /> Clip Timeline Editor
                </span>
                <span className="text-xs text-purple-400 font-semibold font-mono">
                  Clip Duration: {trimEnd - trimStart}s
                </span>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-[10px] font-bold uppercase tracking-wider text-zinc-400 mb-1">
                    Start Offset (Seconds)
                  </label>
                  <input
                    type="number"
                    min={0}
                    max={trimEnd - 5}
                    value={trimStart}
                    onChange={(e) => setTrimStart(Number(e.target.value))}
                    className="w-full px-3 py-2 bg-black/40 border border-white/10 rounded-xl text-white text-xs font-mono"
                  />
                </div>
                <div>
                  <label className="block text-[10px] font-bold uppercase tracking-wider text-zinc-400 mb-1">
                    End Offset (Seconds)
                  </label>
                  <input
                    type="number"
                    min={trimStart + 5}
                    value={trimEnd}
                    onChange={(e) => setTrimEnd(Number(e.target.value))}
                    className="w-full px-3 py-2 bg-black/40 border border-white/10 rounded-xl text-white text-xs font-mono"
                  />
                </div>
              </div>

              {/* Subtitle Preset Bar */}
              <div>
                <label className="block text-[10px] font-bold uppercase tracking-wider text-zinc-400 mb-1.5">
                  Subtitle Aesthetic
                </label>
                <div className="grid grid-cols-3 gap-2">
                  {[
                    { id: "mrbeast", label: "🔥 MrBeast" },
                    { id: "neon", label: "⚡ Cyber Neon" },
                    { id: "clean", label: "✨ Minimalist" }
                  ].map((sub) => (
                    <button
                      key={sub.id}
                      onClick={() => setSubtitleStyle(sub.id as any)}
                      className={`py-2 px-3 rounded-xl text-xs font-bold transition-all ${
                        subtitleStyle === sub.id
                          ? "bg-purple-600 text-white shadow-md shadow-purple-600/30 border border-purple-400/40"
                          : "bg-white/5 text-zinc-400 hover:text-white border border-white/5"
                      }`}
                    >
                      {sub.label}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* RIGHT: AI Generation Panel & Generated Clips List */}
        <div className="lg:col-span-5 space-y-6">
          {/* Dispatch Box */}
          <div className="bg-[#0f0c1e] border border-white/10 rounded-3xl p-6 shadow-xl">
            <h3 className="text-base font-bold text-white flex items-center gap-2 mb-2">
              <Sparkles className="w-4 h-4 text-purple-400" /> AI Highlights Miner
            </h3>
            <p className="text-xs text-zinc-400 mb-4">
              Scans audio energy peaks, comedic spikes, and dialogue velocity to slice vertical shorts automatically.
            </p>

            <div className="mb-4">
              <label className="block text-xs font-bold text-zinc-300 uppercase tracking-wider mb-2">
                Target Clips To Mine
              </label>
              <div className="grid grid-cols-3 gap-2">
                {[5, 10, 20].map((num) => (
                  <button
                    key={num}
                    onClick={() => setTargetClips(num)}
                    className={`py-2 px-3 rounded-xl text-xs font-bold transition-all ${
                      targetClips === num
                        ? "bg-purple-600 text-white shadow-md"
                        : "bg-white/5 text-zinc-400 hover:text-white"
                    }`}
                  >
                    {num} Clips
                  </button>
                ))}
              </div>
            </div>

            {isGenerating ? (
              <div className="p-4 rounded-2xl bg-purple-950/40 border border-purple-500/30">
                <div className="flex items-center justify-between text-xs font-bold text-purple-300 mb-2">
                  <span>Processing Stream...</span>
                  <span>{jobProgress}%</span>
                </div>
                <div className="w-full bg-black/60 h-2 rounded-full overflow-hidden mb-2">
                  <div
                    className="bg-gradient-to-r from-purple-500 to-indigo-500 h-full transition-all duration-300"
                    style={{ width: `${jobProgress}%` }}
                  />
                </div>
                <p className="text-[11px] text-zinc-400 flex items-center gap-1.5">
                  <RefreshCw className="w-3 h-3 animate-spin text-purple-400" /> {statusText}
                </p>
              </div>
            ) : (
              <button
                onClick={handleStartGeneration}
                className="w-full flex items-center justify-center gap-2 py-3.5 px-4 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 shadow-xl shadow-purple-600/30 transition-all cursor-pointer"
              >
                <Sparkles className="w-4 h-4" /> Mine {targetClips} Viral Shorts Now
              </button>
            )}
          </div>

          {/* Generated Clips Reel */}
          <div className="bg-[#0f0c1e] border border-white/10 rounded-3xl p-6 shadow-xl">
            <h3 className="text-base font-bold text-white flex items-center justify-between mb-4">
              <span>Mined Clips ({clips.length})</span>
              <span className="text-xs text-purple-400 font-semibold">1080x1920 MP4</span>
            </h3>

            {clips.length === 0 ? (
              <div className="py-8 text-center text-zinc-500 text-xs">
                No clips mined for this video yet. Click above to begin!
              </div>
            ) : (
              <div className="space-y-3 max-h-[460px] overflow-y-auto pr-1">
                {clips.map((clip) => (
                  <div
                    key={clip.id}
                    className="p-3.5 rounded-2xl bg-white/5 border border-white/10 hover:border-purple-500/30 flex items-center justify-between gap-3 transition-colors"
                  >
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2 mb-1">
                        <span className="px-2 py-0.5 rounded text-[10px] font-extrabold bg-amber-500/20 text-amber-300 flex items-center gap-1">
                          <Flame className="w-3 h-3 fill-amber-300" /> {clip.virality_score || 95}
                        </span>
                        <span className="text-[10px] text-zinc-400 font-mono">
                          {formatSeconds(clip.duration || 45)}
                        </span>
                      </div>
                      <h5 className="text-xs font-bold text-white truncate">{clip.title}</h5>
                    </div>

                    <a
                      href={clip.download_url || "#"}
                      download
                      className="p-2 rounded-xl bg-purple-600 text-white hover:bg-purple-500 flex-shrink-0"
                    >
                      <Download className="w-4 h-4" />
                    </a>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}

export default function StudioPage() {
  return (
    <Suspense fallback={
      <div className="min-h-screen bg-[#07050f] flex items-center justify-center text-purple-400">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500" />
      </div>
    }>
      <StudioContent />
    </Suspense>
  );
}
