"use client";

import React, { useState, useEffect, useRef } from "react";
import { Navbar } from "@/components/Navbar";
import { HardwareStatusBanner } from "@/components/HardwareStatusBanner";
import { UploadZone } from "@/components/UploadZone";
import { VideoDetailsCard, VideoData } from "@/components/VideoDetailsCard";
import { ClipsStudio, ClipData } from "@/components/ClipsStudio";
import { RecentVideosList } from "@/components/RecentVideosList";
import { Film, Sparkles, RefreshCw, AlertCircle } from "lucide-react";

export default function Home() {
  const apiBaseUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";
  const storageBaseUrl = process.env.NEXT_PUBLIC_STORAGE_URL || "http://localhost:8000/storage";

  const [healthData, setHealthData] = useState<any | null>(null);
  const [healthLoading, setHealthLoading] = useState(true);
  const [recentVideos, setRecentVideos] = useState<VideoData[]>([]);
  const [activeVideo, setActiveVideo] = useState<VideoData | null>(null);

  // Clipping state
  const [clips, setClips] = useState<ClipData[]>([]);
  const [clipsLoading, setClipsLoading] = useState(false);
  const [isGenerating, setIsGenerating] = useState(false);
  const [activeJob, setActiveJob] = useState<any | null>(null);
  const [generationError, setGenerationError] = useState<string | null>(null);

  const pollingRef = useRef<NodeJS.Timeout | null>(null);

  // Fetch health check
  const fetchHealth = async () => {
    try {
      setHealthLoading(true);
      const res = await fetch(`${apiBaseUrl}/health`);
      if (res.ok) {
        const data = await res.json();
        setHealthData(data);
      } else {
        setHealthData(null);
      }
    } catch (e) {
      setHealthData(null);
    } finally {
      setHealthLoading(false);
    }
  };

  // Fetch recent videos
  const fetchRecentVideos = async () => {
    try {
      const res = await fetch(`${apiBaseUrl}/videos/`);
      if (res.ok) {
        const data = await res.json();
        setRecentVideos(data);
        if (data.length > 0 && !activeVideo) {
          setActiveVideo(data[0]);
        }
      }
    } catch (e) {
      console.error("Failed to load videos:", e);
    }
  };

  // Fetch clips for a video
  const fetchClipsForVideo = async (videoId: string) => {
    try {
      setClipsLoading(true);
      const res = await fetch(`${apiBaseUrl}/clips/by-video/${videoId}`);
      if (res.ok) {
        const data = await res.json();
        setClips(data);
      } else {
        setClips([]);
      }
    } catch (err) {
      console.error("Failed to fetch clips:", err);
      setClips([]);
    } finally {
      setClipsLoading(false);
    }
  };

  // Check if there is an active job for a video
  const checkJobStatus = async (videoId: string) => {
    try {
      const res = await fetch(`${apiBaseUrl}/jobs/by-video/${videoId}`);
      if (res.ok) {
        const job = await res.json();
        setActiveJob(job);
        if (job.status === "PENDING" || job.status === "PROCESSING") {
          setIsGenerating(true);
          startPolling(job.id, videoId);
        } else {
          setIsGenerating(false);
        }
      }
    } catch (err) {
      // No active job
    }
  };

  const stopPolling = () => {
    if (pollingRef.current) {
      clearInterval(pollingRef.current);
      pollingRef.current = null;
    }
  };

  const startPolling = (jobId: string, videoId: string) => {
    stopPolling();
    pollingRef.current = setInterval(async () => {
      try {
        const res = await fetch(`${apiBaseUrl}/jobs/${jobId}`);
        if (res.ok) {
          const job = await res.json();
          setActiveJob(job);

          if (job.status === "COMPLETED") {
            stopPolling();
            setIsGenerating(false);
            fetchClipsForVideo(videoId);
          } else if (job.status === "FAILED") {
            stopPolling();
            setIsGenerating(false);
            setGenerationError(job.error_message || "Video clipping pipeline failed.");
          }
        }
      } catch (err) {
        console.error("Error polling job status:", err);
      }
    }, 1200);
  };

  // Initial load
  useEffect(() => {
    fetchHealth();
    fetchRecentVideos();
    return () => stopPolling();
  }, []);

  // When activeVideo changes, fetch its clips and active job status
  useEffect(() => {
    if (activeVideo) {
      fetchClipsForVideo(activeVideo.id);
      checkJobStatus(activeVideo.id);
    } else {
      setClips([]);
      setActiveJob(null);
      setIsGenerating(false);
    }
  }, [activeVideo?.id]);

  const handleUploadSuccess = (newVideo: VideoData) => {
    setActiveVideo(newVideo);
    setRecentVideos((prev) => [newVideo, ...prev]);
    setClips([]);
  };

  const handleCancelJob = async (jobId: string) => {
    try {
      await fetch(`${apiBaseUrl}/clips/cancel/${jobId}`, { method: "POST" });
      stopPolling();
      setIsGenerating(false);
      if (activeVideo) {
        checkJobStatus(activeVideo.id);
      }
    } catch (err) {
      console.error("Failed to cancel job:", err);
    }
  };

  const handleStartClipping = async (targetCount: number, burnSubtitles: boolean) => {
    if (!activeVideo) return;

    try {
      setIsGenerating(true);
      setGenerationError(null);

      const res = await fetch(`${apiBaseUrl}/clips/generate`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          video_id: activeVideo.id,
          target_count: targetCount,
          burn_subtitles: burnSubtitles
        }),
      });

      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || "Failed to start AI clipping.");
      }

      const job = await res.json();
      setActiveJob(job);
      startPolling(job.id, activeVideo.id);
    } catch (err: any) {
      setIsGenerating(false);
      setGenerationError(err.message || "Failed to initiate clipping.");
    }
  };

  const isDbConnected = healthData?.database?.status === "connected";

  return (
    <div className="min-h-screen bg-zinc-950 text-zinc-100 flex flex-col font-sans selection:bg-rose-500 selection:text-white">
      {/* Navbar with hardware badges */}
      <Navbar hardware={healthData?.hardware} dbConnected={isDbConnected} />

      {/* Main Content */}
      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        {/* Hardware Status Banner */}
        <section>
          <HardwareStatusBanner health={healthData} loading={healthLoading} />
        </section>

        {/* Hero Section */}
        <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-zinc-800/80 pb-6">
          <div>
            <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold mb-2">
              <Sparkles className="w-3.5 h-3.5" /> Phase 3: Smart AI Viral Clipping Engine
            </div>
            <h2 className="text-3xl font-extrabold tracking-tight text-zinc-100">
              AI Gaming Moments & 9:16 Shorts Studio
            </h2>
            <p className="text-sm text-zinc-400 mt-1 max-w-2xl">
              Automatic peak-style moment detection using Whisper word timestamps, audio energy RMS, speech velocity, and NVIDIA RTX 3050 NVENC vertical reframing.
            </p>
          </div>

          <button
            onClick={() => {
              fetchHealth();
              fetchRecentVideos();
              if (activeVideo) fetchClipsForVideo(activeVideo.id);
            }}
            className="flex items-center gap-2 px-3.5 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-xs font-semibold text-zinc-300 transition self-start sm:self-auto"
          >
            <RefreshCw className="w-3.5 h-3.5" />
            Refresh Diagnostic
          </button>
        </div>

        {generationError && (
          <div className="p-4 rounded-2xl bg-rose-950/40 border border-rose-800 text-rose-300 text-xs flex items-center gap-3">
            <AlertCircle className="w-5 h-5 text-rose-400 shrink-0" />
            <div>
              <p className="font-bold">Clipping Generation Notice:</p>
              <p>{generationError}</p>
            </div>
          </div>
        )}

        {/* Active Ingestion Form */}
        <section>
          <UploadZone
            onUploadSuccess={handleUploadSuccess}
            apiBaseUrl={apiBaseUrl}
          />
        </section>

        {/* Selected Video Details Card & Clipping Trigger */}
        {activeVideo && (
          <section className="animate-in fade-in duration-300">
            <VideoDetailsCard
              video={activeVideo}
              storageBaseUrl={storageBaseUrl}
              onGenerateClips={handleStartClipping}
              onCancelJob={handleCancelJob}
              isGenerating={isGenerating}
              activeJob={activeJob}
              clipsCount={clips.length}
            />
          </section>
        )}

        {/* Generated 10 Clips Studio Section */}
        {activeVideo && clips.length > 0 && (
          <section className="animate-in fade-in duration-300">
            <ClipsStudio
              clips={clips}
              jobId={activeJob?.id}
              apiBaseUrl={apiBaseUrl}
              storageBaseUrl={storageBaseUrl}
              onClipUpdated={() => fetchClipsForVideo(activeVideo.id)}
            />
          </section>
        )}

        {/* Ingested Videos List */}
        <section>
          <RecentVideosList
            videos={recentVideos}
            onSelectVideo={(v) => setActiveVideo(v)}
            selectedId={activeVideo?.id}
          />
        </section>
      </main>

      {/* Footer */}
      <footer className="border-t border-zinc-900 bg-zinc-950/80 py-6 text-center text-xs text-zinc-400">
        <p>
          GTA CLIP AI &bull; Next.js + FastAPI + FFmpeg + Whisper + MySQL &bull; Windows 11 RTX 3050 Optimized
        </p>
      </footer>
    </div>
  );
}
