import pymysql
import subprocess
import json
from pathlib import Path

conn = pymysql.connect(host='127.0.0.1', user='root', password='', database='gtaclipai')
with conn.cursor(pymysql.cursors.DictCursor) as cur:
    cur.execute('SELECT * FROM clips WHERE job_id = %s ORDER BY clip_index ASC', ('336c9390-1934-4afd-90af-876ab95b6be5',))
    clips = cur.fetchall()

print(f"Total clips found for job 336c9390: {len(clips)}")
for i, c in enumerate(clips, 1):
    fp = c['file_path']
    cid = c['id']
    resolved_path = Path(fp)
    if not resolved_path.is_absolute():
        resolved_path = Path.cwd() / resolved_path
    
    exists = resolved_path.exists()
    size = resolved_path.stat().st_size if exists else 0
    print(f"\n--- Clip #{i} (ID: {cid}) ---")
    print(f"Title: {c['title']}")
    print(f"DB file_path: {fp}")
    print(f"Resolved on disk: {resolved_path}")
    print(f"Exists: {exists}, Size: {size} bytes ({size / (1024*1024):.2f} MB)")
    
    if exists and size > 0:
        ffprobe_cmd = [
            'ffprobe', '-v', 'error',
            '-show_entries', 'stream=codec_name,codec_type,width,height,pix_fmt,r_frame_rate,duration:format=duration,size,bit_rate',
            '-of', 'json',
            str(resolved_path)
        ]
        res = subprocess.run(ffprobe_cmd, capture_output=True, text=True)
        if res.returncode == 0:
            info = json.loads(res.stdout)
            streams = info.get('streams', [])
            v_stream = next((s for s in streams if s.get('codec_type') == 'video'), None)
            a_stream = next((s for s in streams if s.get('codec_type') == 'audio'), None)
            fmt = info.get('format', {})
            dur = fmt.get('duration')
            br = fmt.get('bit_rate')
            print(f"FFprobe duration: {dur}s, Bitrate: {br} bps")
            if v_stream:
                print(f"Video: {v_stream.get('codec_name')}, {v_stream.get('width')}x{v_stream.get('height')}, pix_fmt: {v_stream.get('pix_fmt')}, fps: {v_stream.get('r_frame_rate')}")
            else:
                print("NO VIDEO STREAM!")
            if a_stream:
                print(f"Audio: {a_stream.get('codec_name')}")
            else:
                print("NO AUDIO STREAM!")
        else:
            print("FFprobe ERROR:", res.stderr[:200])
