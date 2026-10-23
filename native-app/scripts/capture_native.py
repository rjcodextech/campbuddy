"""Shoots one event's screens in the native app on a phone, scrolling down a page at a time, into
pwa-reference/<out>/n-<screen>-<n>.png (stops when the screen stops moving).

    python native-app/scripts/capture_native.py --serial <phone> --slug wordcamp-rajasthan-2026 --out live-raj home my-day ...
"""

import argparse
import hashlib
import os
import subprocess
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent
TOOLS = HERE.parent / "Tools"

parser = argparse.ArgumentParser()
parser.add_argument("screens", nargs="*", default=["home", "my-day", "quest", "contribute", "explore", "camp-card"])
parser.add_argument("--serial", required=True)
parser.add_argument("--slug", required=True)
parser.add_argument("--out", default="live-raj")
parser.add_argument("--package", default="club.campbuddy.app")
parser.add_argument("--pages", type=int, default=5)
args = parser.parse_args()

OUT = HERE / "pwa-reference" / args.out
OUT.mkdir(parents=True, exist_ok=True)
env = {**os.environ, "USERPROFILE": str(TOOLS / "adb-home"), "HOME": str(TOOLS / "adb-home"), "MSYS_NO_PATHCONV": "1"}
adb = [str(TOOLS / "android-build/sdk/platform-tools/adb.exe"), "-s", args.serial]


def run(*a, binary=False):
    p = subprocess.run(adb + list(a), capture_output=True, timeout=90, env=env)
    return p.stdout if binary else p.stdout.decode("utf-8", "replace")


run("shell", "input", "keyevent", "KEYCODE_WAKEUP")
for screen in args.screens:
    path = "" if screen == "home" else "/" + screen.replace("explore-", "explore?tab=")
    run("shell", "am", "force-stop", args.package)
    run("shell", "am", "start", "-a", "android.intent.action.VIEW", "-d", f"https://campbuddy.club/event/{args.slug}{path}", "-p", args.package)
    time.sleep(9)
    last = None
    for i in range(args.pages):
        shot = run("exec-out", "screencap", "-p", binary=True)
        digest = hashlib.md5(shot).hexdigest()
        if digest == last:
            break
        last = digest
        (OUT / f"n-{screen}-{i}.png").write_bytes(shot)
        run("shell", "input", "swipe", "540", "1900", "540", "500", "500")
        time.sleep(1.2)
    print(screen, "pages", i + 1)
