import sys
import subprocess

print("Current sys.executable:", sys.executable)
try:
    r1 = subprocess.run(["python", "--version"], capture_output=True, text=True)
    print("subprocess with 'python': returncode=", r1.returncode, "out=", r1.stdout.strip(), "err=", r1.stderr.strip())
except Exception as e:
    print("subprocess with 'python' FAILED:", type(e), e)

try:
    r2 = subprocess.run([sys.executable, "--version"], capture_output=True, text=True)
    print("subprocess with sys.executable: returncode=", r2.returncode, "out=", r2.stdout.strip())
except Exception as e:
    print("subprocess with sys.executable FAILED:", type(e), e)
