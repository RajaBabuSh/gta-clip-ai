"use client";

import React, { useState } from "react";
import { DashboardLayout } from "@/components/DashboardLayout";
import { useAuth } from "@/context/AuthContext";
import { User, Mail, Lock, ShieldCheck, Share2, Video, CheckCircle2, AlertCircle, Save } from "lucide-react";

export default function SettingsPage() {
  const { user } = useAuth();
  const [fullName, setFullName] = useState(user?.full_name || "");
  const [email, setEmail] = useState(user?.email || "");
  const [password, setPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [statusMessage, setStatusMessage] = useState<string | null>(null);

  const handleSaveProfile = (e: React.FormEvent) => {
    e.preventDefault();
    setStatusMessage("Profile settings updated successfully!");
    setTimeout(() => setStatusMessage(null), 3000);
  };

  return (
    <DashboardLayout pageTitle="Account & Platform Settings">
      <div className="max-w-4xl space-y-8">
        {statusMessage && (
          <div className="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm flex items-center gap-3">
            <CheckCircle2 className="w-5 h-5 flex-shrink-0" />
            <span>{statusMessage}</span>
          </div>
        )}

        {/* 1. Profile Information */}
        <div className="p-6 sm:p-8 rounded-3xl bg-[#0f0c1e]/80 border border-white/10">
          <h3 className="text-lg font-bold text-white mb-1">Creator Profile</h3>
          <p className="text-xs text-zinc-400 mb-6">Manage your username, email, and display details.</p>

          <form onSubmit={handleSaveProfile} className="space-y-4 max-w-lg">
            <div>
              <label className="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">Username</label>
              <input
                type="text"
                disabled
                value={user?.username || ""}
                className="w-full px-4 py-2.5 bg-black/40 border border-white/10 rounded-xl text-zinc-400 text-sm cursor-not-allowed"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">Full Name</label>
              <input
                type="text"
                value={fullName}
                onChange={(e) => setFullName(e.target.value)}
                placeholder="Raja Babu"
                className="w-full px-4 py-2.5 bg-black/40 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">Email Address</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="w-full px-4 py-2.5 bg-black/40 border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-purple-500"
              />
            </div>

            <div className="pt-2">
              <button
                type="submit"
                className="flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-purple-600 hover:bg-purple-500 shadow-md transition-colors"
              >
                <Save className="w-3.5 h-3.5" /> Save Profile
              </button>
            </div>
          </form>
        </div>

        {/* 2. YouTube Channel Connection */}
        <div className="p-6 sm:p-8 rounded-3xl bg-[#0f0c1e]/80 border border-white/10">
          <div className="flex items-center justify-between mb-4">
            <div>
              <h3 className="text-lg font-bold text-white flex items-center gap-2">
                <Video className="w-5 h-5 text-red-500" /> YouTube Studio OAuth 2.0 Integration
              </h3>
              <p className="text-xs text-zinc-400 mt-1">
                Connect your YouTube channel using official Google OAuth 2.0 to publish generated clips directly to Shorts.
              </p>
            </div>
          </div>

          <div className="p-4 rounded-2xl bg-black/40 border border-white/10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-red-600/10 border border-red-500/20 flex items-center justify-center text-red-500">
                <Video className="w-5 h-5" />
              </div>
              <div>
                <div className="text-xs font-bold text-white">Google YouTube Data API v3</div>
                <div className="text-[11px] text-zinc-400">Status: Disconnected / Ready for OAuth authorization</div>
              </div>
            </div>

            <button
              onClick={() => {
                alert("Redirecting to official Google OAuth 2.0 authorization endpoint...");
              }}
              className="px-4 py-2 rounded-xl text-xs font-bold text-white bg-red-600 hover:bg-red-500 transition-colors flex items-center gap-2"
            >
              <Video className="w-3.5 h-3.5" /> Connect YouTube Channel
            </button>
          </div>
        </div>

        {/* 3. Subscription & Plan Quotas */}
        <div className="p-6 sm:p-8 rounded-3xl bg-[#0f0c1e]/80 border border-white/10">
          <h3 className="text-lg font-bold text-white mb-1">Plan & Usage Quota</h3>
          <p className="text-xs text-zinc-400 mb-6">Review your monthly clip allocation and GPU processing limits.</p>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="p-4 rounded-2xl bg-black/40 border border-white/10">
              <span className="text-[10px] uppercase font-bold text-zinc-400">Active Tier</span>
              <div className="text-xl font-extrabold text-purple-400 uppercase mt-1">
                {user?.plan_tier || "free"}
              </div>
            </div>

            <div className="p-4 rounded-2xl bg-black/40 border border-white/10">
              <span className="text-[10px] uppercase font-bold text-zinc-400">Monthly Clips</span>
              <div className="text-xl font-extrabold text-white mt-1">
                {user?.clips_generated_this_month || 0} / {user?.monthly_clip_quota || 50}
              </div>
            </div>

            <div className="p-4 rounded-2xl bg-black/40 border border-white/10">
              <span className="text-[10px] uppercase font-bold text-zinc-400">Encoding Speed</span>
              <div className="text-xl font-extrabold text-emerald-400 mt-1">NVIDIA NVENC</div>
            </div>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
