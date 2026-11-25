"use client";

import React, { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useAuth } from "@/context/AuthContext";
import { Sparkles, Video, ArrowRight, ShieldCheck, Lock, Mail, User, AlertCircle, Loader2 } from "lucide-react";

export default function LoginPage() {
  const [loginId, setLoginId] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const { login } = useAuth();
  const router = useRouter();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      const res = await login(loginId, password);
      if (res.success) {
        router.push("/dashboard");
      } else {
        setError(res.error || "Invalid username or password.");
      }
    } catch {
      setError("An unexpected error occurred. Please try again.");
    } finally {
      setSubmitting(false);
    }
  };

  const handleQuickDemo = (userType: "user" | "admin") => {
    if (userType === "user") {
      setLoginId("rajababush5432@gmail.com");
    } else {
      setLoginId("superadmin");
    }
  };

  return (
    <div className="min-h-screen bg-[#07050f] text-zinc-100 flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden">
      {/* Background Glow Accents */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[350px] bg-purple-600/15 rounded-full blur-[140px] pointer-events-none" />
      <div className="absolute bottom-10 right-1/4 w-[400px] h-[250px] bg-blue-600/10 rounded-full blur-[120px] pointer-events-none" />

      <div className="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
        <div className="flex justify-center mb-6">
          <Link href="/" className="flex items-center gap-2.5 group">
            <div className="h-11 w-11 rounded-xl bg-gradient-to-tr from-purple-600 via-indigo-600 to-blue-500 p-0.5 shadow-lg shadow-purple-900/30 group-hover:scale-105 transition-transform duration-300">
              <div className="w-full h-full bg-[#0d0b1a] rounded-[10px] flex items-center justify-center">
                <Video className="w-5 h-5 text-purple-400" />
              </div>
            </div>
            <span className="font-extrabold text-2xl tracking-tight text-white">
              GTA CLIP <span className="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-indigo-300 to-blue-400">AI</span>
            </span>
          </Link>
        </div>

        <h2 className="text-center text-3xl font-extrabold tracking-tight text-white">
          Sign In to Creator Studio
        </h2>
        <p className="mt-2 text-center text-sm text-zinc-400">
          Or{" "}
          <Link href="/register" className="font-semibold text-purple-400 hover:text-purple-300 transition-colors">
            create a free account with 50 monthly clips
          </Link>
        </p>
      </div>

      <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md relative z-10">
        <div className="bg-[#0f0c1e]/80 backdrop-blur-xl border border-white/10 py-8 px-6 shadow-2xl shadow-purple-950/40 rounded-2xl sm:px-10">
          {error && (
            <div className="mb-6 p-3.5 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm flex items-start gap-3">
              <AlertCircle className="w-5 h-5 flex-shrink-0 text-red-400 mt-0.5" />
              <span>{error}</span>
            </div>
          )}

          <form className="space-y-5" onSubmit={handleSubmit}>
            <div>
              <label className="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">
                Email or Username
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-500">
                  <User className="h-4 w-4" />
                </div>
                <input
                  type="text"
                  required
                  value={loginId}
                  onChange={(e) => setLoginId(e.target.value)}
                  placeholder="user@gtaclip.ai or superadmin"
                  className="w-full pl-10 pr-4 py-2.5 bg-black/40 border border-white/10 rounded-xl text-white placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all text-sm"
                />
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between mb-2">
                <label className="block text-xs font-semibold text-zinc-300 uppercase tracking-wider">
                  Password
                </label>
                <Link href="/forgot-password" className="text-xs text-purple-400 hover:text-purple-300">
                  Forgot password?
                </Link>
              </div>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-zinc-500">
                  <Lock className="h-4 w-4" />
                </div>
                <input
                  type="password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••••••"
                  className="w-full pl-10 pr-4 py-2.5 bg-black/40 border border-white/10 rounded-xl text-white placeholder-zinc-500 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all text-sm"
                />
              </div>
            </div>

            <div className="flex items-center justify-between pt-1">
              <div className="flex items-center">
                <input
                  id="remember-me"
                  name="remember-me"
                  type="checkbox"
                  defaultChecked
                  className="h-4 w-4 rounded bg-black/40 border-white/10 text-purple-600 focus:ring-purple-500"
                />
                <label htmlFor="remember-me" className="ml-2 block text-xs text-zinc-400">
                  Remember my session
                </label>
              </div>
              <span className="text-[11px] text-emerald-400 font-medium flex items-center gap-1">
                <ShieldCheck className="w-3.5 h-3.5" /> 256-Bit Encrypted
              </span>
            </div>

            <button
              type="submit"
              disabled={submitting}
              className="w-full flex items-center justify-center gap-2 py-3 px-4 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-purple-500 shadow-lg shadow-purple-600/30 disabled:opacity-50 transition-all duration-200 cursor-pointer"
            >
              {submitting ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" /> Signing In...
                </>
              ) : (
                <>
                  Sign In to Studio <ArrowRight className="w-4 h-4" />
                </>
              )}
            </button>
          </form>

          {/* Quick Prefill helper for users */}
          <div className="mt-6 pt-5 border-t border-white/10">
            <p className="text-xs text-zinc-400 text-center mb-3">Quick prefill credentials:</p>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => handleQuickDemo("user")}
                className="flex-1 py-1.5 px-3 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-zinc-300 font-medium transition-colors"
              >
                👤 Existing User
              </button>
              <button
                type="button"
                onClick={() => handleQuickDemo("admin")}
                className="flex-1 py-1.5 px-3 rounded-lg bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/20 text-xs text-purple-300 font-medium transition-colors"
              >
                ⚡ Super Admin
              </button>
            </div>
          </div>
        </div>

        <p className="text-center text-xs text-zinc-500 mt-6">
          Protected by enterprise rate limiting and token session encryption.
        </p>
      </div>
    </div>
  );
}
