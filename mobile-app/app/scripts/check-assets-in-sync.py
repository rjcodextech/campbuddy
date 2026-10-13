"""After make-assets.py has run: every generated image must show the same pixels as
the committed one (PNG file bytes may differ between Pillow versions, the picture may not)."""

import io
import subprocess
import sys

from PIL import Image

changed = subprocess.run(["git", "diff", "--name-only", "--", "mobile-app/app"], capture_output=True, text=True, check=True).stdout.split()
bad = []
for path in changed:
    if not path.lower().endswith(".png"):
        continue  # only the generated pictures matter here
    old = subprocess.run(["git", "show", f"HEAD:{path}"], capture_output=True, check=True).stdout
    a = Image.open(io.BytesIO(old)).convert("RGBA")
    b = Image.open(path).convert("RGBA")
    if a.size != b.size or a.tobytes() != b.tobytes():
        bad.append(path)

if bad:
    print("Out of date with the website logo: " + ", ".join(bad))
    print("Run mobile-app/app/scripts/make-assets.py and commit the result.")
    sys.exit(1)
print("Icons and splash match the website logo.")
