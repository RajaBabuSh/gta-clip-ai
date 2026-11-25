import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export async function GET(req: NextRequest) {
  try {
    const url = new URL(req.url);
    const videoId = url.searchParams.get("video_id");
    const aspectRatio = url.searchParams.get("aspect_ratio");
    const limit = Number(url.searchParams.get("limit")) || 100;

    let sql = "SELECT * FROM clips WHERE 1=1";
    const params: any[] = [];

    if (videoId) {
      sql += " AND video_id = ?";
      params.push(videoId);
    }
    if (aspectRatio && aspectRatio !== "all") {
      sql += " AND aspect_ratio = ?";
      params.push(aspectRatio);
    }

    sql += " ORDER BY virality_score DESC, created_at DESC LIMIT ?";
    params.push(limit);

    const clips = await query<any[]>(sql, params);

    // Map download_url and thumbnail_url to accessible paths
    const formatted = (clips || []).map((c) => ({
      ...c,
      download_url: c.file_path ? `/storage/${c.file_path.replace(/^storage[\\/]/, '')}` : `/api/download?id=${c.id}`,
      thumbnail_url: c.thumbnail_path ? `/storage/${c.thumbnail_path.replace(/^storage[\\/]/, '')}` : null,
    }));

    return NextResponse.json(formatted);
  } catch (err: any) {
    console.error("Clips API error:", err);
    return NextResponse.json({ error: "Failed to load clips" }, { status: 500 });
  }
}
