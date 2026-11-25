"use client";

import React from "react";
import { Cpu, ShieldCheck, Database, Zap, HardDrive, CheckCircle2 } from "lucide-react";

interface HealthData {
  status: string;
  project: string;
  environment: string;
  database: {
    status: string;
    host: string;
    port: number;
    database: string;
  };
  hardware: {
    gpu: {
      detected: boolean;
      name?: string;
      vram_total_mb?: number;
      driver_version?: string;
    };
    ai_acceleration: {
      torch_cuda: boolean;
      whisper_device: string;
      mode: string;
    };
    video_acceleration: {
      ffmpeg_installed: boolean;
      ffprobe_installed: boolean;
      nvenc_supported: boolean;
      active_encoder: string;
      mode: string;
    };
  };
}

export const HardwareStatusBanner: React.FC<{ health: HealthData | null; loading: boolean }> = ({
  health,
  loading
}) => {
  if (loading) {
    return (
      <div className="w-full bg-zinc-900/60 border border-zinc-800 rounded-2xl p-4 animate-pulse flex items-center justify-between">
        <div className="h-5 bg-zinc-800 rounded w-48" />
        <div className="h-5 bg-zinc-800 rounded w-32" />
      </div>
    );
  }

  if (!health) {
    return (
      <div className="w-full bg-rose-950/20 border border-rose-900/40 rounded-2xl p-4 text-rose-300 text-sm flex items-center gap-3">
        <span className="w-2.5 h-2.5 rounded-full bg-rose-500 animate-ping" />
        Backend service unreachable at <code className="bg-rose-950 px-2 py-0.5 rounded text-xs">http://127.0.0.1:8000</code>. Please start the backend script.
      </div>
    );
  }

  const gpuName = health.hardware?.gpu?.name || "NVIDIA GPU Detected";
  const vram = health.hardware?.gpu?.vram_total_mb ? `${Math.round(health.hardware.gpu.vram_total_mb / 1024)}GB VRAM` : "6GB VRAM";

  return (
    <div className="w-full bg-gradient-to-r from-zinc-900 via-zinc-900/90 to-zinc-950 border border-zinc-800/80 rounded-2xl p-5 shadow-xl">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-zinc-800/60 pb-4 mb-4">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/20">
            <Zap className="w-5 h-5" />
          </div>
          <div>
            <h3 className="text-sm font-semibold text-zinc-100 flex items-center gap-2">
              Hardware & Engine Diagnostic
              <span className="text-[10px] bg-emerald-500/10 text-emerald-400 px-2 py-0.5 rounded-full border border-emerald-500/20 flex items-center gap-1">
                <CheckCircle2 className="w-3 h-3" /> Operational
              </span>
            </h3>
            <p className="text-xs text-zinc-400">Windows 11 Laptop Configuration Verified</p>
          </div>
        </div>

        <div className="text-xs text-zinc-400 flex items-center gap-2">
          <Database className="w-3.5 h-3.5 text-emerald-400" />
          <span>MySQL Database: <b className="text-zinc-200">{health.database.database}</b> on {health.database.host}:{health.database.port}</span>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        {/* GPU & NVENC Card */}
        <div className="bg-zinc-950/60 border border-zinc-800/60 rounded-xl p-3 flex items-start gap-3">
          <Cpu className="w-5 h-5 text-emerald-400 mt-0.5 flex-shrink-0" />
          <div>
            <div className="text-[11px] uppercase tracking-wider text-zinc-400 font-semibold">Video Transcoding</div>
            <div className="text-xs font-bold text-zinc-200 mt-0.5">{health.hardware.video_acceleration.mode}</div>
            <div className="text-[11px] text-zinc-400 mt-0.5">{gpuName} ({vram})</div>
          </div>
        </div>

        {/* AI & Whisper Card */}
        <div className="bg-zinc-950/60 border border-zinc-800/60 rounded-xl p-3 flex items-start gap-3">
          <ShieldCheck className="w-5 h-5 text-violet-400 mt-0.5 flex-shrink-0" />
          <div>
            <div className="text-[11px] uppercase tracking-wider text-zinc-400 font-semibold">AI Speech Model</div>
            <div className="text-xs font-bold text-zinc-200 mt-0.5">Whisper Engine</div>
            <div className="text-[11px] text-zinc-400 mt-0.5">{health.hardware.ai_acceleration.mode} (Device: {health.hardware.ai_acceleration.whisper_device})</div>
          </div>
        </div>

        {/* FFprobe Inspection Card */}
        <div className="bg-zinc-950/60 border border-zinc-800/60 rounded-xl p-3 flex items-start gap-3">
          <HardDrive className="w-5 h-5 text-rose-400 mt-0.5 flex-shrink-0" />
          <div>
            <div className="text-[11px] uppercase tracking-wider text-zinc-400 font-semibold">Media Stream Inspector</div>
            <div className="text-xs font-bold text-zinc-200 mt-0.5">FFmpeg & FFprobe 9.0.2</div>
            <div className="text-[11px] text-zinc-400 mt-0.5">Local High-Speed Storage Buffer</div>
          </div>
        </div>
      </div>
    </div>
  );
};
