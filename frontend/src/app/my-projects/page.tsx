"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { DashboardLayout } from "@/components/DashboardLayout";
import { Video, Clock, Sparkles, Upload, ArrowRight, Film, CheckCircle2 } from "lucide-react";

export default function MyProjectsPage() {
  const [videos, setVideos] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchProjects = async () => {
      try {
        setLoading(true);
        const res = await fetch("/api/videos?limit=50");
        if (res.ok) {
          const data = await res.json();
          setVideos(data);
        }
      } catch (e) {
        console.error("Failed to load projects:", e);
      } finally {
        setLoading(false);
      }
    };
    fetchProjects();
  }, []);

  const formatDuration = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs < 10 ? "0" : ""}${secs}`;
  };

  return (
    <DashboardLayout pageTitle="My Video Projects">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
          <h2 className="text-xl sm:text-2xl font-extrabold text-white">Source Videos & VODs</h2>
          <p className="text-xs text-zinc-400 mt-1">
            Manage your uploaded long-form stream recordings and launch AI clipping pipelines.
          </p>
        </div>

        <Link
          href="/studio"
          className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 shadow-md shadow-purple-600/30"
        >
          <Upload className="w-4 h-4" /> Upload New Footage
        </Link>
      </div>

      {loading ? (
        <div className="py-20 text-center text-zinc-500">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mx-auto mb-3" />
          <p className="text-xs">Loading video projects...</p>
        </div>
      ) : videos.length === 0 ? (
        <div className="p-16 text-center rounded-3xl bg-[#0f0c1e]/60 border border-white/10">
          <Video className="w-12 h-12 mx-auto text-zinc-600 mb-4" />
          <h3 className="text-base font-bold text-white mb-1">No Video Projects Found</h3>
          <p className="text-xs text-zinc-400 max-w-md mx-auto mb-6">
            Upload your first stream recording to begin mining clips with Whisper AI.
          </p>
          <Link
            href="/studio"
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-purple-600 hover:bg-purple-500"
          >
            <Upload className="w-3.5 h-3.5" /> Upload Video Now
          </Link>
        </div>
      ) : (
        <div className="space-y-3">
          {videos.map((vid) => (
            <div
              key={vid.id}
              className="p-5 rounded-2xl bg-[#0f0c1e]/70 border border-white/10 hover:border-purple-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-all"
            >
              <div className="flex items-center gap-4 min-w-0">
                <div className="w-12 h-12 rounded-xl bg-purple-600/15 border border-purple-500/25 flex items-center justify-center text-purple-400 flex-shrink-0">
                  <Video className="w-6 h-6" />
                </div>
                <div className="truncate">
                  <h4 className="text-sm font-bold text-white truncate">{vid.title || "Footage Recording"}</h4>
                  <div className="flex items-center gap-3 text-xs text-zinc-400 mt-1">
                    <span className="flex items-center gap-1 text-zinc-300">
                      <Clock className="w-3.5 h-3.5 text-zinc-500" /> {formatDuration(vid.duration_seconds || 180)}
                    </span>
                    <span>•</span>
                    <span className="uppercase text-[10px] font-bold text-purple-400 bg-purple-500/10 px-2 py-0.5 rounded">
                      {vid.source_type || "mp4"}
                    </span>
                    <span>•</span>
                    <span>{vid.created_at ? new Date(vid.created_at).toLocaleDateString() : "Active"}</span>
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-3">
                <Link
                  href={`/studio?video_id=${vid.id}`}
                  className="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-purple-600 hover:bg-purple-500 transition-colors shadow-sm"
                >
                  <Sparkles className="w-3.5 h-3.5" /> Open in Studio
                </Link>
              </div>
            </div>
          ))}
        </div>
      )}
    </DashboardLayout>
  );
}
