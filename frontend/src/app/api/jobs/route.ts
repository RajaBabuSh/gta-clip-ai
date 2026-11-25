import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export async function GET(req: NextRequest) {
  try {
    const url = new URL(req.url);
    const videoId = url.searchParams.get("video_id");
    const limit = Number(url.searchParams.get("limit")) || 20;

    let sql = "SELECT * FROM processing_jobs WHERE 1=1";
    const params: any[] = [];

    if (videoId) {
      sql += " AND video_id = ?";
      params.push(videoId);
    }
    sql += " ORDER BY created_at DESC LIMIT ?";
    params.push(limit);

    const jobs = await query<any[]>(sql, params);
    return NextResponse.json(jobs || []);
  } catch (err: any) {
    console.error("Jobs API error:", err);
    return NextResponse.json({ error: "Failed to load jobs" }, { status: 500 });
  }
}

export async function POST(req: NextRequest) {
  try {
    const user = await getCurrentUser();
    const body = await req.json();
    const videoId = body.video_id;
    const targetClips = Number(body.target_clips) || 5;

    if (!videoId) {
      return NextResponse.json({ error: "video_id is required" }, { status: 400 });
    }

    // Try forwarding to local FastAPI or AI worker URL
    const workerUrl = process.env.AI_WORKER_URL || "http://127.0.0.1:8000";
    let workerSuccess = false;
    let jobData: any = null;

    try {
      const res = await fetch(`${workerUrl}/api/v1/jobs/`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ video_id: videoId, target_clips: targetClips }),
      });
      if (res.ok) {
        jobData = await res.json();
        workerSuccess = true;
      }
    } catch {
      // Worker offline or unreachable
    }

    if (!workerSuccess) {
      // Create record directly in database queue
      const jobId = crypto.randomUUID();
      await query(
        "INSERT INTO processing_jobs (id, video_id, status, progress_percentage, clips_total_target, created_at, updated_at) VALUES (?, ?, 'QUEUED', 0, ?, NOW(), NOW())",
        [jobId, videoId, targetClips]
      );
      jobData = { id: jobId, video_id: videoId, status: "QUEUED", progress_percentage: 0 };
    }

    return NextResponse.json({
      success: true,
      message: "AI clipping job dispatched successfully!",
      job: jobData,
    });
  } catch (err: any) {
    console.error("Job dispatch error:", err);
    return NextResponse.json({ error: "Failed to dispatch clipping job" }, { status: 500 });
  }
}
