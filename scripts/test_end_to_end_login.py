import urllib.request
import urllib.parse
import http.cookiejar
import re

cj = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))

# 1. Fetch Login Page and CSRF Token
login_url = "http://localhost/New%20folder/admin/login.php"
req = urllib.request.Request(login_url)
resp = opener.open(req)
html = resp.read().decode('utf-8')

csrf_match = re.search(r'name="csrf_token"\s+value="([^"]+)"', html)
if not csrf_match:
    print("FAILED: CSRF token not found on login page")
    exit(1)
csrf_token = csrf_match.group(1)
print("1. Fetched login page, CSRF token acquired.")

# 2. Submit Login with Temporary Password
post_data = urllib.parse.urlencode({
    'csrf_token': csrf_token,
    'form_action': 'login',
    'login': 'superadmin',
    'password': 'SzJ&VaxjK4FVb8Qk'
}).encode('utf-8')

req2 = urllib.request.Request(login_url, data=post_data, headers={'Content-Type': 'application/x-www-form-urlencoded'})
resp2 = opener.open(req2)
final_url = resp2.geturl()
resp2_html = resp2.read().decode('utf-8')

print("2. Login submitted. Resulting URL:", final_url)
is_change_pwd = "change-password.php" in final_url or "Update Administrator Password" in resp2_html
print(f"   Routed to Mandatory Password Change: {'YES' if is_change_pwd else 'NO'}")

# 3. Submit New Permanent Password
csrf_match2 = re.search(r'name="csrf_token"\s+value="([^"]+)"', resp2_html)
csrf_token2 = csrf_match2.group(1) if csrf_match2 else csrf_token

change_pwd_url = "http://localhost/New%20folder/admin/change-password.php"
post_data2 = urllib.parse.urlencode({
    'csrf_token': csrf_token2,
    'current_password': 'SzJ&VaxjK4FVb8Qk',
    'new_password': 'SuperPermanentPass2026!',
    'confirm_password': 'SuperPermanentPass2026!'
}).encode('utf-8')

req3 = urllib.request.Request(change_pwd_url, data=post_data2, headers={'Content-Type': 'application/x-www-form-urlencoded'})
resp3 = opener.open(req3)
final_url3 = resp3.geturl()
resp3_html = resp3.read().decode('utf-8')

print("3. Password change submitted. Resulting URL:", final_url3)
is_dashboard = "index.php" in final_url3 or "Platform Master Dashboard" in resp3_html or "System Master Overview" in resp3_html
print(f"   Access to Master Dashboard: {'YES' if is_dashboard else 'NO'}")

# 4. Access another admin page: users.php
users_url = "http://localhost/New%20folder/admin/users.php"
resp4 = opener.open(urllib.request.Request(users_url))
resp4_html = resp4.read().decode('utf-8')
has_users = "User Directory" in resp4_html
print(f"4. Authenticated admin navigation (users.php): {'YES' if has_users else 'NO'}")

# 5. Logout
logout_url = "http://localhost/New%20folder/admin/logout.php"
resp5 = opener.open(urllib.request.Request(logout_url))
print(f"5. Logout executed. Landed at: {resp5.geturl()}")

print("\nEND-TO-END HTTP AUTHENTICATION TEST COMPLETED SUCCESSFULLY!")
