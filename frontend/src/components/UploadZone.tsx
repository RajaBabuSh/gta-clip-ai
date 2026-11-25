"use client";

import React, { useState, useRef } from "react";
import { Upload, FileVideo, AlertCircle, Loader2, CheckCircle2, ArrowRight, ShieldCheck, Clock, User, ExternalLink, XCircle } from "lucide-react";

const YouTubeIcon = ({ className = "w-4 h-4 text-red-500" }: { className?: string }) => (
  <svg className={className} viewBox="0 0 24 24" fill="currentColor">
    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
  </svg>
);

interface UploadZoneProps {
  onUploadSuccess: (videoData: any) => void;
  apiBaseUrl: string;
}

interface YouTubeVerifiedData {
  youtube_id: string;
  youtube_url: string;
  title: string;
  channel_title: string;
  thumbnail_url: string;
  duration_seconds?: number;
  is_live: boolean;
  media_access_status: string;
  access_message: string;
}

export const UploadZone: React.FC<UploadZoneProps> = ({ onUploadSuccess, apiBaseUrl }) => {
  const [activeTab, setActiveTab] = useState<"upload" | "youtube">("youtube");
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [customTitle, setCustomTitle] = useState("");
  const [isUploading, setIsUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // YouTube tab state
  const [youtubeUrl, setYoutubeUrl] = useState("https://www.youtube.com/live/irNhWMG560k?si=rEmqQMOICANxtEh6");
  const [isVerifyingYT, setIsVerifyingYT] = useState(false);
  const [verifiedYT, setVerifiedYT] = useState<YouTubeVerifiedData | null>(null);
  const [isIngestingYT, setIsIngestingYT] = useState(false);
  const [clipDurationLimit, setClipDurationLimit] = useState<number>(300); // 5 mins default

  const handleFileSelect = (file: File) => {
    setErrorMessage(null);
    const validExtensions = [".mp4", ".mov", ".mkv", ".webm"];
    const fileExt = "." + file.name.split(".").pop()?.toLowerCase();
    
    if (!validExtensions.includes(fileExt)) {
      setErrorMessage(`Unsupported format '${fileExt}'. Please select an MP4, MOV, MKV, or WebM video.`);
      return;
    }

    if (file.size > 500 * 1024 * 1024) {
      setErrorMessage("File exceeds 500MB limit. Please provide a lighter gaming video.");
      return;
    }

    setSelectedFile(file);
    if (!customTitle) {
      setCustomTitle(file.name.replace(/\.[^/.]+$/, ""));
    }
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(true);
  };

  const handleDragLeave = () => {
    setIsDragging(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      handleFileSelect(e.dataTransfer.files[0]);
    }
  };

  const handleUploadSubmit = async () => {
    if (!selectedFile) return;

    setIsUploading(true);
    setUploadProgress(10);
    setErrorMessage(null);

    const formData = new FormData();
    formData.append("file", selectedFile);
    if (customTitle.trim()) {
      formData.append("title", customTitle.trim());
    }

    try {
      setUploadProgress(35);
      const res = await fetch(`${apiBaseUrl}/videos/upload`, {
        method: "POST",
        body: formData,
      });

      setUploadProgress(80);
      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.detail || "Upload or media analysis failed.");
      }

      setUploadProgress(100);
      onUploadSuccess(data);
      setSelectedFile(null);
      setCustomTitle("");
    } catch (err: any) {
      setErrorMessage(err.message || "Failed to upload video.");
    } finally {
      setIsUploading(false);
      setUploadProgress(0);
    }
  };

  // YouTube Verification Step
  const handleVerifyYouTube = async () => {
    if (!youtubeUrl.trim()) {
      setErrorMessage("Please enter a valid YouTube video or live stream URL.");
      return;
    }

    setIsVerifyingYT(true);
    setErrorMessage(null);
    setVerifiedYT(null);

    try {
      const res = await fetch(`${apiBaseUrl}/videos/youtube/verify`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ url: youtubeUrl.trim() }),
      });

      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.detail || "Failed to verify YouTube video metadata.");
      }

      setVerifiedYT(data);
    } catch (err: any) {
      setErrorMessage(err.message || "Unable to access YouTube video. Check URL or restrictions.");
    } finally {
      setIsVerifyingYT(false);
    }
  };

  // YouTube Confirmation & Ingestion Step
  const handleConfirmYouTube = async () => {
    if (!verifiedYT) return;

    setIsIngestingYT(true);
    setErrorMessage(null);

    try {
      const res = await fetch(`${apiBaseUrl}/videos/youtube/confirm`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          youtube_id: verifiedYT.youtube_id,
          custom_title: verifiedYT.title,
          duration_limit_seconds: clipDurationLimit,
        }),
      });

      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.detail || "Media ingestion failed. Media may be restricted.");
      }

      onUploadSuccess(data);
      setVerifiedYT(null);
    } catch (err: any) {
      setErrorMessage(err.message || "Failed to ingest YouTube video stream.");
    } finally {
      setIsIngestingYT(false);
    }
  };

  const formatSeconds = (sec?: number) => {
    if (!sec) return "Live / Unknown";
    const hrs = Math.floor(sec / 3600);
    const mins = Math.floor((sec % 3600) / 60);
    const s = Math.floor(sec % 60);
    if (hrs > 0) {
      return `${hrs}h ${mins}m ${s}s`;
    }
    return `${mins}m ${s}s`;
  };

  return (
    <div className="w-full bg-zinc-900/80 border border-zinc-800 rounded-2xl p-6 shadow-2xl backdrop-blur">
      {/* Tab Switcher */}
      <div className="flex items-center gap-2 border-b border-zinc-800 pb-4 mb-6">
        <button
          onClick={() => {
            setActiveTab("youtube");
            setErrorMessage(null);
          }}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all ${
            activeTab === "youtube"
              ? "bg-rose-600 text-white shadow-lg shadow-rose-900/30"
              : "text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60"
          }`}
        >
          <YouTubeIcon className="w-4 h-4 text-red-500" />
          <span>YouTube Ingestion</span>
          <span className="text-[10px] bg-rose-500/30 text-rose-200 px-1.5 py-0.5 rounded-full ml-1">
            Phase 2 Active
          </span>
        </button>

        <button
          onClick={() => {
            setActiveTab("upload");
            setErrorMessage(null);
          }}
          className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition-all ${
            activeTab === "upload"
              ? "bg-rose-600 text-white shadow-lg shadow-rose-900/30"
              : "text-zinc-400 hover:text-zinc-200 hover:bg-zinc-800/60"
          }`}
        >
          <Upload className="w-4 h-4" />
          <span>Authorized File Upload</span>
          <span className="text-[10px] bg-zinc-800 text-zinc-400 px-1.5 py-0.5 rounded-full ml-1 border border-zinc-700">
            MP4/MOV
          </span>
        </button>
      </div>

      {/* Error Callout */}
      {errorMessage && (
        <div className="mb-6 p-4 rounded-xl bg-rose-950/40 border border-rose-800/60 text-rose-300 text-sm flex items-start gap-3">
          <AlertCircle className="w-5 h-5 text-rose-400 flex-shrink-0 mt-0.5" />
          <div>
            <div className="font-semibold text-rose-200">Processing Notice</div>
            <div className="text-xs mt-0.5 leading-relaxed">{errorMessage}</div>
          </div>
        </div>
      )}

      {/* TAB 1: YOUTUBE INGESTION & VERIFICATION (PHASE 2) */}
      {activeTab === "youtube" && (
        <div className="space-y-6">
          {!verifiedYT ? (
            <div className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-zinc-300 mb-1.5">
                  Paste YouTube Video or Live Stream URL
                </label>
                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                  <div className="relative flex-1">
                    <input
                      type="text"
                      value={youtubeUrl}
                      onChange={(e) => setYoutubeUrl(e.target.value)}
                      placeholder="https://www.youtube.com/watch?v=... or https://www.youtube.com/live/..."
                      disabled={isVerifyingYT}
                      className="w-full px-4 py-3 rounded-xl bg-zinc-950 border border-zinc-700 text-sm text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-rose-500 transition pl-10"
                    />
                    <div className="absolute left-3 top-3.5">
                      <YouTubeIcon className="w-4 h-4 text-red-500" />
                    </div>
                  </div>

                  <button
                    onClick={handleVerifyYouTube}
                    disabled={isVerifyingYT}
                    className="px-6 py-3 rounded-xl bg-gradient-to-r from-rose-600 to-amber-600 hover:from-rose-500 hover:to-amber-500 text-white font-semibold text-sm flex items-center justify-center gap-2 shadow-lg shadow-rose-900/30 transition disabled:opacity-50 whitespace-nowrap"
                  >
                    {isVerifyingYT ? (
                      <>
                        <Loader2 className="w-4 h-4 animate-spin" />
                        <span>Verifying Metadata...</span>
                      </>
                    ) : (
                      <>
                        <span>Inspect & Verify Video</span>
                        <ArrowRight className="w-4 h-4" />
                      </>
                    )}
                  </button>
                </div>
              </div>

              <div className="text-[11px] text-zinc-400 bg-zinc-950/60 p-3 rounded-xl border border-zinc-800/80 flex items-center gap-2">
                <ShieldCheck className="w-4 h-4 text-emerald-400 flex-shrink-0" />
                <span>
                  Strict verification enabled: The backend inspects the genuine video ID, channel name, and access permissions. No media is downloaded until you confirm.
                </span>
              </div>
            </div>
          ) : (
            /* STEP 2: SOURCE VIDEO CONFIRMATION CARD */
            <div className="bg-zinc-950 border border-zinc-800 rounded-2xl p-5 space-y-5 animate-in fade-in duration-200">
              <div className="flex items-center justify-between border-b border-zinc-800 pb-3">
                <div className="flex items-center gap-2">
                  <span className="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 flex items-center gap-1">
                    <ShieldCheck className="w-3.5 h-3.5" /> Source Confirmation Required
                  </span>
                  <span className="text-xs text-zinc-400 font-mono">
                    ID: {verifiedYT.youtube_id}
                  </span>
                </div>

                <button
                  onClick={() => setVerifiedYT(null)}
                  disabled={isIngestingYT}
                  className="text-xs text-zinc-400 hover:text-zinc-200 flex items-center gap-1"
                >
                  <XCircle className="w-4 h-4" /> Change Video
                </button>
              </div>

              {/* Source Video Preview Card */}
              <div className="grid grid-cols-1 md:grid-cols-12 gap-5 items-center">
                {/* Thumbnail */}
                <div className="md:col-span-5 relative rounded-xl overflow-hidden border border-zinc-800 group shadow-lg bg-zinc-900">
                  <img
                    src={verifiedYT.thumbnail_url}
                    alt={verifiedYT.title}
                    className="w-full h-44 object-cover"
                  />
                  <div className="absolute top-2 left-2 bg-black/80 backdrop-blur text-[10px] font-bold px-2 py-0.5 rounded border border-zinc-700 text-zinc-200">
                    {verifiedYT.is_live ? "LIVE STREAM" : "RECORDED VIDEO"}
                  </div>
                  <div className="absolute bottom-2 right-2 bg-black/80 backdrop-blur text-xs font-mono font-bold px-2 py-0.5 rounded text-zinc-200">
                    {formatSeconds(verifiedYT.duration_seconds)}
                  </div>
                </div>

                {/* Metadata Details */}
                <div className="md:col-span-7 space-y-3">
                  <div>
                    <h3 className="text-base font-bold text-zinc-100 leading-snug">
                      {verifiedYT.title}
                    </h3>
                    <div className="flex items-center gap-3 text-xs text-zinc-400 mt-1">
                      <span className="flex items-center gap-1 text-zinc-300 font-medium">
                        <User className="w-3.5 h-3.5 text-rose-400" />
                        {verifiedYT.channel_title}
                      </span>
                      <span>&bull;</span>
                      <a
                        href={verifiedYT.youtube_url}
                        target="_blank"
                        rel="noreferrer"
                        className="text-rose-400 hover:underline flex items-center gap-1"
                      >
                        YouTube Link <ExternalLink className="w-3 h-3" />
                      </a>
                    </div>
                  </div>

                  {/* Access Status Pill */}
                  <div className="text-xs p-3 rounded-xl bg-zinc-900 border border-zinc-800 flex items-start gap-2.5">
                    {verifiedYT.media_access_status === "AVAILABLE" ? (
                      <CheckCircle2 className="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5" />
                    ) : (
                      <AlertCircle className="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5" />
                    )}
                    <div>
                      <div className="font-semibold text-zinc-200">
                        Media Stream: <span className={verifiedYT.media_access_status === "AVAILABLE" ? "text-emerald-400" : "text-amber-400"}>{verifiedYT.media_access_status}</span>
                      </div>
                      <div className="text-[11px] text-zinc-400 mt-0.5">
                        {verifiedYT.access_message}
                      </div>
                    </div>
                  </div>

                  {/* For Long Streams: Section Selector */}
                  {verifiedYT.duration_seconds && verifiedYT.duration_seconds > 600 && (
                    <div className="flex items-center justify-between text-xs bg-zinc-900/60 px-3 py-2 rounded-xl border border-zinc-800">
                      <span className="text-zinc-400">Stream Processing Slice:</span>
                      <select
                        value={clipDurationLimit}
                        onChange={(e) => setClipDurationLimit(Number(e.target.value))}
                        disabled={isIngestingYT}
                        className="bg-zinc-950 border border-zinc-700 text-zinc-200 text-xs rounded-lg px-2 py-1 focus:outline-none"
                      >
                        <option value={300}>First 5 Minutes (Fastest - Recommended)</option>
                        <option value={600}>First 10 Minutes</option>
                        <option value={1200}>First 20 Minutes</option>
                        <option value={0}>Full Video (High Bandwidth)</option>
                      </select>
                    </div>
                  )}

                  {/* Confirmation Actions */}
                  <div className="pt-1 flex items-center gap-3">
                    <button
                      onClick={handleConfirmYouTube}
                      disabled={isIngestingYT || verifiedYT.media_access_status === "RESTRICTED"}
                      className="flex-1 py-3 px-4 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-semibold text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-950/40 transition disabled:opacity-50"
                    >
                      {isIngestingYT ? (
                        <>
                          <Loader2 className="w-4 h-4 animate-spin" />
                          <span>Ingesting Stream Media for {verifiedYT.youtube_id}...</span>
                        </>
                      ) : (
                        <>
                          <CheckCircle2 className="w-4 h-4" />
                          <span>Confirm Source Video & Ingest Media</span>
                        </>
                      )}
                    </button>

                    <button
                      onClick={() => setVerifiedYT(null)}
                      disabled={isIngestingYT}
                      className="px-4 py-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-700 text-xs font-semibold text-zinc-300 transition"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* TAB 2: LOCAL AUTHORIZED FILE UPLOAD */}
      {activeTab === "upload" && (
        <div className="space-y-4">
          <input
            type="file"
            ref={fileInputRef}
            onChange={(e) => e.target.files?.[0] && handleFileSelect(e.target.files[0])}
            accept=".mp4,.mov,.mkv,.webm"
            className="hidden"
          />

          {!selectedFile ? (
            <div
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
              onClick={() => fileInputRef.current?.click()}
              className={`border-2 border-dashed rounded-2xl p-10 text-center cursor-pointer transition-all ${
                isDragging
                  ? "border-rose-500 bg-rose-950/10 scale-[1.01]"
                  : "border-zinc-700/80 hover:border-zinc-500 bg-zinc-950/50 hover:bg-zinc-950/80"
              }`}
            >
              <div className="w-14 h-14 rounded-2xl bg-zinc-800/80 flex items-center justify-center mx-auto mb-4 border border-zinc-700 shadow-inner">
                <FileVideo className="w-7 h-7 text-rose-400" />
              </div>
              <h4 className="text-base font-semibold text-zinc-100">
                Drop your authorized gaming video here
              </h4>
              <p className="text-xs text-zinc-400 mt-1 max-w-md mx-auto">
                Supports MP4, MOV, MKV up to 500MB. Video streams are automatically inspected with FFprobe.
              </p>
              <button
                type="button"
                className="mt-5 px-4 py-2 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-xs font-semibold text-zinc-200 border border-zinc-600 transition"
              >
                Browse Files
              </button>
            </div>
          ) : (
            <div className="bg-zinc-950/70 border border-zinc-800 rounded-2xl p-5 space-y-4">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center">
                    <FileVideo className="w-5 h-5 text-rose-400" />
                  </div>
                  <div>
                    <div className="text-sm font-semibold text-zinc-100 truncate max-w-sm sm:max-w-md">
                      {selectedFile.name}
                    </div>
                    <div className="text-xs text-zinc-400">
                      {(selectedFile.size / (1024 * 1024)).toFixed(2)} MB
                    </div>
                  </div>
                </div>

                <button
                  onClick={() => setSelectedFile(null)}
                  disabled={isUploading}
                  className="text-xs text-zinc-400 hover:text-zinc-200 underline"
                >
                  Change
                </button>
              </div>

              <div>
                <label className="block text-xs font-medium text-zinc-400 mb-1">
                  Video Label / Title (Optional)
                </label>
                <input
                  type="text"
                  value={customTitle}
                  onChange={(e) => setCustomTitle(e.target.value)}
                  placeholder="e.g. GTA 5 Heist Escape Fail"
                  disabled={isUploading}
                  className="w-full px-3.5 py-2.5 rounded-xl bg-zinc-900 border border-zinc-700 text-sm text-zinc-100 placeholder-zinc-500 focus:outline-none focus:border-rose-500 transition"
                />
              </div>

              {isUploading && (
                <div className="space-y-1.5 pt-2">
                  <div className="flex justify-between text-xs text-zinc-400 font-medium">
                    <span>Uploading & inspecting media streams...</span>
                    <span>{uploadProgress}%</span>
                  </div>
                  <div className="w-full bg-zinc-800 rounded-full h-2 overflow-hidden">
                    <div
                      className="bg-gradient-to-r from-rose-500 to-amber-500 h-2 rounded-full transition-all duration-300"
                      style={{ width: `${uploadProgress}%` }}
                    />
                  </div>
                </div>
              )}

              <div className="pt-2">
                <button
                  onClick={handleUploadSubmit}
                  disabled={isUploading}
                  className="w-full py-3 rounded-xl bg-gradient-to-r from-rose-600 to-amber-600 hover:from-rose-500 hover:to-amber-500 text-white font-semibold text-sm flex items-center justify-center gap-2 shadow-lg shadow-rose-900/30 transition disabled:opacity-50"
                >
                  {isUploading ? (
                    <>
                      <Loader2 className="w-4 h-4 animate-spin" />
                      <span>Ingesting & Analyzing Media...</span>
                    </>
                  ) : (
                    <>
                      <span>Upload & Verify Stream Properties</span>
                      <ArrowRight className="w-4 h-4" />
                    </>
                  )}
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};
