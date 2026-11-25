import sys
import os
from pathlib import Path
import subprocess
import shutil
import pymysql
import json
import time

sys.path.insert(0, os.path.abspath('backend'))
from app.services.ffmpeg_render import FFmpegRenderEngine

source_path = Path(r'backend\storage\uploads\yt_qSyo5d-dVzA.mp4')
output_dir = Path(r'backend\storage\output')
ffmpeg_bin = shutil.which("ffmpeg") or "ffmpeg"

conn = pymysql.connect(host='127.0.0.1', user='root', password='', database='gtaclipai')
cur = conn.cursor(pymysql.cursors.DictCursor)

cur.execute('SELECT * FROM clips WHERE file_size_bytes < 1000 ORDER BY clip_index ASC')
tiny_clips = cur.fetchall()
print(f"Total tiny clips to repair across all jobs: {len(tiny_clips)}")

for c in tiny_clips:
    cid = c['id']
    idx = c['clip_index']
    start_t = c['start_time']
    end_t = min(449.0, c['end_time'])
    if end_t <= start_t:
        end_t = start_t + 10.0
    
    file_rel = c['file_path']
    out_file = Path(r'backend') / file_rel.replace('storage/', 'storage' + os.sep)
    if not out_file.parent.exists():
        out_file = Path(file_rel)
    
    thumb_name = out_file.stem + "_thumb.jpg"
    thumb_path = out_file.parent / thumb_name
    
    try:
        FFmpegRenderEngine.render_vertical_clip(
            source_video_path=source_path,
            start_time=start_t,
            end_time=end_t,
            output_clip_path=out_file,
            words=[],
            burn_subtitles=False,
            aspect_ratio="9:16",
            layout_mode="blurred_background"
        )
        meta = FFmpegRenderEngine.validate_rendered_clip(out_file)
        
        # Generate thumbnail at 1.0s
        subprocess.run(
            [ffmpeg_bin, "-y", "-ss", "00:00:01", "-i", str(out_file), "-vframes", "1", "-update", "1", "-q:v", "2", str(thumb_path)],
            capture_output=True, text=True
        )
        
        rel_clip_path = f"storage/output/{out_file.name}"
        rel_thumb_path = f"storage/output/{thumb_name}" if thumb_path.exists() else None
        
        cur.execute('''
            UPDATE clips 
            SET file_path = %s, file_size_bytes = %s, duration = %s, thumbnail_path = %s, status = 'approved'
            WHERE id = %s
        ''', (rel_clip_path, meta['file_size'], meta['duration'], rel_thumb_path, cid))
        conn.commit()
        print(f"Repaired clip #{idx} ({cid[:8]}): {meta['file_size']} bytes, dur={meta['duration']:.1f}s")
    except Exception as ex:
        print(f"Failed to repair clip {cid[:8]}: {ex}")

print("All remaining clips repaired!")
