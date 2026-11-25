"use client";

import React from "react";
import { Film, Clock, Calendar, CheckCircle } from "lucide-react";
import { VideoData } from "./VideoDetailsCard";

interface RecentVideosListProps {
  videos: VideoData[];
  onSelectVideo: (video: VideoData) => void;
  selectedId?: string;
}

export const RecentVideosList: React.FC<RecentVideosListProps> = ({
  videos,
  onSelectVideo,
  selectedId
}) => {
  if (videos.length === 0) return null;

  return (
    <div className="w-full bg-zinc-900/60 border border-zinc-800 rounded-2xl p-5 space-y-3">
      <div className="flex items-center justify-between border-b border-zinc-800/80 pb-3">
        <h4 className="text-xs font-bold uppercase tracking-wider text-zinc-300 flex items-center gap-2">
          <Film className="w-4 h-4 text-rose-500" />
          Ingested Source Videos ({videos.length})
        </h4>
        <span className="text-[11px] text-zinc-500">Stored in MySQL DB</span>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {videos.map((vid) => {
          const isSelected = vid.id === selectedId;
          const mins = Math.floor((vid.duration_seconds || 0) / 60);
          const secs = Math.floor((vid.duration_seconds || 0) % 60);
          const durationStr = `${mins}:${secs < 10 ? "0" : ""}${secs}`;

          return (
            <div
              key={vid.id}
              onClick={() => onSelectVideo(vid)}
              className={`p-3.5 rounded-xl border text-left cursor-pointer transition-all ${
                isSelected
                  ? "bg-rose-950/20 border-rose-600 shadow-md shadow-rose-950/30"
                  : "bg-zinc-950/60 border-zinc-800 hover:border-zinc-700 hover:bg-zinc-950/90"
              }`}
            >
              <div className="flex items-start justify-between gap-2">
                <div className="text-xs font-semibold text-zinc-100 truncate flex-1">
                  {vid.title}
                </div>
                {isSelected && (
                  <CheckCircle className="w-4 h-4 text-rose-500 flex-shrink-0" />
                )}
              </div>

              <div className="flex items-center gap-3 text-[11px] text-zinc-400 mt-2">
                <span className="flex items-center gap-1">
                  <Clock className="w-3 h-3 text-amber-400" />
                  {durationStr}
                </span>
                <span>{vid.width}x{vid.height}</span>
                <span className="uppercase text-[10px] px-1 rounded bg-zinc-800 text-zinc-300">
                  {vid.source_type}
                </span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};
