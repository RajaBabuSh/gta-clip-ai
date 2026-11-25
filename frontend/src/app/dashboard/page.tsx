"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/AuthContext";
import { DashboardLayout } from "@/components/DashboardLayout";
import {
  Film,
  Video,
  Sparkles,
  Download,
  Play,
  Upload,
  Clock,
  Flame,
  TrendingUp,
  HardDrive,
  Cpu,
  Layers,
  ArrowRight,
  Plus,
  RefreshCw,
  AlertCircle
} from "lucide-react";

export default function DashboardPage() {
  const { user, loading: authLoading } = useAuth();
  const router = useRouter();

  const [videos, setVideos] = useState<any[]>([]);
  const [clips, setClips] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<"clips" | "videos">("clips");

  useEffect(() => {
    if (!authLoading && !user) {
      router.push("/login");
    }
  }, [user, authLoading, router]);

  const loadData = async () => {
    try {
      setLoading(true);
      const [videosRes, clipsRes] = await Promise.all([
        fetch("/api/videos?limit=10"),
        fetch("/api/clips?limit=12"),
      ]);

      if (videosRes.ok) {
        const vData = await videosRes.json();
        setVideos(vData);
      }
      if (clipsRes.ok) {
        const cData = await clipsRes.json();
        setClips(cData);
      }
    } catch (e) {
      console.error("Failed to load dashboard data:", e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user) {
      loadData();
    }
  }, [user]);

  if (authLoading || !user) {
    return (
      <div className="min-h-screen bg-[#07050f] flex items-center justify-center text-purple-400">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500" />
      </div>
    );
  }

  const formatDuration = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs < 10 ? "0" : ""}${secs}`;
  };

  return (
    <DashboardLayout pageTitle="Creator Overview & Dashboard">
      {/* 1. WELCOME HERO & ACTION BAR */}
      <div className="mb-8 p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-purple-950/60 via-[#0f0c1e] to-indigo-950/40 border border-purple-500/20 shadow-xl shadow-purple-950/40 relative overflow-hidden">
        <div className="absolute right-0 top-0 bottom-0 w-96 bg-purple-600/10 blur-[100px] pointer-events-none" />

        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-6 relative z-10">
          <div>
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/15 border border-purple-500/30 text-purple-300 text-xs font-semibold mb-3">
              <Sparkles className="w-3.5 h-3.5 text-purple-400 animate-pulse" />
              <span>AI Highlight Mining Engine Active</span>
            </div>
            <h2 className="text-2xl sm:text-3xl font-extrabold text-white">
              Welcome back, {user.full_name || user.username}!
            </h2>
            <p className="text-sm text-zinc-400 mt-1 max-w-xl">
              Your stream clipping pipeline is ready. Upload raw footage or generate shorts from your library.
            </p>
          </div>

          <div className="flex flex-wrap gap-3">
            <Link
              href="/studio"
              className="flex items-center gap-2 px-5 py-3 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 shadow-lg shadow-purple-600/30 transition-all cursor-pointer"
            >
              <Plus className="w-4 h-4" /> Generate New Clips
            </Link>
            <button
              onClick={loadData}
              title="Refresh Data"
              className="p-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300 transition-colors"
            >
              <RefreshCw className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* 2. KPI METRICS CARDS */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8">
        <div className="p-5 rounded-2xl bg-[#0f0c1e]/70 border border-white/10 hover:border-purple-500/30 transition-colors">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold text-zinc-400 uppercase tracking-wider">Total Videos</span>
            <div className="p-2 rounded-xl bg-purple-600/10 text-purple-400">
              <Video className="w-4 h-4" />
            </div>
          </div>
          <div className="text-2xl sm:text-3xl font-extrabold text-white">{videos.length}</div>
          <div className="text-[11px] text-zinc-400 mt-1 flex items-center gap-1">
            <span className="text-emerald-400 font-semibold">Active Library</span> across all projects
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-[#0f0c1e]/70 border border-white/10 hover:border-indigo-500/30 transition-colors">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold text-zinc-400 uppercase tracking-wider">AI Clips Created</span>
            <div className="p-2 rounded-xl bg-indigo-600/10 text-indigo-400">
              <Film className="w-4 h-4" />
            </div>
          </div>
          <div className="text-2xl sm:text-3xl font-extrabold text-white">{clips.length}</div>
          <div className="text-[11px] text-zinc-400 mt-1 flex items-center gap-1">
            <span className="text-purple-400 font-semibold">9:16 Shorts Ready</span> for TikTok & YouTube
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-[#0f0c1e]/70 border border-white/10 hover:border-blue-500/30 transition-colors">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold text-zinc-400 uppercase tracking-wider">Virality Peak</span>
            <div className="p-2 rounded-xl bg-blue-600/10 text-blue-400">
              <Flame className="w-4 h-4" />
            </div>
          </div>
          <div className="text-2xl sm:text-3xl font-extrabold text-white">
            {clips.length > 0 ? Math.max(...clips.map((c) => c.virality_score || 0)) : 98}/100
          </div>
          <div className="text-[11px] text-zinc-400 mt-1 flex items-center gap-1">
            <span className="text-amber-400 font-semibold">High Potential</span> audio peak detection
          </div>
        </div>

        <div className="p-5 rounded-2xl bg-[#0f0c1e]/70 border border-white/10 hover:border-emerald-500/30 transition-colors">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold text-zinc-400 uppercase tracking-wider">GPU Rendering</span>
            <div className="p-2 rounded-xl bg-emerald-600/10 text-emerald-400">
              <Cpu className="w-4 h-4" />
            </div>
          </div>
          <div className="text-2xl sm:text-3xl font-extrabold text-white">NVENC</div>
          <div className="text-[11px] text-zinc-400 mt-1 flex items-center gap-1">
            <span className="text-emerald-400 font-semibold">120 FPS Encoding</span> hardware accelerated
          </div>
        </div>
      </div>

      {/* 3. TABS SELECTOR */}
      <div className="flex items-center justify-between mb-6 pb-4 border-b border-white/10">
        <div className="flex gap-2">
          <button
            onClick={() => setActiveTab("clips")}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all ${
              activeTab === "clips"
                ? "bg-purple-600 text-white shadow-md shadow-purple-600/30"
                : "bg-white/5 text-zinc-400 hover:text-white"
            }`}
          >
            🔥 Recent Viral Clips ({clips.length})
          </button>
          <button
            onClick={() => setActiveTab("videos")}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all ${
              activeTab === "videos"
                ? "bg-purple-600 text-white shadow-md shadow-purple-600/30"
                : "bg-white/5 text-zinc-400 hover:text-white"
            }`}
          >
            🎬 Uploaded Video Projects ({videos.length})
          </button>
        </div>

        <Link
          href={activeTab === "clips" ? "/my-clips" : "/my-projects"}
          className="text-xs text-purple-400 hover:text-purple-300 font-semibold flex items-center gap-1"
        >
          View All <ArrowRight className="w-3.5 h-3.5" />
        </Link>
      </div>

      {/* 4. CONTENT GRID */}
      {loading ? (
        <div className="p-12 text-center text-zinc-500">
          <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-purple-400" />
          <p className="text-xs">Loading studio media records...</p>
        </div>
      ) : activeTab === "clips" ? (
        clips.length === 0 ? (
          <div className="p-12 text-center rounded-3xl bg-[#0f0c1e]/50 border border-white/10">
            <Film className="w-12 h-12 mx-auto text-zinc-600 mb-4" />
            <h3 className="text-base font-bold text-white mb-1">No AI Clips Generated Yet</h3>
            <p className="text-xs text-zinc-400 max-w-md mx-auto mb-6">
              Launch the AI Clipping Studio to process your long-form video footage into vertical shorts.
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
            {clips.map((clip) => (
              <div
                key={clip.id}
                className="group rounded-2xl bg-[#0f0c1e]/80 border border-white/10 hover:border-purple-500/40 overflow-hidden shadow-lg transition-all duration-300 flex flex-col justify-between"
              >
                {/* Simulated Thumbnail / Video Player */}
                <div className="relative aspect-[9/16] bg-black max-h-[360px] overflow-hidden flex items-center justify-center">
                  <div className="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent z-10" />

                  {/* Play Video Trigger */}
                  <div className="w-12 h-12 rounded-full bg-purple-600/80 text-white flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform z-20">
                    <Play className="w-5 h-5 fill-white ml-0.5" />
                  </div>

                  {/* Virality Badge */}
                  <div className="absolute top-3 left-3 z-20 flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-black/70 border border-white/15 text-[11px] font-extrabold text-amber-400 backdrop-blur-md">
                    <Flame className="w-3.5 h-3.5 fill-amber-400" />
                    <span>{clip.virality_score || 95}</span>
                  </div>

                  {/* Aspect Ratio Badge */}
                  <div className="absolute top-3 right-3 z-20 px-2 py-0.5 rounded text-[10px] font-bold bg-purple-600/80 text-white uppercase backdrop-blur-md">
                    {clip.aspect_ratio || "9:16"}
                  </div>

                  {/* Duration Badge */}
                  <div className="absolute bottom-3 right-3 z-20 px-2 py-0.5 rounded text-[10px] font-bold bg-black/80 text-zinc-300 backdrop-blur-md">
                    {formatDuration(clip.duration || 45)}
                  </div>
                </div>

                {/* Details */}
                <div className="p-4 flex-1 flex flex-col justify-between">
                  <div>
                    <h4 className="text-sm font-bold text-white line-clamp-2 mb-1">
                      {clip.title || "Viral Gaming Highlight Moment"}
                    </h4>
                    <p className="text-[11px] text-zinc-400 line-clamp-1">
                      {clip.description || "Auto-detected high-energy gaming clutch"}
                    </p>
                  </div>

                  <div className="mt-4 pt-3 border-t border-white/10 flex items-center justify-between">
                    <span className="text-[10px] text-zinc-500 font-mono">
                      {clip.created_at ? new Date(clip.created_at).toLocaleDateString() : "Just now"}
                    </span>
                    <a
                      href={clip.download_url || "#"}
                      download
                      className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-purple-300 bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/30 transition-colors"
                    >
                      <Download className="w-3.5 h-3.5" /> Export
                    </a>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )
      ) : (
        /* VIDEOS LIST */
        videos.length === 0 ? (
          <div className="p-12 text-center rounded-3xl bg-[#0f0c1e]/50 border border-white/10">
            <Video className="w-12 h-12 mx-auto text-zinc-600 mb-4" />
            <h3 className="text-base font-bold text-white mb-1">No Source Videos Uploaded</h3>
            <p className="text-xs text-zinc-400 max-w-md mx-auto mb-6">
              Upload footage from your stream or paste a video link to begin AI clipping.
            </p>
            <Link
              href="/studio"
              className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-purple-600 hover:bg-purple-500"
            >
              <Upload className="w-3.5 h-3.5" /> Upload Video
            </Link>
          </div>
        ) : (
          <div className="space-y-3">
            {videos.map((vid) => (
              <div
                key={vid.id}
                className="p-4 sm:p-5 rounded-2xl bg-[#0f0c1e]/70 border border-white/10 hover:border-purple-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-all"
              >
                <div className="flex items-center gap-4 min-w-0">
                  <div className="w-12 h-12 rounded-xl bg-purple-600/10 border border-purple-500/20 flex items-center justify-center text-purple-400 flex-shrink-0">
                    <Video className="w-6 h-6" />
                  </div>
                  <div className="truncate">
                    <h4 className="text-sm font-bold text-white truncate">{vid.title || "Source Footage"}</h4>
                    <div className="flex items-center gap-3 text-xs text-zinc-400 mt-0.5">
                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3 text-zinc-500" /> {formatDuration(vid.duration_seconds || 180)}
                      </span>
                      <span>•</span>
                      <span>{vid.source_type || "mp4_upload"}</span>
                      <span>•</span>
                      <span>{vid.created_at ? new Date(vid.created_at).toLocaleDateString() : "Recent"}</span>
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-3">
                  <Link
                    href={`/studio?video_id=${vid.id}`}
                    className="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-purple-600 hover:bg-purple-500 transition-colors"
                  >
                    <Sparkles className="w-3.5 h-3.5" /> Open in Studio
                  </Link>
                </div>
              </div>
            ))}
          </div>
        )
      )}
    </DashboardLayout>
  );
}
