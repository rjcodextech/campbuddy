"""Copies the website's own artwork into the native app, so the app can never look different from the PWA
(tab icons, the CampBuddy wordmark, the app icon). Run it after the website art changes, commit the result;
tests/Feature/NativeKotlinSyncTest.php fails when the copies are out of date.
"""

import hashlib
import json
import shutil
import subprocess
from pathlib import Path

REPO = Path(__file__).resolve().parents[2]
MEDIA = REPO / "public" / "media"
OUT = REPO / "native-app" / "shared" / "src" / "commonMain" / "composeResources" / "drawable"

# (website file, name in the app: lowercase letters, digits and underscores)
FILES = [
    ("logo-wordmark-sm.png", "wordmark.png"),
    ("icons/icon-192.png", "app_icon.png"),
]

OUT.mkdir(parents=True, exist_ok=True)
for source, target in FILES:
    shutil.copyfile(MEDIA / source, OUT / target)

# Android cannot draw SVG files, so the tab drawings are rendered to PNG; their SVG hashes are recorded for the sync test.
SVGS = ["home", "my-day", "quest", "contribute", "explore", "camp-card"]
ART = ["people", "first-badge", "avatar"]
PATTERNS = ["event", "guide", "people", "contribute", "icons", "schedule"]
subprocess.run(["node", str(Path(__file__).with_name("render-nav-icons.mjs")), str(MEDIA), str(OUT)], check=True)
hashes = {name: hashlib.md5((MEDIA / f"{name}.svg").read_bytes()).hexdigest() for name in SVGS}
hashes.update({"illustrations/" + name: hashlib.md5((MEDIA / "illustrations" / f"{name}.svg").read_bytes()).hexdigest() for name in ART})
(REPO / "native-app" / "web-art.json").write_text(json.dumps(hashes, indent=2) + chr(10))
hashes.update({"pattern-" + name: hashlib.md5((MEDIA / f"pattern-{name}.svg").read_bytes()).hexdigest() for name in PATTERNS})
(REPO / "native-app" / "web-art.json").write_text(json.dumps(hashes, indent=2) + chr(10))
print(f"{len(FILES)} files copied and {len(SVGS)} icons rendered to {OUT}")
