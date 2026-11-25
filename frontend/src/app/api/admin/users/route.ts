import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export async function GET() {
  try {
    const user = await getCurrentUser();
    if (!user || (user.role !== "admin" && user.role !== "superadmin")) {
      return NextResponse.json({ error: "Unauthorized access" }, { status: 403 });
    }

    const users = await query<any[]>(
      "SELECT id, username, email, full_name, plan_tier, monthly_clip_quota, clips_generated_this_month, status, created_at FROM users ORDER BY created_at DESC LIMIT 100"
    );
    return NextResponse.json(users || []);
  } catch (err: any) {
    return NextResponse.json({ error: "Failed to fetch users" }, { status: 500 });
  }
}
