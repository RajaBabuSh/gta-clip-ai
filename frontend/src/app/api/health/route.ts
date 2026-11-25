import { NextResponse } from "next/server";
import { query } from "@/lib/db";

export async function GET() {
  let dbStatus = "disconnected";
  let workerStatus = "offline";
  let workerDetails = null;

  try {
    const res = await query<any[]>("SELECT 1 as connected");
    if (res && res.length > 0 && res[0].connected === 1) {
      dbStatus = "connected";
    }
  } catch {
    dbStatus = "error";
  }

  try {
    const workerUrl = process.env.AI_WORKER_URL || "http://127.0.0.1:8000";
    const res = await fetch(`${workerUrl}/api/v1/health`, { next: { revalidate: 10 } });
    if (res.ok) {
      const data = await res.json();
      workerStatus = data.status || "online";
      workerDetails = data.worker || null;
    }
  } catch {
    workerStatus = "offline";
  }

  return NextResponse.json({
    status: dbStatus === "connected" ? "healthy" : "degraded",
    database: dbStatus,
    ai_worker: workerStatus,
    worker_details: workerDetails,
    timestamp: new Date().toISOString(),
  });
}
