import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import { comparePassword, createSessionToken, AuthUser } from "@/lib/auth";

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const login = (body.login || "").trim();
    const password = body.password || "";

    if (!login || !password) {
      return NextResponse.json(
        { error: "Please enter your username/email and password." },
        { status: 400 }
      );
    }

    // 1. Search in users table first
    const users = await query<any[]>(
      "SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1",
      [login, login]
    );

    let authUser: AuthUser | null = null;

    if (users && users.length > 0) {
      const u = users[0];
      if (u.status !== "active") {
        return NextResponse.json(
          { error: "Account is suspended or inactive." },
          { status: 403 }
        );
      }
      if (comparePassword(password, u.password_hash)) {
        authUser = {
          id: u.id,
          username: u.username,
          email: u.email,
          role: u.role || "user",
          full_name: u.full_name,
          plan_tier: u.plan_tier || "free",
          monthly_clip_quota: u.monthly_clip_quota || 50,
          clips_generated_this_month: u.clips_generated_this_month || 0,
          created_at: u.created_at,
        };
      }
    }

    // 2. If not found in users, check admins table
    if (!authUser) {
      const admins = await query<any[]>(
        "SELECT * FROM admins WHERE email = ? OR username = ? LIMIT 1",
        [login, login]
      );
      if (admins && admins.length > 0) {
        const a = admins[0];
        if (a.status !== "active") {
          return NextResponse.json(
            { error: "Administrative account is suspended." },
            { status: 403 }
          );
        }
        if (comparePassword(password, a.password_hash)) {
          authUser = {
            id: a.id,
            username: a.username,
            email: a.email,
            role: (a.role as any) || "superadmin",
            full_name: a.full_name || "Administrator",
            plan_tier: "enterprise",
            monthly_clip_quota: 10000,
            clips_generated_this_month: 0,
            created_at: a.created_at,
          };
        }
      }
    }

    if (!authUser) {
      return NextResponse.json(
        { error: "Invalid username/email or password." },
        { status: 401 }
      );
    }

    // Generate token and set HTTP-only cookie
    const token = createSessionToken(authUser);
    const response = NextResponse.json({
      success: true,
      user: authUser,
      message: `Welcome back, ${authUser.full_name || authUser.username}!`,
    });

    response.cookies.set("gtaclip_session", token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "lax",
      maxAge: 60 * 60 * 24 * 7, // 7 days
      path: "/",
    });

    return response;
  } catch (err: any) {
    console.error("Login route error:", err);
    return NextResponse.json(
      { error: "Internal server error during authentication." },
      { status: 500 }
    );
  }
}
