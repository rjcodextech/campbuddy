"""Shoots the native app on a phone for each screen of one event and puts it next to the website's own screenshot
(pwa-reference/live/<screen>.png, made by the Chrome script at the phone's width), so the two can be compared by eye.

    python native-app/scripts/compare_live.py --serial <phone> [--slug wordcamp-bengaluru-2026] [screens...]

Screens: home my-day quest contribute explore camp-card. Output: pwa-reference/live/cmp-<screen>.png (website | app).
"""

import argparse
import subprocess
import time
from pathlib import Path

from PIL import Image

HERE = Path(__file__).resolve().parent
TOOLS = HERE.parents[1] / "mobile-app" / "Tools"
LIVE = HERE / "pwa-reference" / "live"

parser = argparse.ArgumentParser()
parser.add_argument("screens", nargs="*", default=["home", "my-day", "quest", "contribute", "explore", "camp-card"])
parser.add_argument("--serial", required=True)
parser.add_argument("--slug", default="wordcamp-bengaluru-2026")
parser.add_argument("--package", default="club.campbuddy.app")
parser.add_argument("--wait", type=float, default=8)
args = parser.parse_args()

env = {**__import__("os").environ, "USERPROFILE": str(TOOLS / "adb-home"), "HOME": str(TOOLS / "adb-home")}
adb = [str(TOOLS / "android-build/sdk/platform-tools/adb.exe"), "-s", args.serial]


def run(*a, binary=False):
    p = subprocess.run(adb + list(a), capture_output=True, timeout=90, env=env)
    return p.stdout if binary else p.stdout.decode("utf-8", "replace")


run("shell", "input", "keyevent", "KEYCODE_WAKEUP")
for screen in args.screens:
    path = "" if screen == "home" else "/" + screen
    run("shell", "am", "start", "-a", "android.intent.action.VIEW", "-d", f"https://campbuddy.club/event/{args.slug}{path}", "-p", args.package)
    time.sleep(args.wait)
    app = LIVE / f"N-{screen}.png"
    app.write_bytes(run("exec-out", "screencap", "-p", binary=True))
    web = LIVE / f"{screen}.png"
    if not web.exists():
        continue
    left, right = Image.open(web).convert("RGB"), Image.open(app).convert("RGB")
    height = 2000
    left = left.resize((int(left.width * height / left.height), height))
    right = right.resize((int(right.width * height / right.height), height))
    both = Image.new("RGB", (left.width + right.width + 20, height), (40, 40, 40))
    both.paste(left, (0, 0))
    both.paste(right, (left.width + 20, 0))
    both.save(LIVE / f"cmp-{screen}.png")
    print("wrote", f"cmp-{screen}.png")
