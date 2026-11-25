import shutil
import subprocess
from pathlib import Path

ffmpeg_bin = shutil.which("ffmpeg")
test_ass = Path(r"C:\xampp\htdocs\New folder\backend\storage\subtitles\test.ass")
test_ass.parent.mkdir(parents=True, exist_ok=True)
test_ass.write_text("""[Script Info]
ScriptType: v4.00+
PlayResX: 1080
PlayResY: 1920

[V4+ Styles]
Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
Style: Default,Arial,68,&H00FFFFFF,&H0000FFFF,&H00000000,&H90000000,-1,0,0,0,100,100,1,0,1,5.0,2.5,2,40,40,340,1

[Events]
Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
Dialogue: 0,0:00:01.00,0:00:03.00,Default,,0,0,0,,HELLO WORLD
""", encoding="utf-8")

escaped_ass = str(test_ass.resolve()).replace("\\", "/").replace(":", "\\:")
print("Escaped ASS path in filter:", escaped_ass)

cmd = [
    ffmpeg_bin,
    "-y",
    "-f", "lavfi", "-i", "color=c=black:s=1080x1920:d=2",
    "-filter_complex", f"[0:v]subtitles='{escaped_ass}'[v]",
    "-map", "[v]",
    "-c:v", "libx264",
    "-t", "1",
    "backend/storage/output/test_render.mp4"
]

res = subprocess.run(cmd, capture_output=True, text=True)
print("Return code:", res.returncode)
if res.returncode != 0:
    print("FFmpeg STDERR:")
    print(res.stderr)
else:
    print("SUCCESS rendering with subtitles!")
