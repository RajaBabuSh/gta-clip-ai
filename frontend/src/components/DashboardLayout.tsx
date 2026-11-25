"use client";

import React, { useState } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useAuth } from "@/context/AuthContext";
import {
  Video,
  LayoutDashboard,
  Film,
  Sparkles,
  FolderOpen,
  Settings,
  Shield,
  LogOut,
  Menu,
  X,
  PlusCircle,
  HardDrive,
  Cpu,
  User,
  ChevronRight
} from "lucide-react";

interface DashboardLayoutProps {
  children: React.ReactNode;
  pageTitle?: string;
}

export function DashboardLayout({ children, pageTitle = "Creator Studio" }: DashboardLayoutProps) {
  const { user, logout } = useAuth();
  const pathname = usePathname();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const navLinks = [
    { label: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
    { label: "AI Clipping Studio", href: "/studio", icon: Sparkles },
    { label: "My Clips Library", href: "/my-clips", icon: Film },
    { label: "My Projects", href: "/my-projects", icon: FolderOpen },
    { label: "Account Settings", href: "/settings", icon: Settings },
  ];

  if (user?.role === "admin" || user?.role === "superadmin") {
    navLinks.push({ label: "Admin Console", href: "/admin", icon: Shield });
  }

  return (
    <div className="min-h-screen bg-[#07050f] text-zinc-100 flex">
      {/* 1. DESKTOP SIDEBAR */}
      <aside className="hidden lg:flex flex-col w-64 border-r border-white/10 bg-[#0a0816]/95 backdrop-blur-xl fixed inset-y-0 z-40">
        {/* Brand Header */}
        <div className="h-20 px-6 flex items-center border-b border-white/10">
          <Link href="/dashboard" className="flex items-center gap-2.5 group">
            <div className="h-9 w-9 rounded-xl bg-gradient-to-tr from-purple-600 via-indigo-600 to-blue-500 p-0.5 shadow-md shadow-purple-900/30">
              <div className="w-full h-full bg-[#0d0b1a] rounded-[10px] flex items-center justify-center">
                <Video className="w-4 h-4 text-purple-400" />
              </div>
            </div>
            <div>
              <span className="font-extrabold text-lg tracking-tight text-white">
                GTA CLIP <span className="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-blue-400">AI</span>
              </span>
              <span className="block text-[10px] text-zinc-400 font-medium">Creator Studio</span>
            </div>
          </Link>
        </div>

        {/* Navigation Links */}
        <div className="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
          {navLinks.map((item) => {
            const Icon = item.icon;
            const isActive = pathname === item.href || (item.href !== "/dashboard" && pathname.startsWith(item.href));
            return (
              <Link
                key={item.href}
                href={item.href}
                className={`flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all ${
                  isActive
                    ? "bg-purple-600/20 text-purple-300 border border-purple-500/30 shadow-sm"
                    : "text-zinc-400 hover:text-white hover:bg-white/5"
                }`}
              >
                <Icon className={`w-4 h-4 ${isActive ? "text-purple-400" : "text-zinc-500"}`} />
                <span>{item.label}</span>
              </Link>
            );
          })}
        </div>

        {/* Storage & Plan Quota Widget */}
        <div className="p-4 mx-4 mb-4 rounded-2xl bg-white/5 border border-white/10">
          <div className="flex items-center justify-between text-xs mb-1.5">
            <span className="font-bold text-zinc-300">Monthly Clip Quota</span>
            <span className="text-purple-400 font-extrabold">
              {user?.clips_generated_this_month || 0} / {user?.monthly_clip_quota || 50}
            </span>
          </div>
          <div className="w-full bg-black/60 h-1.5 rounded-full overflow-hidden mb-2">
            <div
              className="bg-gradient-to-r from-purple-500 to-indigo-500 h-full rounded-full transition-all duration-300"
              style={{
                width: `${Math.min(
                  100,
                  (((user?.clips_generated_this_month || 0) / (user?.monthly_clip_quota || 50)) * 100) || 5
                )}%`,
              }}
            />
          </div>
          <div className="flex items-center justify-between text-[11px] text-zinc-400">
            <span className="uppercase text-[10px] font-bold tracking-wider text-purple-300">
              {user?.plan_tier || "free"} plan
            </span>
            <Link href="/pricing" className="text-indigo-400 hover:text-indigo-300 font-semibold">
              Upgrade
            </Link>
          </div>
        </div>

        {/* User Card & Logout */}
        <div className="p-4 border-t border-white/10 flex items-center justify-between">
          <div className="flex items-center gap-3 min-w-0">
            <div className="w-8 h-8 rounded-full bg-purple-600/20 border border-purple-500/30 flex items-center justify-center text-purple-400 font-bold text-xs flex-shrink-0">
              {user?.username?.charAt(0).toUpperCase() || "U"}
            </div>
            <div className="truncate">
              <div className="text-xs font-bold text-white truncate">{user?.username || "Creator"}</div>
              <div className="text-[10px] text-zinc-400 truncate">{user?.email || "user@gtaclip.ai"}</div>
            </div>
          </div>
          <button
            onClick={() => logout()}
            title="Log Out"
            className="p-1.5 rounded-lg text-zinc-400 hover:text-red-400 hover:bg-red-500/10 transition-colors"
          >
            <LogOut className="w-4 h-4" />
          </button>
        </div>
      </aside>

      {/* 2. MAIN CONTENT AREA */}
      <div className="flex-1 lg:pl-64 flex flex-col min-h-screen">
        {/* Top Header */}
        <header className="h-20 border-b border-white/10 bg-[#07050f]/80 backdrop-blur-xl px-4 sm:px-8 flex items-center justify-between sticky top-0 z-30">
          <div className="flex items-center gap-4">
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="lg:hidden p-2 rounded-lg text-zinc-400 hover:text-white hover:bg-white/5"
            >
              {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </button>
            <h1 className="text-lg sm:text-xl font-bold text-white tracking-tight">{pageTitle}</h1>
          </div>

          <div className="flex items-center gap-3">
            <Link
              href="/studio"
              className="hidden sm:flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 shadow-md shadow-purple-600/20 transition-all"
            >
              <PlusCircle className="w-3.5 h-3.5" /> Launch AI Studio
            </Link>
            <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              <span className="text-zinc-300 hidden md:inline">NVENC GPU Active</span>
            </div>
          </div>
        </header>

        {/* Mobile Navigation Drawer */}
        {mobileMenuOpen && (
          <div className="lg:hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-md pt-20 px-6">
            <div className="space-y-2">
              {navLinks.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  onClick={() => setMobileMenuOpen(false)}
                  className="flex items-center gap-3 px-4 py-3 rounded-xl text-base font-semibold bg-white/5 text-zinc-200"
                >
                  <item.icon className="w-5 h-5 text-purple-400" />
                  <span>{item.label}</span>
                </Link>
              ))}
              <button
                onClick={() => logout()}
                className="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-base font-semibold bg-red-500/10 text-red-400 mt-4"
              >
                <LogOut className="w-5 h-5" />
                <span>Sign Out</span>
              </button>
            </div>
          </div>
        )}

        {/* Page Content */}
        <main className="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto">{children}</main>
      </div>
    </div>
  );
}
