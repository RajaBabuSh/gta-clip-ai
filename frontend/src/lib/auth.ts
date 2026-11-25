import bcrypt from "bcryptjs";
import crypto from "crypto";
import { cookies } from "next/headers";
import { query } from "./db";

const JWT_SECRET = process.env.AUTH_SECRET || "gtaclip-ai-super-secret-jwt-key-2026";
const COOKIE_NAME = "gtaclip_session";

export interface AuthUser {
  id: string;
  username: string;
  email: string;
  role: "user" | "admin" | "superadmin";
  full_name?: string;
  plan_tier?: string;
  monthly_clip_quota?: number;
  clips_generated_this_month?: number;
  created_at?: string;
}

export function comparePassword(password: string, hash: string): boolean {
  if (!password || !hash) return false;
  // Convert PHP $2y$ blowfish hash prefix to Node bcrypt $2a$ format if needed
  const normalizedHash = hash.startsWith("$2y$") ? "$2a$" + hash.slice(4) : hash;
  return bcrypt.compareSync(password, normalizedHash);
}

export function hashPassword(password: string): string {
  return bcrypt.hashSync(password, 10);
}

export function createSessionToken(user: AuthUser): string {
  const payload = {
    sub: user.id,
    username: user.username,
    email: user.email,
    role: user.role,
    exp: Math.floor(Date.now() / 1000) + 60 * 60 * 24 * 7, // 7 days
  };
  const data = Buffer.from(JSON.stringify(payload)).toString("base64url");
  const signature = crypto
    .createHmac("sha256", JWT_SECRET)
    .update(data)
    .digest("base64url");
  return `${data}.${signature}`;
}

export function verifySessionToken(token: string): { sub: string; username: string; email: string; role: string } | null {
  try {
    const parts = token.split(".");
    if (parts.length !== 2) return null;
    const [data, signature] = parts;
    const expected = crypto
      .createHmac("sha256", JWT_SECRET)
      .update(data)
      .digest("base64url");
    if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) {
      return null;
    }
    const payload = JSON.parse(Buffer.from(data, "base64url").toString("utf-8"));
    if (payload.exp && payload.exp < Math.floor(Date.now() / 1000)) {
      return null;
    }
    return payload;
  } catch {
    return null;
  }
}

export async function getCurrentUser(): Promise<AuthUser | null> {
  try {
    const cookieStore = await cookies();
    const token = cookieStore.get(COOKIE_NAME)?.value;
    if (!token) return null;

    const payload = verifySessionToken(token);
    if (!payload?.sub) return null;

    // Check users table
    const users = await query<any[]>("SELECT id, username, email, role, full_name, plan_tier, monthly_clip_quota, clips_generated_this_month, created_at FROM users WHERE id = ?", [payload.sub]);
    if (users && users.length > 0) {
      return users[0] as AuthUser;
    }

    // Check admins table
    const admins = await query<any[]>("SELECT id, username, email, role, full_name, created_at FROM admins WHERE id = ?", [payload.sub]);
    if (admins && admins.length > 0) {
      return {
        ...admins[0],
        plan_tier: "enterprise",
      } as AuthUser;
    }

    return null;
  } catch {
    return null;
  }
}
