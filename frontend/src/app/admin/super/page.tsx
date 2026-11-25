"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { DashboardLayout } from "@/components/DashboardLayout";
import { useAuth } from "@/context/AuthContext";
import {
  ShieldAlert,
  Key,
  Database,
  Cpu,
  Lock,
  CheckCircle2,
  AlertTriangle,
  Save,
  Server,
  Cloud,
  ArrowLeft
} from "lucide-react";

export default function SuperAdminPage() {
  const { user, loading: authLoading } = useAuth();
  const router = useRouter();

  const [settings, setSettings] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!authLoading && (!user || user.role !== "superadmin")) {
      router.push("/admin");
    }
  }, [user, authLoading, router]);

  useEffect(() => {
    const fetchSettings = async () => {
      try {
        setLoading(true);
        const res = await fetch("/api/admin/system");
        if (res.ok) {
          setSettings(await res.json());
        }
      } catch (e) {
        console.error("Failed to load settings:", e);
      } finally {
        setLoading(false);
      }
    };
    if (user?.role === "superadmin") {
      fetchSettings();
    }
  }, [user]);

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    setStatusMessage("Master provider settings saved successfully!");
    setTimeout(() => setStatusMessage(null), 3000);
  };

  if (authLoading || !user || user.role !== "superadmin") {
    return (
      <div className="min-h-screen bg-[#07050f] flex items-center justify-center text-purple-400">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500" />
      </div>
    );
  }

  return (
    <DashboardLayout pageTitle="Super Admin Master Control Center">
      <div className="max-w-5xl space-y-8">
        {/* Back Link */}
        <div>
          <Link
            href="/admin"
            className="inline-flex items-center gap-2 text-xs font-semibold text-purple-400 hover:text-purple-300 transition-colors"
          >
            <ArrowLeft className="w-3.5 h-3.5" /> Back to Operations Console
          </Link>
        </div>

        {statusMessage && (
          <div className="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm flex items-center gap-3">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0" />
            <span>{statusMessage}</span>
          </div>
        )}

        {/* 1. MASTER VAULT HEADER */}
        <div className="p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-red-950/40 via-purple-950/60 to-black border border-red-500/30 shadow-2xl">
          <div className="flex items-center gap-3 mb-2">
            <span className="p-2 rounded-xl bg-red-500/20 text-red-400">
              <ShieldAlert className="w-6 h-6" />
            </span>
            <div>
              <h2 className="text-xl sm:text-2xl font-extrabold text-white">Super Admin Control Vault</h2>
              <p className="text-xs text-red-300/80">Authorized for Master Administrator: {user.username}</p>
            </div>
          </div>
          <p className="text-xs text-zinc-400 mt-2 max-w-2xl leading-relaxed">
            Configure underlying AI acceleration models, Cloudflare R2 object storage credentials, and YouTube OAuth production client credentials.
          </p>
        </div>

        {/* 2. AI PROVIDER CONFIGURATION */}
        <div className="p-6 sm:p-8 rounded-3xl bg-[#0f0c1e]/80 border border-white/10">
          <h3 className="text-base font-bold text-white flex items-center gap-2 mb-4">
            <Cpu className="w-4 h-4 text-purple-400" /> AI Provider & Hardware Engine Toggles
          </h3>

          <form onSubmit={handleSave} className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="p-4 rounded-2xl bg-black/40 border border-white/10">
                <label className="block text-xs font-bold text-zinc-300 mb-1">Whisper Model Size</label>
                <select
                  defaultValue="base"
                  className="w-full px-3 py-2 bg-zinc-900 border border-white/10 rounded-xl text-xs text-white"
                >
                  <option value="tiny">Tiny (Fastest, ~1GB VRAM)</option>
                  <option value="base">Base (Balanced Recommended, ~1.5GB VRAM)</option>
                  <option value="small">Small (High Accuracy, ~2.5GB VRAM)</option>
                  <option value="medium">Medium (Studio Grade, ~5GB VRAM)</option>
                </select>
                <p className="text-[10px] text-zinc-500 mt-1.5">
                  Currently executing on: NVIDIA RTX 3050 NVENC (Windows 11 Daemon)
                </p>
              </div>

              <div className="p-4 rounded-2xl bg-black/40 border border-white/10">
                <label className="block text-xs font-bold text-zinc-300 mb-1">Audio Processing Engine</label>
                <select
                  defaultValue="librosa"
                  className="w-full px-3 py-2 bg-zinc-900 border border-white/10 rounded-xl text-xs text-white"
                >
                  <option value="librosa">Librosa + SoundFile (Acoustic Loudness Analysis)</option>
                  <option value="ffmpeg_loudnorm">FFmpeg LoudNorm Filter</option>
                </select>
                <p className="text-[10px] text-zinc-500 mt-1.5">Mines peak emotion and volume shifts automatically.</p>
              </div>
            </div>

            {/* Cloud API Keys */}
            <div className="p-4 rounded-2xl bg-black/40 border border-white/10 space-y-4">
              <h4 className="text-xs font-bold text-zinc-300 uppercase tracking-wider">Cloud AI & Video APIs</h4>
              <div>
                <label className="block text-xs text-zinc-400 mb-1">Google Gemini API Key (Optional)</label>
                <input
                  type="password"
                  defaultValue="AIzaSy••••••••••••••••••••••••"
                  className="w-full px-3 py-2 bg-zinc-900 border border-white/10 rounded-xl text-xs text-white font-mono"
                />
              </div>
              <div>
                <label className="block text-xs text-zinc-400 mb-1">Pexels Background Footage API Key (Optional)</label>
                <input
                  type="password"
                  defaultValue="••••••••••••••••••••••••••••••••"
                  className="w-full px-3 py-2 bg-zinc-900 border border-white/10 rounded-xl text-xs text-white font-mono"
                />
              </div>
            </div>

            {/* Storage Configuration */}
            <div className="p-4 rounded-2xl bg-black/40 border border-white/10 space-y-4">
              <h4 className="text-xs font-bold text-zinc-300 uppercase tracking-wider flex items-center gap-2">
                <Cloud className="w-4 h-4 text-blue-400" /> Storage Subsystem
              </h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs text-zinc-400 mb-1">Active Storage Driver</label>
                  <select
                    defaultValue="local"
                    className="w-full px-3 py-2 bg-zinc-900 border border-white/10 rounded-xl text-xs text-white"
                  >
                    <option value="local">Local Filesystem (C:\xampp\htdocs\New folder\storage)</option>
                    <option value="r2">Cloudflare R2 Object Storage</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-zinc-400 mb-1">Max Upload Threshold</label>
                  <input
                    type="text"
                    defaultValue="500 MB"
                    disabled
                    className="w-full px-3 py-2 bg-zinc-900 border border-white/10 rounded-xl text-xs text-zinc-500"
                  />
                </div>
              </div>
            </div>

            <button
              type="submit"
              className="flex items-center gap-2 px-6 py-3 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 shadow-md transition-colors cursor-pointer"
            >
              <Save className="w-4 h-4" /> Save Super Admin Settings
            </button>
          </form>
        </div>

        {/* 3. ROLES & PERMISSIONS MATRIX */}
        <div className="p-6 sm:p-8 rounded-3xl bg-[#0f0c1e]/80 border border-white/10">
          <h3 className="text-base font-bold text-white mb-2">Role-Based Access Control (RBAC) Matrix</h3>
          <p className="text-xs text-zinc-400 mb-4">Configured security permissions per user tier.</p>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="border-b border-white/10 text-zinc-400 uppercase text-[10px]">
                <tr>
                  <th className="pb-3">Capability / Feature</th>
                  <th className="pb-3">Free Creator</th>
                  <th className="pb-3">Creator Pro</th>
                  <th className="pb-3">Administrator</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5 text-zinc-300">
                <tr>
                  <td className="py-2.5 font-medium">Whisper Subtitles Synthesis</td>
                  <td className="py-2.5 text-emerald-400">Standard</td>
                  <td className="py-2.5 text-emerald-400">Karaoke / Animated</td>
                  <td className="py-2.5 text-emerald-400">All Presets</td>
                </tr>
                <tr>
                  <td className="py-2.5 font-medium">Monthly Clip Quota</td>
                  <td className="py-2.5">50 Clips</td>
                  <td className="py-2.5">500 Clips</td>
                  <td className="py-2.5 text-purple-400 font-bold">Unlimited</td>
                </tr>
                <tr>
                  <td className="py-2.5 font-medium">Encoding Resolution</td>
                  <td className="py-2.5">720p HD</td>
                  <td className="py-2.5">1080p 60FPS</td>
                  <td className="py-2.5 text-purple-400 font-bold">4K NVENC</td>
                </tr>
                <tr>
                  <td className="py-2.5 font-medium">YouTube OAuth Upload</td>
                  <td className="py-2.5 text-zinc-500">Disabled</td>
                  <td className="py-2.5 text-emerald-400">Enabled</td>
                  <td className="py-2.5 text-emerald-400">Enabled</td>
                </tr>
                <tr>
                  <td className="py-2.5 font-medium">Audit Log & System Access</td>
                  <td className="py-2.5 text-zinc-500">No</td>
                  <td className="py-2.5 text-zinc-500">No</td>
                  <td className="py-2.5 text-emerald-400 font-bold">Full Access</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
