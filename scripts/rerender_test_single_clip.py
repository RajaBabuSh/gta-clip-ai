import sys
import os
from pathlib import Path
import subprocess
import shutil
import pymysql

sys.path.insert(0, os.path.abspath('backend'))
from app.services.ffmpeg_render import FFmpegRenderEngine

clip_id = '68bb04dc-dc0c-4327-bb27-dcd10ba0aa77'
source_path = Path(r'backend\storage\uploads\yt_qSyo5d-dVzA.mp4')
output_path = Path(r'backend\storage\output\clip_a4e941f7_1_68bb04.mp4')
thumb_path = Path(r'backend\storage\output\clip_a4e941f7_1_68bb04_thumb.jpg')

print(f"Rendering Clip #1 ({clip_id}) from {source_path}...")
start_time = 94.66
end_time = 107.80

FFmpegRenderEngine.render_vertical_clip(
    source_video_path=source_path,
    start_time=start_time,
    end_time=end_time,
    output_clip_path=output_path,
    words=[],
    burn_subtitles=False,
    aspect_ratio="9:16",
    layout_mode="blurred_background"
)

# Validate output
meta = FFmpegRenderEngine.validate_rendered_clip(output_path)
print("Validated metadata:", meta)

# Generate thumbnail at 1.0s
ffmpeg_bin = shutil.which("ffmpeg") or "ffmpeg"
res_thumb = subprocess.run(
    [ffmpeg_bin, "-y", "-ss", "00:00:01", "-i", str(output_path), "-vframes", "1", "-update", "1", "-q:v", "2", str(thumb_path)],
    capture_output=True, text=True
)
print("Thumbnail generated. Size:", thumb_path.stat().st_size if thumb_path.exists() else 0)

# Update database record
conn = pymysql.connect(host='127.0.0.1', user='root', password='', database='gtaclipai')
cur = conn.cursor(pymysql.cursors.DictCursor)
cur.execute('''
    UPDATE clips 
    SET file_size_bytes = %s, duration = %s, thumbnail_path = %s, status = 'ready'
    WHERE id = %s
''', (meta['file_size'], meta['duration'], 'storage/output/' + thumb_path.name, clip_id))
conn.commit()
print("Updated MySQL clip record successfully!")
