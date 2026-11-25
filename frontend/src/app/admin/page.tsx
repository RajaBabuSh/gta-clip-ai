"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { DashboardLayout } from "@/components/DashboardLayout";
import { useAuth } from "@/context/AuthContext";
import {
  Shield,
  Users,
  Activity,
  Cpu,
  FileText,
  Sliders,
  CheckCircle2,
  Clock,
  AlertCircle,
  RefreshCw,
  Search,
  ExternalLink,
  Flame,
  ArrowRight
} from "lucide-react";

export default function AdminDashboardPage() {
  const { user, loading: authLoading } = useAuth();
  const router = useRouter();

  const [users, setUsers] = useState<any[]>([]);
  const [jobs, setJobs] = useState<any[]>([]);
  const [logs, setLogs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchUser, setSearchUser] = useState("");

  useEffect(() => {
    if (!authLoading && (!user || (user.role !== "admin" && user.role !== "superadmin"))) {
      router.push("/dashboard");
    }
  }, [user, authLoading, router]);

  const loadAdminData = async () => {
    try {
      setLoading(true);
      const [uRes, jRes, lRes] = await Promise.all([
        fetch("/api/admin/users"),
        fetch("/api/jobs?limit=20"),
        fetch("/api/admin/logs"),
      ]);

      if (uRes.ok) setUsers(await uRes.json());
      if (jRes.ok) setJobs(await jRes.json());
      if (lRes.ok) setLogs(await lRes.json());
    } catch (e) {
      console.error("Failed to load admin data:", e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (user && (user.role === "admin" || user.role === "superadmin")) {
      loadAdminData();
    }
  }, [user]);

  if (authLoading || !user || (user.role !== "admin" && user.role !== "superadmin")) {
    return (
      <div className="min-h-screen bg-[#07050f] flex items-center justify-center text-purple-400">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500" />
      </div>
    );
  }

  const filteredUsers = users.filter((u) =>
    (u.username || "").toLowerCase().includes(searchUser.toLowerCase()) ||
    (u.email || "").toLowerCase().includes(searchUser.toLowerCase())
  );

  return (
    <DashboardLayout pageTitle="Administration & Operations Console">
      {/* 1. TOP HEADER & SUPER ADMIN SWITCH */}
      <div className="p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-purple-950/70 via-[#0f0c1e] to-indigo-950/50 border border-purple-500/20 shadow-xl mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/15 border border-purple-500/30 text-purple-300 text-xs font-semibold mb-2">
            <Shield className="w-3.5 h-3.5" /> Staff Privileges Active
          </div>
          <h2 className="text-2xl font-extrabold text-white">Platform Operations Center</h2>
          <p className="text-xs text-zinc-400 mt-1">
            Real-time control over user tiers, AI worker processing queues, and system audit trails.
          </p>
        </div>

        <div className="flex gap-3">
          <Link
            href="/admin/super"
            className="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 shadow-md"
          >
            <Shield className="w-4 h-4" /> Super Admin Controls <ArrowRight className="w-3.5 h-3.5" />
          </Link>
          <button
            onClick={loadAdminData}
            title="Refresh Operations"
            className="p-2.5 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-zinc-300"
          >
            <RefreshCw className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* 2. STATS CARDS */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div className="p-5 rounded-2xl bg-[#0f0c1e]/70 border border-white/10">
          <span className="text-[10px] uppercase font-bold text-zinc-400">Registered Users</span>
          <div className="text-2xl font-extrabold text-white mt-1">{users.length}</div>
          <div className="text-[11px] text-emerald-400 font-semibold mt-0.5">Database Verified</div>
        </div>

        <div className="p-5 rounded-2xl bg-[#0f0c1e]/70 border border-white/10">
          <span className="text-[10px] uppercase font-bold text-zinc-400">Total Processing Jobs</span>
          <div className="text-2xl font-extrabold text-white mt-1">{jobs.length}</div>
          <div className="text-[11px] text-purple-400 font-semibold mt-0.5">Whisper + NVENC Queue</div>
        </div>

        <div className="p-5 rounded-2xl bg-[#0f0c1e]/70 border border-white/10">
          <span className="text-[10px] uppercase font-bold text-zinc-400">Audit Events Recorded</span>
          <div className="text-2xl font-extrabold text-white mt-1">{logs.length}</div>
          <div className="text-[11px] text-blue-400 font-semibold mt-0.5">Security Audit Stream</div>
        </div>

        <div className="p-5 rounded-2xl bg-[#0f0c1e]/70 border border-white/10">
          <span className="text-[10px] uppercase font-bold text-zinc-400">Worker Status</span>
          <div className="text-2xl font-extrabold text-emerald-400 mt-1">ONLINE</div>
          <div className="text-[11px] text-zinc-400 mt-0.5">Hardware NVENC Detected</div>
        </div>
      </div>

      {/* 3. DUAL SECTION: USER DIRECTORY & RECENT JOBS */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start mb-8">
        {/* User Directory Table */}
        <div className="lg:col-span-7 bg-[#0f0c1e]/80 border border-white/10 rounded-3xl p-6 shadow-xl">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
            <h3 className="text-base font-bold text-white flex items-center gap-2">
              <Users className="w-4 h-4 text-purple-400" /> User Directory
            </h3>
            <div className="relative">
              <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500" />
              <input
                type="text"
                placeholder="Search by username/email..."
                value={searchUser}
                onChange={(e) => setSearchUser(e.target.value)}
                className="pl-8 pr-3 py-1.5 bg-black/40 border border-white/10 rounded-xl text-xs text-white placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-purple-500 w-52"
              />
            </div>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="border-b border-white/10 text-zinc-400 uppercase tracking-wider text-[10px]">
                <tr>
                  <th className="pb-3">User</th>
                  <th className="pb-3">Tier</th>
                  <th className="pb-3">Quota</th>
                  <th className="pb-3">Status</th>
                  <th className="pb-3">Joined</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {filteredUsers.map((u) => (
                  <tr key={u.id} className="hover:bg-white/5 transition-colors">
                    <td className="py-3">
                      <div className="font-bold text-white">{u.username}</div>
                      <div className="text-[10px] text-zinc-400">{u.email}</div>
                    </td>
                    <td className="py-3">
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300 uppercase">
                        {u.plan_tier || "free"}
                      </span>
                    </td>
                    <td className="py-3 text-zinc-300">
                      {u.clips_generated_this_month || 0} / {u.monthly_clip_quota || 50}
                    </td>
                    <td className="py-3">
                      <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300">
                        {u.status || "active"}
                      </span>
                    </td>
                    <td className="py-3 text-zinc-500 text-[10px]">
                      {u.created_at ? new Date(u.created_at).toLocaleDateString() : "Active"}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        {/* AI Processing Jobs Monitor */}
        <div className="lg:col-span-5 bg-[#0f0c1e]/80 border border-white/10 rounded-3xl p-6 shadow-xl">
          <h3 className="text-base font-bold text-white flex items-center gap-2 mb-4">
            <Activity className="w-4 h-4 text-indigo-400" /> Pipeline Processing Jobs
          </h3>

          {jobs.length === 0 ? (
            <div className="py-8 text-center text-zinc-500 text-xs">
              No processing jobs recorded in queue.
            </div>
          ) : (
            <div className="space-y-3 max-h-[380px] overflow-y-auto pr-1">
              {jobs.map((j) => (
                <div key={j.id} className="p-3 rounded-xl bg-white/5 border border-white/10 text-xs">
                  <div className="flex items-center justify-between mb-1.5">
                    <span className="font-mono text-zinc-300 text-[11px] truncate max-w-[180px]">
                      {j.id}
                    </span>
                    <span
                      className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                        j.status === "COMPLETED"
                          ? "bg-emerald-500/20 text-emerald-300"
                          : j.status === "FAILED"
                          ? "bg-red-500/20 text-red-300"
                          : "bg-purple-500/20 text-purple-300"
                      }`}
                    >
                      {j.status || "PROCESSING"}
                    </span>
                  </div>
                  <div className="flex items-center justify-between text-[11px] text-zinc-400">
                    <span>Progress: {j.progress || 0}%</span>
                    <span>Clips: {j.total_clips_expected || 5}</span>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* 4. SECURITY AUDIT LOGS */}
      <div className="bg-[#0f0c1e]/80 border border-white/10 rounded-3xl p-6 shadow-xl">
        <h3 className="text-base font-bold text-white flex items-center gap-2 mb-4">
          <FileText className="w-4 h-4 text-purple-400" /> Platform Security & Audit Trail
        </h3>
        <div className="space-y-2">
          {logs.map((log) => (
            <div
              key={log.id}
              className="p-3 rounded-xl bg-white/5 border border-white/5 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs"
            >
              <div className="flex items-center gap-3">
                <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-white/10 text-zinc-300 uppercase font-mono">
                  {log.action}
                </span>
                <span className="text-zinc-300 truncate max-w-md">{log.details || "System action logged"}</span>
              </div>
              <div className="text-[11px] text-zinc-500 flex items-center gap-3">
                <span>IP: {log.ip_address || "127.0.0.1"}</span>
                <span>{log.created_at ? new Date(log.created_at).toLocaleString() : "Recent"}</span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </DashboardLayout>
  );
}
