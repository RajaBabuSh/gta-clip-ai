import urllib.request
import urllib.parse
import http.cookiejar
import re

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# 1. Fetch login page to get CSRF token
login_url = 'http://localhost/New%20folder/login.php'
req = urllib.request.Request(login_url, headers={'User-Agent': 'Mozilla/5.0'})
with opener.open(req) as resp:
    html = resp.read().decode('utf-8')

csrf = re.search(r'name="csrf_token" value="([^"]+)"', html).group(1)

# 2. Login as rajababu22
post_data = urllib.parse.urlencode({
    'login': 'rajababu22',
    'password': 'password123',
    'csrf_token': csrf
}).encode('utf-8')
req = urllib.request.Request(login_url, data=post_data, headers={'User-Agent': 'Mozilla/5.0'})
with opener.open(req) as resp:
    pass

# 3. Access my-clips.php
clips_url = 'http://localhost/New%20folder/my-clips.php'
req = urllib.request.Request(clips_url, headers={'User-Agent': 'Mozilla/5.0'})
with opener.open(req) as resp:
    content = resp.read().decode('utf-8')

video_tags = re.findall(r'<video[^>]+>', content)
posters = re.findall(r'poster="([^"]+)"', content)
sources = re.findall(r'<source src="([^"]+)"', content)

print(f"HTML response length: {len(content)} bytes")
print("Page title:", re.findall(r"<title>([^<]+)</title>", content))
if "Sign In" in content:
    print("User was NOT logged in - redirected to Sign In.")
else:
    print("User IS logged in!")
    clips_match = re.findall(r"My Clips Library \(([0-9]+)\)", content)
    print("Header count:", clips_match)
print(f"Video players rendered: {len(video_tags)}")
print(f"Video posters rendered: {len(posters)}")
print(f"Video sources rendered: {len(sources)}")
if posters:
    print("Sample poster URL:", posters[0])
if sources:
    print("Sample source URL:", sources[0])
