import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export async function GET(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    const url = new URL(req.url);
    const limit = Number(url.searchParams.get("limit")) || 50;

    let sql = "SELECT * FROM videos";
    const params: any[] = [];

    // If regular user, filter by user_id if present
    if (user && user.role === "user") {
      sql += " WHERE user_id = ? OR user_id IS NULL";
      params.push(user.id);
    }
    sql += " ORDER BY created_at DESC LIMIT ?";
    params.push(limit);

    const videos = await query<any[]>(sql, params);
    return NextResponse.json(videos || []);
  } catch (err: any) {
    console.error("Videos API error:", err);
    return NextResponse.json({ error: "Failed to load videos" }, { status: 500 });
  }
}
