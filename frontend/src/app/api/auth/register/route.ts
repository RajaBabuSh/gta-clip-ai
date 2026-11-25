import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import { hashPassword, createSessionToken, AuthUser } from "@/lib/auth";
import crypto from "crypto";

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const username = (body.username || "").trim().toLowerCase().replace(/[^a-z0-9_-]/g, "");
    const email = (body.email || "").trim().toLowerCase();
    const password = body.password || "";
    const fullName = (body.full_name || body.fullName || username).trim();

    if (username.length < 3) {
      return NextResponse.json({ error: "Username must be at least 3 characters." }, { status: 400 });
    }
    if (!email || !email.includes("@")) {
      return NextResponse.json({ error: "Please enter a valid email address." }, { status: 400 });
    }
    if (password.length < 6) {
      return NextResponse.json({ error: "Password must be at least 6 characters." }, { status: 400 });
    }

    // Check if email or username already taken
    const existing = await query<any[]>(
      "SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1",
      [email, username]
    );
    if (existing && existing.length > 0) {
      return NextResponse.json({ error: "An account with this email or username already exists." }, { status: 409 });
    }

    const userId = crypto.randomUUID();
    const pwdHash = hashPassword(password);

    await query(
      "INSERT INTO users (id, username, email, password_hash, full_name, plan_tier, monthly_clip_quota, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'free', 50, 'active', NOW(), NOW())",
      [userId, username, email, pwdHash, fullName]
    );

    const newUser: AuthUser = {
      id: userId,
      username,
      email,
      role: "user",
      full_name: fullName,
      plan_tier: "free",
      monthly_clip_quota: 50,
      clips_generated_this_month: 0,
    };

    const token = createSessionToken(newUser);
    const response = NextResponse.json({
      success: true,
      user: newUser,
      message: "Account created successfully! Welcome to GTA Clip AI.",
    });

    const isSecure = req.nextUrl.protocol === "https:" || req.headers.get("x-forwarded-proto") === "https";

    response.cookies.set("gtaclip_session", token, {
      httpOnly: true,
      secure: isSecure,
      sameSite: "lax",
      maxAge: 60 * 60 * 24 * 7,
      path: "/",
    });

    return response;
  } catch (err: any) {
    console.error("Register route error:", err);
    return NextResponse.json({ error: "Registration failed. Please try again." }, { status: 500 });
  }
}
