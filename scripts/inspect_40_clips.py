import pymysql
import os
import subprocess
import json

conn = pymysql.connect(host='127.0.0.1', user='root', password='', database='gtaclipai')
cur = conn.cursor(pymysql.cursors.DictCursor)
cur.execute('''
    SELECT id, job_id, video_id, clip_index, title, start_time, end_time, duration, file_path, file_size_bytes, status 
    FROM clips 
    WHERE job_id = 'a03b7f43-74d3-40d0-b8e2-5f592f92cd53' 
    ORDER BY clip_index ASC
''')
clips = cur.fetchall()
print(f"Total clips found for job a03b7f43: {len(clips)}")

base_dir = r"C:\xampp\htdocs\New folder"
sizes = {}
for c in clips:
    p = c['file_path']
    if p.startswith(('http://', 'https://')):
        # It's a URL
        real_path = os.path.join(base_dir, p.split('New%20folder/')[-1].replace('/', os.sep))
    elif not os.path.isabs(p):
        real_path = os.path.join(base_dir, p)
    else:
        real_path = p
    
    exists = os.path.exists(real_path)
    size = os.path.getsize(real_path) if exists else -1
    sizes[size] = sizes.get(size, 0) + 1
    if c['clip_index'] <= 5 or c['clip_index'] >= 38:
        print(f"Clip #{c['clip_index']:2d} (ID: {c['id'][:8]}): size={size} bytes, file={os.path.basename(real_path)}, exists={exists}")

print("\n=== FILE SIZE DISTRIBUTION ===")
for s, count in sorted(sizes.items()):
    print(f"  Size {s} bytes: {count} files")

# Inspect the first clip in detail with ffprobe
if clips:
    first_path = os.path.join(base_dir, clips[0]['file_path'])
    print(f"\n=== FFPROBE INSPECTION of {first_path} ===")
    res = subprocess.run(['ffprobe', '-v', 'error', '-show_format', '-show_streams', '-of', 'json', first_path], capture_output=True, text=True)
    print("Return code:", res.returncode)
    print("STDOUT:", res.stdout[:500])
    print("STDERR:", res.stderr[:500])
    
    # Read the raw bytes of first_path
    with open(first_path, 'rb') as fp:
        raw = fp.read()
    print("Raw file length:", len(raw))
    print("Raw hex:", raw[:64].hex())
    print("Raw text repr:", repr(raw[:64]))
