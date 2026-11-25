import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export async function GET() {
  try {
    const user = await getCurrentUser();
    if (!user || (user.role !== "admin" && user.role !== "superadmin")) {
      return NextResponse.json({ error: "Unauthorized access" }, { status: 403 });
    }

    const logs = await query<any[]>(
      "SELECT id, action, details, ip_address, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 50"
    );
    return NextResponse.json(logs || []);
  } catch (err: any) {
    return NextResponse.json({ error: "Failed to fetch audit logs" }, { status: 500 });
  }
}
