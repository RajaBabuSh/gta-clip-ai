import pymysql
import os
import subprocess
import json

conn = pymysql.connect(host='127.0.0.1', user='root', password='', database='gtaclipai')
cur = conn.cursor(pymysql.cursors.DictCursor)

job_id = 'a03b7f43-74d3-40d0-b8e2-5f592f92cd53'
cur.execute('SELECT * FROM clips WHERE job_id = %s ORDER BY clip_index ASC', (job_id,))
clips = cur.fetchall()

base_dir = r'C:\xampp\htdocs\New folder'
valid_count = 0
invalid_count = 0
report = []

for c in clips:
    p = c['file_path']
    real_path = os.path.join(base_dir, p) if not os.path.isabs(p) else p
    exists = os.path.exists(real_path)
    sz = os.path.getsize(real_path) if exists else 0
    
    thumb_p = c['thumbnail_path']
    thumb_exists = False
    thumb_sz = 0
    if thumb_p:
        real_thumb = os.path.join(base_dir, thumb_p) if not os.path.isabs(thumb_p) else thumb_p
        thumb_exists = os.path.exists(real_thumb)
        thumb_sz = os.path.getsize(real_thumb) if thumb_exists else 0
        
    # Check ffprobe
    res = subprocess.run(
        ['ffprobe', '-v', 'error', '-show_format', '-show_streams', '-of', 'json', real_path],
        capture_output=True, text=True
    )
    is_valid = False
    streams_summary = "N/A"
    duration = 0.0
    if res.returncode == 0:
        try:
            data = json.loads(res.stdout)
            streams = data.get('streams', [])
            v_stream = next((s for s in streams if s.get('codec_type') == 'video'), None)
            a_stream = next((s for s in streams if s.get('codec_type') == 'audio'), None)
            duration = float(data.get('format', {}).get('duration') or 0.0)
            if v_stream and sz > 100_000 and duration > 0.5:
                is_valid = True
                streams_summary = f"{v_stream.get('codec_name')} {v_stream.get('width')}x{v_stream.get('height')}"
                if a_stream:
                    streams_summary += f" + {a_stream.get('codec_name')}"
        except Exception:
            pass
            
    if is_valid:
        valid_count += 1
    else:
        invalid_count += 1
        
    report.append({
        'index': c['clip_index'],
        'title': c['title'],
        'size_bytes': sz,
        'size_mb': round(sz / (1024*1024), 2),
        'duration': round(duration, 1),
        'streams': streams_summary,
        'valid': is_valid,
        'thumb_exists': thumb_exists,
        'thumb_kb': round(thumb_sz / 1024, 1)
    })

print(f"=== JOB a03b7f43 AUDIT RESULTS ===")
print(f"Total Clips: {len(clips)}")
print(f"Valid MP4s:  {valid_count} / {len(clips)}")
print(f"Invalid:     {invalid_count} / {len(clips)}")
print(f"All 40 have thumbnails: {all(r['thumb_exists'] for r in report)}")
print("\n--- SAMPLE CLIPS ---")
for r in report[:5]:
    print(f"Clip #{r['index']:2d}: {r['size_mb']:4.2f}MB, {r['duration']:4.1f}s, {r['streams']}, thumb={r['thumb_kb']}KB | {r['title'][:32]}")
print("...")
for r in report[-5:]:
    print(f"Clip #{r['index']:2d}: {r['size_mb']:4.2f}MB, {r['duration']:4.1f}s, {r['streams']}, thumb={r['thumb_kb']}KB | {r['title'][:32]}")
