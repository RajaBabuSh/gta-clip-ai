"use client";

import React, { useState } from "react";
import Link from "next/link";
import {
  Sparkles,
  Video,
  Play,
  ArrowRight,
  Check,
  ChevronDown,
  ChevronUp,
  Cpu,
  Layers,
  Zap,
  Globe,
  Share2,
  ShieldCheck,
  Star,
  Film,
  TrendingUp,
  Flame,
  Volume2
} from "lucide-react";
import { useAuth } from "@/context/AuthContext";

export default function LandingPage() {
  const { user } = useAuth();
  const [activeFaq, setActiveFaq] = useState<number | null>(null);
  const [billingPeriod, setBillingPeriod] = useState<"monthly" | "yearly">("monthly");
  const [demoAspect, setDemoAspect] = useState<"9:16" | "16:9" | "1:1">("9:16");
  const [demoSubtitle, setDemoSubtitle] = useState<"mrbeast" | "neon" | "clean">("mrbeast");

  const faqs = [
    {
      q: "How does GTA Clip AI detect viral gaming moments?",
      a: "GTA Clip AI combines audio intensity analysis (screams, laughter, gunfire, explosions) with deep speech transcription via Whisper. Our virality model scores energetic dialogue, clutch plays, and comedic peaks to slice optimal 30-to-60 second clips."
    },
    {
      q: "Can I upload full multi-hour streams from YouTube or Twitch?",
      a: "Yes! You can upload raw MP4/MKV video files up to 500MB directly, or paste a YouTube stream link. Our pipeline extracts the audio track in seconds and mines viral chapters asynchronously."
    },
    {
      q: "What subtitle styles are supported?",
      a: "We provide high-engagement word-level karaoke animated subtitles including MrBeast style (bold yellow with active word scale), Cyberpunk Neon, and Clean Minimal, all rendered in crisp 1080x1920 vertical video."
    },
    {
      q: "Does GTA Clip AI automatically reframe horizontal gameplay into 9:16 vertical?",
      a: "Yes! Our smart auto-reframer analyzes the 16:9 gameplay frame, centers the key action, and automatically generates vertical splits suitable for TikTok, YouTube Shorts, and Instagram Reels."
    },
    {
      q: "Is there a free tier available?",
      a: "Yes. Every registered user receives 50 free AI clips every month with full access to karaoke subtitles, timeline trimmer, and standard exports."
    },
    {
      q: "Can I connect my YouTube channel for direct upload?",
      a: "Yes. Using official Google OAuth 2.0 with YouTube Data API v3, you can connect your channel and publish generated shorts directly as Private, Unlisted, or Public videos."
    }
  ];

  return (
    <div className="min-h-screen bg-[#07050f] text-zinc-100 selection:bg-purple-600 selection:text-white">
      {/* Dynamic Background Glows */}
      <div className="fixed top-0 left-1/2 -translate-x-1/2 w-[1000px] h-[500px] bg-gradient-to-b from-purple-700/20 via-indigo-600/10 to-transparent blur-[160px] pointer-events-none -z-10" />
      <div className="fixed bottom-0 right-0 w-[600px] h-[400px] bg-blue-600/10 blur-[150px] pointer-events-none -z-10" />

      {/* 1. TOP NAVBAR */}
      <nav className="sticky top-0 z-50 bg-[#07050f]/80 backdrop-blur-xl border-b border-white/10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
          <Link href="/" className="flex items-center gap-3 group">
            <div className="h-11 w-11 rounded-2xl bg-gradient-to-tr from-purple-600 via-indigo-600 to-blue-500 p-0.5 shadow-lg shadow-purple-900/40 group-hover:scale-105 transition-transform duration-300">
              <div className="w-full h-full bg-[#0d0b1a] rounded-[14px] flex items-center justify-center">
                <Video className="w-5 h-5 text-purple-400" />
              </div>
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className="font-extrabold text-2xl tracking-tight text-white">
                  GTA CLIP <span className="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-indigo-300 to-blue-400">AI</span>
                </span>
                <span className="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300 border border-purple-500/30">
                  SaaS 2.0
                </span>
              </div>
              <p className="text-[11px] text-zinc-400 -mt-0.5">Viral Shorts & Stream Highlights Engine</p>
            </div>
          </Link>

          {/* Desktop Navigation Links */}
          <div className="hidden md:flex items-center gap-8 text-sm font-medium text-zinc-300">
            <a href="#demo" className="hover:text-white transition-colors">Interactive Demo</a>
            <a href="#features" className="hover:text-white transition-colors">Features</a>
            <a href="#workflow" className="hover:text-white transition-colors">Workflow</a>
            <a href="#pricing" className="hover:text-white transition-colors">Pricing</a>
            <a href="#faq" className="hover:text-white transition-colors">FAQ</a>
          </div>

          {/* Actions */}
          <div className="flex items-center gap-3">
            {user ? (
              <Link
                href="/dashboard"
                className="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 shadow-lg shadow-purple-600/30 transition-all duration-200"
              >
                Creator Studio <ArrowRight className="w-4 h-4" />
              </Link>
            ) : (
              <>
                <Link
                  href="/login"
                  className="px-4 py-2 text-sm font-semibold text-zinc-300 hover:text-white transition-colors"
                >
                  Sign In
                </Link>
                <Link
                  href="/register"
                  className="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 shadow-lg shadow-purple-600/30 transition-all duration-200"
                >
                  Get Started Free <ArrowRight className="w-4 h-4" />
                </Link>
              </>
            )}
          </div>
        </div>
      </nav>

      {/* 2. HERO SECTION */}
      <section className="relative pt-24 pb-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto text-center">
        <div className="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/30 text-purple-300 text-xs font-semibold mb-8 backdrop-blur-md">
          <Sparkles className="w-3.5 h-3.5 text-purple-400 animate-pulse" />
          <span>Next-Gen NVIDIA NVENC & Whisper Accelerated AI Video Mining</span>
        </div>

        <h1 className="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight text-white max-w-5xl mx-auto leading-[1.1]">
          Turn Long Gaming Streams Into{" "}
          <span className="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-indigo-300 to-blue-400">
            Viral 9:16 Shorts
          </span>{" "}
          In Minutes.
        </h1>

        <p className="mt-6 text-lg sm:text-xl text-zinc-400 max-w-3xl mx-auto leading-relaxed">
          Stop scrubbing through 4-hour GTA V and gaming VODs manually. Our AI detects your peak clutches, funny fails, and hype dialogue—slicing them into vertical shorts with dynamic karaoke subtitles.
        </p>

        {/* CTA Buttons */}
        <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
          <Link
            href="/register"
            className="w-full sm:w-auto flex items-center justify-center gap-2.5 px-8 py-4 rounded-xl text-base font-bold text-white bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 shadow-xl shadow-purple-600/30 hover:scale-[1.02] transition-all duration-200"
          >
            Start Free Now (50 Free Clips) <ArrowRight className="w-5 h-5" />
          </Link>
          <a
            href="#demo"
            className="w-full sm:w-auto flex items-center justify-center gap-2.5 px-8 py-4 rounded-xl text-base font-semibold text-zinc-200 bg-white/5 hover:bg-white/10 border border-white/10 backdrop-blur-md transition-all duration-200"
          >
            <Play className="w-4 h-4 fill-white" /> Watch Interactive Demo
          </a>
        </div>

        {/* Social Proof / Stats Bar */}
        <div className="mt-16 pt-10 border-t border-white/10 grid grid-cols-2 md:grid-cols-4 gap-8 max-w-4xl mx-auto">
          <div>
            <div className="text-3xl font-extrabold text-white">100,000+</div>
            <div className="text-xs text-zinc-400 mt-1 uppercase tracking-wider font-semibold">Clips Generated</div>
          </div>
          <div>
            <div className="text-3xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-indigo-300">
              98%
            </div>
            <div className="text-xs text-zinc-400 mt-1 uppercase tracking-wider font-semibold">Virality Accuracy</div>
          </div>
          <div>
            <div className="text-3xl font-extrabold text-white">10x</div>
            <div className="text-xs text-zinc-400 mt-1 uppercase tracking-wider font-semibold">Faster Than Editing</div>
          </div>
          <div>
            <div className="text-3xl font-extrabold text-emerald-400">4 Platforms</div>
            <div className="text-xs text-zinc-400 mt-1 uppercase tracking-wider font-semibold">TikTok • Shorts • Reels</div>
          </div>
        </div>
      </section>

      {/* 3. INTERACTIVE PRODUCT DEMO */}
      <section id="demo" className="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div className="text-center mb-12">
          <span className="text-xs font-bold text-purple-400 uppercase tracking-widest px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/20">
            Interactive AI Studio Demo
          </span>
          <h2 className="text-3xl sm:text-5xl font-extrabold text-white mt-4">
            See How AI Auto-Reframes & Subtitles
          </h2>
          <p className="text-zinc-400 text-base max-w-2xl mx-auto mt-3">
            Switch aspect ratios and subtitle themes in real-time to preview how our rendering engine processes gaming clips.
          </p>
        </div>

        <div className="bg-[#0f0c1e]/90 border border-white/15 rounded-3xl p-6 sm:p-8 shadow-2xl shadow-purple-950/50 backdrop-blur-2xl">
          <div className="flex flex-wrap items-center justify-between gap-4 pb-6 border-b border-white/10 mb-8">
            <div className="flex items-center gap-3">
              <span className="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Aspect Ratio:</span>
              <div className="flex bg-black/40 p-1 rounded-xl border border-white/10">
                {(["9:16", "16:9", "1:1"] as const).map((ratio) => (
                  <button
                    key={ratio}
                    onClick={() => setDemoAspect(ratio)}
                    className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                      demoAspect === ratio
                        ? "bg-purple-600 text-white shadow-md shadow-purple-600/30"
                        : "text-zinc-400 hover:text-white"
                    }`}
                  >
                    {ratio} {ratio === "9:16" ? "(Shorts)" : ratio === "16:9" ? "(VOD)" : "(Feed)"}
                  </button>
                ))}
              </div>
            </div>

            <div className="flex items-center gap-3">
              <span className="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Subtitle Theme:</span>
              <div className="flex bg-black/40 p-1 rounded-xl border border-white/10">
                {[
                  { id: "mrbeast", label: "🔥 MrBeast" },
                  { id: "neon", label: "⚡ Cyber Neon" },
                  { id: "clean", label: "✨ Minimal" }
                ].map((st) => (
                  <button
                    key={st.id}
                    onClick={() => setDemoSubtitle(st.id as any)}
                    className={`px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                      demoSubtitle === st.id
                        ? "bg-indigo-600 text-white shadow-md shadow-indigo-600/30"
                        : "text-zinc-400 hover:text-white"
                    }`}
                  >
                    {st.label}
                  </button>
                ))}
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            <div className="lg:col-span-6 flex justify-center">
              <div
                className={`relative bg-black rounded-2xl overflow-hidden border border-purple-500/30 shadow-2xl shadow-purple-900/40 transition-all duration-300 flex flex-col justify-between ${
                  demoAspect === "9:16"
                    ? "w-[280px] h-[500px]"
                    : demoAspect === "16:9"
                    ? "w-[480px] h-[270px]"
                    : "w-[360px] h-[360px]"
                }`}
              >
                <div className="absolute inset-0 bg-gradient-to-b from-purple-900/40 via-zinc-900/80 to-black z-0" />
                <div className="absolute inset-0 flex items-center justify-center opacity-30">
                  <Film className="w-24 h-24 text-purple-400 animate-pulse" />
                </div>

                <div className="relative z-10 p-3 flex justify-between items-center">
                  <div className="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-black/60 border border-white/15 text-[11px] font-bold text-amber-400 backdrop-blur-md">
                    <Flame className="w-3.5 h-3.5 fill-amber-400" /> 98/100 Virality
                  </div>
                  <div className="text-[10px] font-semibold text-zinc-400 bg-black/60 px-2 py-0.5 rounded backdrop-blur">
                    00:42 / 00:58
                  </div>
                </div>

                <div className="relative z-10 p-4 text-center">
                  {demoSubtitle === "mrbeast" && (
                    <div className="inline-block bg-black/70 px-3 py-1.5 rounded-lg border border-yellow-400/40">
                      <span className="text-xl font-black uppercase text-yellow-300 tracking-wider drop-shadow-[0_2px_10px_rgba(234,179,8,0.8)] scale-110 inline-block animate-bounce">
                        NO WAY HE HIT THAT! 🎯
                      </span>
                    </div>
                  )}
                  {demoSubtitle === "neon" && (
                    <div className="inline-block bg-black/80 px-3 py-1.5 rounded-lg border border-cyan-400/40">
                      <span className="text-xl font-black uppercase text-cyan-400 tracking-wider drop-shadow-[0_0_15px_rgba(34,211,238,0.9)]">
                        ⚡ INSANE 1v4 CLUTCH ⚡
                      </span>
                    </div>
                  )}
                  {demoSubtitle === "clean" && (
                    <div className="inline-block bg-white/90 px-3 py-1 rounded-md text-black font-extrabold text-sm tracking-tight">
                      That was completely calculated.
                    </div>
                  )}
                </div>

                <div className="relative z-10 p-3 bg-gradient-to-t from-black via-black/80 to-transparent">
                  <div className="w-full bg-white/20 h-1.5 rounded-full overflow-hidden mb-2">
                    <div className="bg-purple-500 h-full w-[65%]" />
                  </div>
                  <div className="flex items-center justify-between text-[11px] text-zinc-300">
                    <span className="font-semibold flex items-center gap-1">
                      <Volume2 className="w-3 h-3 text-emerald-400" /> Loudness Peak +8.2dB
                    </span>
                    <span className="text-purple-400 font-bold">Whisper AI Sync</span>
                  </div>
                </div>
              </div>
            </div>

            <div className="lg:col-span-6 space-y-5">
              <div className="p-4 rounded-2xl bg-white/5 border border-white/10">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-bold text-zinc-400 uppercase">Detection Trigger</span>
                  <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300">HIGH ENGAGEMENT</span>
                </div>
                <h4 className="text-base font-bold text-white">Audio Spike + Emotion Velocity</h4>
                <p className="text-xs text-zinc-400 mt-1">
                  Detected sudden vocal volume shift at 00:14:22 coupled with rapid game audio activity. Confidence: 96.4%.
                </p>
              </div>

              <div className="p-4 rounded-2xl bg-white/5 border border-white/10">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-bold text-zinc-400 uppercase">Whisper Transcript</span>
                  <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300">WORD-LEVEL SYNC</span>
                </div>
                <p className="text-xs text-zinc-300 font-mono italic">
                  &quot;Wait wait wait, look at his angle right now... NO WAY HE HIT THAT SHOT! Let&apos;s go!&quot;
                </p>
              </div>

              <div className="p-4 rounded-2xl bg-white/5 border border-white/10">
                <div className="flex items-center justify-between mb-2">
                  <span className="text-xs font-bold text-zinc-400 uppercase">Generated Metadata</span>
                  <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/20 text-blue-300">VIRAL SEO</span>
                </div>
                <div className="text-xs font-semibold text-white">Title: When the 1% shot actually hits... 😱 #GTAV #GamingShorts</div>
                <div className="text-[11px] text-zinc-400 mt-1 flex gap-2">
                  <span>#gaming</span>
                  <span>#gtaclips</span>
                  <span>#shorts</span>
                  <span>#viral</span>
                </div>
              </div>

              <div className="pt-2">
                <Link
                  href="/register"
                  className="w-full flex items-center justify-center gap-2 py-3 px-6 rounded-xl font-bold text-sm text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 shadow-lg shadow-purple-600/30 transition-all"
                >
                  Create Clips Like This For Free <ArrowRight className="w-4 h-4" />
                </Link>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 4. FEATURES SECTION */}
      <section id="features" className="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div className="text-center mb-16">
          <span className="text-xs font-bold text-indigo-400 uppercase tracking-widest px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20">
            Engineered For Creators & Streamers
          </span>
          <h2 className="text-3xl sm:text-5xl font-extrabold text-white mt-4">
            Everything You Need To Grow 10x Faster
          </h2>
          <p className="text-zinc-400 text-base max-w-2xl mx-auto mt-3">
            Designed specifically for creators turning high-energy gameplay into short-form growth machines.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div className="p-8 rounded-3xl bg-[#0f0c1e]/70 border border-white/10 hover:border-purple-500/40 transition-all duration-300 group">
            <div className="w-12 h-12 rounded-2xl bg-purple-600/20 border border-purple-500/30 flex items-center justify-center text-purple-400 mb-6 group-hover:scale-110 transition-transform">
              <Zap className="w-6 h-6" />
            </div>
            <h3 className="text-xl font-bold text-white mb-2">Automated Peak Detection</h3>
            <p className="text-sm text-zinc-400 leading-relaxed">
              Our acoustic model analyzes voice energy, pitch, screaming, laughter, and high-tempo game moments to isolate viral clips without manual scrubbing.
            </p>
          </div>

          <div className="p-8 rounded-3xl bg-[#0f0c1e]/70 border border-white/10 hover:border-indigo-500/40 transition-all duration-300 group">
            <div className="w-12 h-12 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 mb-6 group-hover:scale-110 transition-transform">
              <Sparkles className="w-6 h-6" />
            </div>
            <h3 className="text-xl font-bold text-white mb-2">Whisper Karaoke Subtitles</h3>
            <p className="text-sm text-zinc-400 leading-relaxed">
              Word-level timestamped captions with active karaoke animations. Retain 80% more viewers on TikTok, Reels, and YouTube Shorts where audio is muted.
            </p>
          </div>

          <div className="p-8 rounded-3xl bg-[#0f0c1e]/70 border border-white/10 hover:border-blue-500/40 transition-all duration-300 group">
            <div className="w-12 h-12 rounded-2xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-blue-400 mb-6 group-hover:scale-110 transition-transform">
              <Layers className="w-6 h-6" />
            </div>
            <h3 className="text-xl font-bold text-white mb-2">Smart 9:16 Auto-Framing</h3>
            <p className="text-sm text-zinc-400 leading-relaxed">
              Instant vertical reframing that tracks the action, keeps your gameplay centered, and stacks webcam over game frames automatically.
            </p>
          </div>

          <div className="p-8 rounded-3xl bg-[#0f0c1e]/70 border border-white/10 hover:border-purple-500/40 transition-all duration-300 group">
            <div className="w-12 h-12 rounded-2xl bg-purple-600/20 border border-purple-500/30 flex items-center justify-center text-purple-400 mb-6 group-hover:scale-110 transition-transform">
              <TrendingUp className="w-6 h-6" />
            </div>
            <h3 className="text-xl font-bold text-white mb-2">Virality Scoring Matrix</h3>
            <p className="text-sm text-zinc-400 leading-relaxed">
              Every clip is scored from 0 to 100 based on hook strength, audio pacing, topic retention, and engagement potential so you publish winners first.
            </p>
          </div>

          <div className="p-8 rounded-3xl bg-[#0f0c1e]/70 border border-white/10 hover:border-indigo-500/40 transition-all duration-300 group">
            <div className="w-12 h-12 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 mb-6 group-hover:scale-110 transition-transform">
              <Share2 className="w-6 h-6" />
            </div>
            <h3 className="text-xl font-bold text-white mb-2">Official YouTube Data API v3</h3>
            <p className="text-sm text-zinc-400 leading-relaxed">
              Connect your YouTube channel via Google OAuth 2.0. Export generated clips directly into YouTube Shorts with scheduled publication and privacy controls.
            </p>
          </div>

          <div className="p-8 rounded-3xl bg-[#0f0c1e]/70 border border-white/10 hover:border-blue-500/40 transition-all duration-300 group">
            <div className="w-12 h-12 rounded-2xl bg-blue-600/20 border border-blue-500/30 flex items-center justify-center text-blue-400 mb-6 group-hover:scale-110 transition-transform">
              <Cpu className="w-6 h-6" />
            </div>
            <h3 className="text-xl font-bold text-white mb-2">GPU Acceleration Pipeline</h3>
            <p className="text-sm text-zinc-400 leading-relaxed">
              Powered by NVIDIA NVENC hardware video encoding and decoupled Python AI microservices. Transcode 1080p vertical video at up to 120 FPS.
            </p>
          </div>
        </div>
      </section>

      {/* 5. WORKFLOW STEP-BY-STEP */}
      <section id="workflow" className="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-white/10">
        <div className="text-center mb-16">
          <span className="text-xs font-bold text-purple-400 uppercase tracking-widest px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/20">
            How It Works
          </span>
          <h2 className="text-3xl sm:text-5xl font-extrabold text-white mt-4">
            From 4-Hour Stream To Viral Shorts In 4 Steps
          </h2>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
          {[
            { step: "01", title: "Upload Footage", desc: "Drag and drop your raw MP4/MKV video or paste your stream URL into the ingestion studio." },
            { step: "02", title: "AI Moment Mining", desc: "Whisper transcribes speech while our acoustic engine scans volume peaks and hype moments." },
            { step: "03", title: "Customize & Reframe", desc: "Preview your 9:16 clip, adjust subtitle styling, and fine-tune start and end timestamps." },
            { step: "04", title: "Export & Go Viral", desc: "Download high-bitrate MP4s or schedule auto-upload directly to your YouTube channel." }
          ].map((s) => (
            <div key={s.step} className="p-6 rounded-2xl bg-[#0f0c1e]/60 border border-white/10 relative">
              <div className="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-purple-500 to-indigo-400 mb-4 font-mono">
                {s.step}
              </div>
              <h4 className="text-lg font-bold text-white mb-2">{s.title}</h4>
              <p className="text-xs text-zinc-400 leading-relaxed">{s.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* 6. PRICING SECTION */}
      <section id="pricing" className="py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto border-t border-white/10">
        <div className="text-center mb-12">
          <span className="text-xs font-bold text-purple-400 uppercase tracking-widest px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/20">
            Simple Transparent Pricing
          </span>
          <h2 className="text-3xl sm:text-5xl font-extrabold text-white mt-4">
            Scale Your Content Without Breaking The Bank
          </h2>

          <div className="mt-6 flex items-center justify-center gap-3">
            <span className={`text-xs font-bold ${billingPeriod === "monthly" ? "text-white" : "text-zinc-500"}`}>Monthly</span>
            <button
              onClick={() => setBillingPeriod(billingPeriod === "monthly" ? "yearly" : "monthly")}
              className="w-12 h-6 rounded-full bg-purple-600/30 border border-purple-500/40 p-0.5 transition-colors relative"
            >
              <div
                className={`w-4 h-4 rounded-full bg-purple-400 transition-transform ${
                  billingPeriod === "yearly" ? "translate-x-6" : "translate-x-0"
                }`}
              />
            </button>
            <span className={`text-xs font-bold ${billingPeriod === "yearly" ? "text-white" : "text-zinc-500"}`}>
              Yearly <span className="text-emerald-400 font-extrabold">(Save 20%)</span>
            </span>
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          <div className="p-8 rounded-3xl bg-[#0f0c1e]/70 border border-white/10 flex flex-col justify-between">
            <div>
              <h3 className="text-lg font-bold text-white">Starter Creator</h3>
              <p className="text-xs text-zinc-400 mt-1">Perfect for casual streamers starting their shorts journey.</p>
              <div className="mt-6 mb-6">
                <span className="text-4xl font-extrabold text-white">$0</span>
                <span className="text-xs text-zinc-400"> / forever</span>
              </div>
              <ul className="space-y-3 text-xs text-zinc-300">
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-emerald-400" /> 50 AI Clips / month</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-emerald-400" /> 720p HD Exports</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-emerald-400" /> Standard Whisper Subtitles</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-emerald-400" /> 9:16 & 16:9 Aspect Ratios</li>
              </ul>
            </div>
            <Link
              href="/register"
              className="mt-8 block text-center py-3 px-4 rounded-xl text-sm font-bold text-white bg-white/10 hover:bg-white/20 transition-colors"
            >
              Get Started Free
            </Link>
          </div>

          <div className="p-8 rounded-3xl bg-gradient-to-b from-purple-950/60 to-[#0f0c1e] border-2 border-purple-500 shadow-2xl shadow-purple-900/40 relative flex flex-col justify-between">
            <div className="absolute -top-3.5 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-gradient-to-r from-purple-500 to-indigo-500 text-[10px] font-extrabold text-white uppercase tracking-wider shadow-md">
              MOST POPULAR
            </div>
            <div>
              <h3 className="text-lg font-bold text-white">Creator Pro</h3>
              <p className="text-xs text-purple-200 mt-1">For active gaming creators posting daily shorts across platforms.</p>
              <div className="mt-6 mb-6">
                <span className="text-4xl font-extrabold text-white">
                  ${billingPeriod === "monthly" ? "29" : "24"}
                </span>
                <span className="text-xs text-zinc-400"> / month</span>
              </div>
              <ul className="space-y-3 text-xs text-zinc-200">
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-purple-400 font-bold" /> 500 AI Clips / month</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-purple-400 font-bold" /> 1080p Full HD 60FPS Exports</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-purple-400 font-bold" /> MrBeast & Neon Animated Subtitles</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-purple-400 font-bold" /> Priority GPU Encoding Queue</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-purple-400 font-bold" /> Direct YouTube OAuth Auto-Publish</li>
              </ul>
            </div>
            <Link
              href="/register"
              className="mt-8 block text-center py-3.5 px-4 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-purple-600 via-indigo-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 shadow-lg shadow-purple-600/30 transition-all"
            >
              Start 7-Day Pro Trial
            </Link>
          </div>

          <div className="p-8 rounded-3xl bg-[#0f0c1e]/70 border border-white/10 flex flex-col justify-between">
            <div>
              <h3 className="text-lg font-bold text-white">Studio Agency</h3>
              <p className="text-xs text-zinc-400 mt-1">For esports teams, agencies, and full-time multi-streamers.</p>
              <div className="mt-6 mb-6">
                <span className="text-4xl font-extrabold text-white">
                  ${billingPeriod === "monthly" ? "99" : "79"}
                </span>
                <span className="text-xs text-zinc-400"> / month</span>
              </div>
              <ul className="space-y-3 text-xs text-zinc-300">
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-emerald-400" /> Unlimited AI Clips</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-emerald-400" /> 4K Ultra HD Exports</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-emerald-400" /> Custom Font & Watermark Upload</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-emerald-400" /> Multi-Seat Team Access</li>
                <li className="flex items-center gap-2.5"><Check className="w-4 h-4 text-emerald-400" /> Dedicated Cloud Worker Slot</li>
              </ul>
            </div>
            <Link
              href="/register"
              className="mt-8 block text-center py-3 px-4 rounded-xl text-sm font-bold text-white bg-white/10 hover:bg-white/20 transition-colors"
            >
              Contact Sales
            </Link>
          </div>
        </div>
      </section>

      {/* 7. FAQ SECTION */}
      <section id="faq" className="py-20 px-4 sm:px-6 lg:px-8 max-w-4xl mx-auto border-t border-white/10">
        <div className="text-center mb-12">
          <span className="text-xs font-bold text-indigo-400 uppercase tracking-widest px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20">
            Frequently Asked Questions
          </span>
          <h2 className="text-3xl sm:text-4xl font-extrabold text-white mt-4">
            Everything You Need To Know
          </h2>
        </div>

        <div className="space-y-4">
          {faqs.map((faq, idx) => (
            <div
              key={idx}
              className="rounded-2xl bg-[#0f0c1e]/70 border border-white/10 overflow-hidden transition-colors"
            >
              <button
                onClick={() => setActiveFaq(activeFaq === idx ? null : idx)}
                className="w-full py-4 px-6 flex items-center justify-between text-left text-sm sm:text-base font-bold text-white hover:text-purple-300 transition-colors cursor-pointer"
              >
                <span>{faq.q}</span>
                {activeFaq === idx ? (
                  <ChevronUp className="w-5 h-5 text-purple-400 flex-shrink-0" />
                ) : (
                  <ChevronDown className="w-5 h-5 text-zinc-500 flex-shrink-0" />
                )}
              </button>
              {activeFaq === idx && (
                <div className="px-6 pb-5 text-xs sm:text-sm text-zinc-400 leading-relaxed border-t border-white/5 pt-3">
                  {faq.a}
                </div>
              )}
            </div>
          ))}
        </div>
      </section>

      {/* 8. FOOTER */}
      <footer className="border-t border-white/10 bg-[#05030a] py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
          <div>
            <div className="flex items-center gap-2.5 mb-3">
              <div className="h-8 w-8 rounded-xl bg-purple-600 p-0.5">
                <div className="w-full h-full bg-[#0d0b1a] rounded-[10px] flex items-center justify-center">
                  <Video className="w-4 h-4 text-purple-400" />
                </div>
              </div>
              <span className="font-extrabold text-lg text-white">GTA CLIP AI</span>
            </div>
            <p className="text-xs text-zinc-400 leading-relaxed">
              High-performance automated video clipping, subtitle synthesis, and viral growth studio for gaming creators.
            </p>
          </div>

          <div>
            <h4 className="text-xs font-bold text-white uppercase tracking-wider mb-4">Product</h4>
            <ul className="space-y-2 text-xs text-zinc-400">
              <li><Link href="/dashboard" className="hover:text-white transition-colors">Creator Dashboard</Link></li>
              <li><Link href="/studio" className="hover:text-white transition-colors">AI Clipping Studio</Link></li>
              <li><Link href="/my-clips" className="hover:text-white transition-colors">My Clips Library</Link></li>
              <li><Link href="/pricing" className="hover:text-white transition-colors">Pricing Plans</Link></li>
            </ul>
          </div>

          <div>
            <h4 className="text-xs font-bold text-white uppercase tracking-wider mb-4">Administration</h4>
            <ul className="space-y-2 text-xs text-zinc-400">
              <li><Link href="/admin" className="hover:text-white transition-colors">Admin Dashboard</Link></li>
              <li><Link href="/admin/super" className="hover:text-white transition-colors">Super Admin Center</Link></li>
              <li><Link href="/login" className="hover:text-white transition-colors">Staff Login</Link></li>
              <li><Link href="/api/health" className="hover:text-white transition-colors">API Diagnostics</Link></li>
            </ul>
          </div>

          <div>
            <h4 className="text-xs font-bold text-white uppercase tracking-wider mb-4">System Status</h4>
            <div className="p-3 rounded-xl bg-white/5 border border-white/10 text-xs">
              <div className="flex items-center gap-2 text-emerald-400 font-bold mb-1">
                <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" /> All Systems Operational
              </div>
              <div className="text-[11px] text-zinc-400">
                GPU Worker: Online (NVIDIA NVENC)
              </div>
              <div className="text-[11px] text-zinc-400">
                Whisper AI: Online (FP16 Accelerated)
              </div>
            </div>
          </div>
        </div>

        <div className="max-w-7xl mx-auto pt-8 border-t border-white/5 flex flex-col sm:flex-row items-center justify-between text-xs text-zinc-500 gap-4">
          <div>© 2026 GTA Clip AI. All rights reserved. Built for professional gaming creators.</div>
          <div className="flex gap-6">
            <a href="#" className="hover:text-zinc-300">Privacy Policy</a>
            <a href="#" className="hover:text-zinc-300">Terms of Service</a>
            <a href="#" className="hover:text-zinc-300">Security Standards</a>
          </div>
        </div>
      </footer>
    </div>
  );
}
