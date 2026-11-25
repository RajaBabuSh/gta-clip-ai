"use client";

import React from "react";
import { Sparkles, Video, Cpu, HardDrive } from "lucide-react";

interface HardwareDiagnostic {
  gpu?: { detected: boolean; name?: string };
  video_acceleration?: { active_encoder: string; mode: string };
  ai_acceleration?: { mode: string };
}

interface NavbarProps {
  hardware?: HardwareDiagnostic | null;
  dbConnected: boolean;
}

export const Navbar: React.FC<NavbarProps> = ({ hardware, dbConnected }) => {
  return (
    <header className="border-b border-zinc-800 bg-zinc-950/80 backdrop-blur sticky top-0 z-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="h-10 w-10 rounded-xl bg-gradient-to-tr from-amber-500 via-rose-600 to-violet-600 p-0.5 shadow-lg shadow-rose-900/20">
            <div className="w-full h-full bg-zinc-950 rounded-[10px] flex items-center justify-center">
              <Video className="w-5 h-5 text-rose-500" />
            </div>
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="font-extrabold text-xl tracking-tight text-zinc-100">
                GTA CLIP <span className="text-transparent bg-clip-text bg-gradient-to-r from-rose-500 to-amber-500">AI</span>
              </h1>
              <span className="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/20">
                Phase 1 Scaffolding
              </span>
            </div>
            <p className="text-xs text-zinc-400">High-Performance Gaming Highlight Studio</p>
          </div>
        </div>

        {/* Hardware Status Indicators */}
        <div className="flex items-center gap-2 text-xs">
          {/* DB Indicator */}
          <div className="hidden sm:flex items-center gap-1.5 px-3 py-1 rounded-full bg-zinc-900 border border-zinc-800">
            <span className={`w-2 h-2 rounded-full ${dbConnected ? "bg-emerald-500 animate-pulse" : "bg-rose-500"}`} />
            <span className="text-zinc-300 font-medium">MySQL</span>
          </div>

          {/* NVENC Hardware Acceleration Indicator */}
          <div className="flex items-center gap-1.5 px-3 py-1 rounded-full bg-zinc-900 border border-zinc-800 text-zinc-300">
            <Cpu className="w-3.5 h-3.5 text-amber-400" />
            <span className="font-medium">
              {hardware?.video_acceleration?.mode || "Detecting Hardware..."}
            </span>
          </div>
        </div>
      </div>
    </header>
  );
};
