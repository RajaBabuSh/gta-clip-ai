import urllib.request
import urllib.parse
import re

url = "http://localhost/New%20folder/admin/login.php"

req = urllib.request.Request(url)
with urllib.request.urlopen(req) as resp:
    html = resp.read().decode('utf-8', errors='ignore')

print("=== VERIFYING ADMIN LOGIN CREDENTIALS PAGE ===")
print("1. Status Code:", resp.getcode())

# Requirement 1: Clearly visible card titled "Admin Login Credentials" in top-right
req1 = "Admin Login Credentials" in html and "admin-credentials-card" in html
print(f"Requirement 1 (Card titled 'Admin Login Credentials'): {'PASS' if req1 else 'FAIL'}")

# Requirement 2: Show Admin Login URL, Admin Username / Email, Password setup status
req2_url = "Admin Login URL" in html and "admin-url-display" in html
req2_user = "Admin Username / Email" in html and "superadmin" in html and "admin@gtaclip.ai" in html
req2_status = "Password Setup Status" in html and ("Active & Encrypted (Bcrypt)" in html or "Temporary Password" in html)
print(f"Requirement 2a (Admin Login URL): {'PASS' if req2_url else 'FAIL'}")
print(f"Requirement 2b (Admin Username / Email): {'PASS' if req2_user else 'FAIL'}")
print(f"Requirement 2c (Password Setup Status): {'PASS' if req2_status else 'FAIL'}")

# Requirement 3: Copy Username button
req3 = "btn-copy-user" in html and "Copy Username" in html and "copyAdminUsername()" in html
print(f"Requirement 3 (Copy Username button): {'PASS' if req3 else 'FAIL'}")

# Requirement 4: Copy Login URL button
req4 = "btn-copy-url" in html and "Copy Login URL" in html and "copyLoginUrl()" in html
print(f"Requirement 4 (Copy Login URL button): {'PASS' if req4 else 'FAIL'}")

# Requirement 5: Change Password button
req5 = "openChangePasswordModal()" in html and "changePasswordModal" in html
print(f"Requirement 5 (Change Password button & Modal): {'PASS' if req5 else 'FAIL'}")

# Requirement 6 & 7: Never display existing password hashes or permanently expose plaintext passwords
req7 = "$2y$10$" not in html and "$2a$" not in html and "$argon2" not in html
print(f"Requirement 7 (No password hashes exposed in HTML/JS): {'PASS' if req7 else 'FAIL'}")

# Requirement 9: Top-right account menu
with open(r"C:\xampp\htdocs\New folder\views\partials\admin_account_menu.php", "r", encoding="utf-8") as f:
    menu_code = f.read()
req9 = "adminAccountMenuBtn" in menu_code and "adminAccountDropdownMenu" in menu_code and "get_auth_admin" in menu_code
print(f"Requirement 9 (Admin Top-Right Account Menu): {'PASS' if req9 else 'FAIL'}")

# Requirement 10: Dark purple SaaS theme
req10 = "#07090e" in html and "rgba(139, 92, 246" in html and "rgba(15, 18, 35" in html
print(f"Requirement 10 (Dark Purple SaaS Theme): {'PASS' if req10 else 'FAIL'}")

all_pass = all([req1, req2_url, req2_user, req2_status, req3, req4, req5, req7, req9, req10])
print("\nOVERALL TEST RESULT:", "ALL REQUIREMENTS VERIFIED AND PASSED!" if all_pass else "SOME CHECKS FAILED")
