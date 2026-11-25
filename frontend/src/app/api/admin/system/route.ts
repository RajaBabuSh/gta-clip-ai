import { NextRequest, NextResponse } from "next/server";
import { query } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export async function GET() {
  try {
    const user = await getCurrentUser();
    if (!user || (user.role !== "admin" && user.role !== "superadmin")) {
      return NextResponse.json({ error: "Unauthorized access" }, { status: 403 });
    }

    const settings = await query<any[]>(
      "SELECT setting_key, setting_value, setting_group, description, is_sensitive FROM system_settings ORDER BY setting_group, setting_key"
    );

    // Redact sensitive values like secrets
    const sanitized = (settings || []).map((s) => ({
      ...s,
      setting_value: s.is_sensitive && s.setting_value ? "••••••••••••" : s.setting_value,
    }));

    return NextResponse.json(sanitized);
  } catch (err: any) {
    return NextResponse.json({ error: "Failed to fetch system settings" }, { status: 500 });
  }
}
