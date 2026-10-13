"""Automated smoke test of the installed CampBuddy app on a USB-connected Android phone.

    python mobile-app/app/scripts/device_smoke.py [--adb PATH] [--apk PATH] [--skip-install] [--offline]

Drives the real app with adb (taps found through the UI tree, like a user),
takes a screenshot at each step into scripts/device-smoke-out/, and fails on
any crash (FATAL EXCEPTION / ANR / app process gone) in logcat. Needs USB
debugging on and the phone unlocked.
"""

import argparse
import os
import re
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from pathlib import Path

HERE = Path(__file__).resolve().parent
APP = HERE.parent
PKG = "club.campbuddy.app"
OUT = HERE / "device-smoke-out"

parser = argparse.ArgumentParser()
parser.add_argument("--adb", default=str(APP.parent / "Tools/android-build/sdk/platform-tools/adb.exe"))
parser.add_argument("--apk", default=str(APP / "release/CampBuddy-1.0.0-debug.apk"))
parser.add_argument("--skip-install", action="store_true")
parser.add_argument("--offline", action="store_true", help="also test with airplane mode on")
args = parser.parse_args()

results = []


ADB_HOME = str(APP.parent / "Tools/adb-home")  # adb keeps its key inside the project (adb-home/.android), not in the user folder
ENV = {**os.environ, "USERPROFILE": ADB_HOME, "HOME": ADB_HOME}


def adb(*a, check=True, binary=False):
    p = subprocess.run([args.adb, *a], capture_output=True, timeout=120, env=ENV)
    if check and p.returncode != 0:
        raise RuntimeError(p.stderr.decode(errors="replace"))
    return p.stdout if binary else p.stdout.decode(errors="replace")


def shot(name):
    OUT.mkdir(exist_ok=True)
    (OUT / f"{name}.png").write_bytes(adb("exec-out", "screencap", "-p", binary=True))


def ui_nodes():
    adb("shell", "uiautomator", "dump", "/sdcard/ui.xml", check=False)
    xml = adb("shell", "cat", "/sdcard/ui.xml", check=False)
    try:
        root = ET.fromstring(xml[xml.index("<"):])
    except Exception:
        return []
    nodes = []
    for n in root.iter("node"):
        m = re.match(r"\[(\d+),(\d+)\]\[(\d+),(\d+)\]", n.get("bounds", ""))
        if m:
            x1, y1, x2, y2 = map(int, m.groups())
            nodes.append({"text": n.get("text", ""), "desc": n.get("content-desc", ""), "box": (x1, y1, x2, y2), "click": n.get("clickable") == "true"})
    return nodes


def find(text, nodes=None):
    needle = text.lower()
    for n in nodes if nodes is not None else ui_nodes():
        if needle in n["text"].lower() or needle in n["desc"].lower():
            return n
    return None


def wait_for(text, seconds=20):
    end = time.time() + seconds
    while time.time() < end:
        n = find(text)
        if n:
            return n
        time.sleep(1.5)
    return None


def tap(node):
    x1, y1, x2, y2 = node["box"]
    adb("shell", "input", "tap", str((x1 + x2) // 2), str((y1 + y2) // 2))


def step(name, ok, detail=""):
    results.append((name, ok, detail))
    print(("PASS " if ok else "FAIL ") + name + (f"  ({detail})" if detail else ""))


def crashed():
    log = adb("logcat", "-d", "-v", "brief", check=False)
    bad = [l for l in log.splitlines() if ("FATAL EXCEPTION" in l or f"ANR in {PKG}" in l or (f"Process {PKG}" in l and "has died" in l))]
    return bad


def tap_text(label, seconds=15, shot_name=None):
    n = wait_for(label, seconds)
    if not n:
        step(f"tap {label}", False, "not found on screen")
        if shot_name:
            shot(shot_name + "-missing")
        return False
    tap(n)
    time.sleep(2.5)
    if shot_name:
        shot(shot_name)
    step(f"tap {label}", True)
    return True


# ---- setup ---------------------------------------------------------------------
devices = [l for l in adb("devices").splitlines()[1:] if l.strip().endswith("device")]
if not devices:
    sys.exit("No phone found: plug it in, enable USB debugging, allow this computer.")

if not args.skip_install:
    out = adb("install", "-r", args.apk, check=False)
    step("install apk", "Success" in out, out.strip().splitlines()[-1] if out.strip() else "")

adb("shell", "svc", "power", "stayon", "usb", check=False)  # screen stays on while on USB; put back at the end
adb("shell", "input", "keyevent", "KEYCODE_WAKEUP")
adb("shell", "wm", "dismiss-keyguard", check=False)
adb("shell", "am", "force-stop", PKG)
adb("logcat", "-c")
adb("shell", "monkey", "-p", PKG, "-c", "android.intent.category.LAUNCHER", "1", check=False)
time.sleep(6)
shot("01-launch")

# ---- the picker ------------------------------------------------------------------
step("app is running", bool(adb("shell", "pidof", PKG, check=False).strip()))
picker = wait_for("Choose your WordCamp", 20)
if picker is None:
    # The WebView exposes its accessibility tree lazily; one fresh start usually settles it.
    adb("shell", "am", "force-stop", PKG)
    adb("shell", "monkey", "-p", PKG, "-c", "android.intent.category.LAUNCHER", "1", check=False)
    time.sleep(6)
    picker = wait_for("Choose your WordCamp", 25)
step("picker shows", picker is not None)

# ---- open an event --------------------------------------------------------------------
if tap_text("Choose your WordCamp", shot_name="02-choose"):
    opener = None
    for _ in range(8):
        opener = next((n for n in ui_nodes() if n["text"].lower().startswith("open ") or "view schedule" in n["text"].lower()), None)
        if opener:
            break
        adb("shell", "input", "swipe", "540", "1900", "540", "700", "300")
        time.sleep(1.5)
    if opener:
        tap(opener)
        time.sleep(4)
        shot("03-event-home")
        step("event opens", wait_for("My Day", 20) is not None)
    else:
        step("event opens", False, "no Open button for a WordCamp (none live right now?)")
        shot("03-no-event")

# ---- every tab (and save a session on My Day) ---------------------------------------------
for tab in ("My Day", "Quest", "Contribute", "Explore", "Camp Card", "Home"):
    if not find(tab):
        continue
    tap_text(tab, 5, "tab-" + tab.lower().replace(" ", "-"))
    if tab == "My Day":
        nodes = ui_nodes()
        OUT.mkdir(exist_ok=True)
        (OUT / "my-day-nodes.txt").write_text(chr(10).join(f"{n['click']} {n['box']} text={n['text'][:50]!r} desc={n['desc'][:50]!r}" for n in nodes), encoding="utf-8")
        # The session stars carry no label for the accessibility tree: they are the small
        # square buttons at the right edge of a session row.
        star = next((n for n in nodes if n["click"] and n["box"][0] > 800 and 100 < n["box"][2] - n["box"][0] < 170 and n["box"][1] > 900), None)
        if star:
            tap(star)
            time.sleep(2)
            shot("save-session-asks")
            for answer in ("OK", "Allow", "ALLOW", "While using the app"):
                n = find(answer)
                if n:
                    tap(n)
                    time.sleep(2)
            shot("save-session")
            alarms = adb("shell", "dumpsys", "alarm", check=False)
            step("save a session", True, "reminder scheduled with the OS" if PKG in alarms else "no OS alarm seen (permission may have been declined)")
        else:
            step("save a session", False, "no star found (see my-day-nodes.txt)")

# ---- background / foreground ---------------------------------------------------------------
adb("shell", "input", "keyevent", "KEYCODE_HOME")
time.sleep(3)
adb("shell", "monkey", "-p", PKG, "-c", "android.intent.category.LAUNCHER", "1", check=False)
time.sleep(4)
shot("resume")
step("survives background and resume", bool(adb("shell", "pidof", PKG, check=False).strip()))

# ---- back button ------------------------------------------------------------------------------
adb("shell", "input", "keyevent", "KEYCODE_BACK")
time.sleep(2)
shot("back")
step("back button does not crash", True)
adb("shell", "monkey", "-p", PKG, "-c", "android.intent.category.LAUNCHER", "1", check=False)
time.sleep(3)

# ---- offline ----------------------------------------------------------------------------------------
if args.offline:
    adb("shell", "cmd", "connectivity", "airplane-mode", "enable", check=False)
    time.sleep(5)
    for tab in ("My Day", "Home"):
        if find(tab):
            tap_text(tab, 5)
    shot("offline")
    page_ok = bool(find("My Day") or find("Home") or find("offline") or find("Try again"))
    step("offline: a page or the offline screen shows", page_ok)
    adb("shell", "cmd", "connectivity", "airplane-mode", "disable", check=False)
    time.sleep(6)

# ---- verdict ---------------------------------------------------------------------------------------------
bad = crashed()
step("no crash in logcat", not bad, bad[0][:140] if bad else "")
step("app still running at the end", bool(adb("shell", "pidof", PKG, check=False).strip()))
shot("99-end")
adb("shell", "svc", "power", "stayon", "false", check=False)

failed = [r for r in results if not r[1]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed. Screenshots: {OUT}")
sys.exit(1 if failed else 0)
