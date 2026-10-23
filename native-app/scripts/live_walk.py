"""Walks the release app on a real phone against production and reports crashes / empty screens."""
import os, re, subprocess, sys, time
import xml.etree.ElementTree as ET

ROOT = r"E:\Sites\InHouse\campbuddy\V2"
ADB = ROOT + r"\native-app\Tools\android-build\sdk\platform-tools\adb.exe"
ENV = {**os.environ, "USERPROFILE": ROOT + r"\native-app\Tools\adb-home", "HOME": ROOT + r"\native-app\Tools\adb-home"}
SERIAL = sys.argv[1] if len(sys.argv) > 1 else "ZD2228YJHF"
PKG = "club.campbuddy.app"
OUT = ROOT + r"\native-app\scripts\emulator-smoke-out"


def adb(*a, binary=False):
    p = subprocess.run([ADB, "-s", SERIAL] + list(a), capture_output=True, timeout=120, env=ENV)
    return p.stdout if binary else p.stdout.decode(errors="replace")


def nodes():
    for _ in range(3):
        adb("shell", "uiautomator", "dump", "/sdcard/ui.xml")
        xml = adb("shell", "cat", "/sdcard/ui.xml")
        try:
            root = ET.fromstring(xml[xml.index("<"):])
        except Exception:
            time.sleep(1.5)
            continue
        out = []
        for n in root.iter("node"):
            m = re.match(r"\[(\d+),(\d+)\]\[(\d+),(\d+)\]", n.get("bounds", ""))
            if m:
                out.append({"t": n.get("text", ""), "d": n.get("content-desc", ""), "b": tuple(map(int, m.groups()))})
        return out
    return []


def find(text, exact=False):
    t = text.lower()
    for n in nodes():
        for h in (n["t"].lower(), n["d"].lower()):
            if (t == h) if exact else (t in h):
                return n
    return None


def wait(text, secs=15, exact=False):
    end = time.time() + secs
    while time.time() < end:
        n = find(text, exact)
        if n:
            return n
        time.sleep(1)
    return None


def tap(n):
    x0, y0, x1, y1 = n["b"]
    adb("shell", "input", "tap", str((x0 + x1) // 2), str((y0 + y1) // 2))
    time.sleep(1.2)


def scroll(times=1):
    for _ in range(times):
        adb("shell", "input", "swipe", "540", "1700", "540", "700", "350")
        time.sleep(0.8)


results = []


def step(name, ok):
    results.append((name, bool(ok)))
    print(("PASS " if ok else "FAIL ") + name, flush=True)


adb("logcat", "-c")
adb("shell", "input", "keyevent", "KEYCODE_WAKEUP")
adb("shell", "am", "force-stop", PKG)
adb("shell", "monkey", "-p", PKG, "-c", "android.intent.category.LAUNCHER", "1")
step("picker loads the live WordCamps", wait("Choose your WordCamp", 20) is not None)
scroll(3)
step("Rajasthan shows as Completed", wait("Completed", 10) is not None)
step("Completed card has the data button", find("Save your WordCamp Data") is not None)
n = find("WordCamp Rajasthan 2026")
if n:
    tap(n)
step("event opens on Home", wait("Home", 15, exact=True) is not None)
for tab, expect in [("My Day", "Full schedule"), ("Quest", "Things to do"), ("Contribute", "Where could you help?"), ("Explore", "Explore"), ("Camp Card", "Camp Card")]:
    t = wait(tab, 8, exact=True)
    if t:
        tap(t)
    step(f"{tab} tab opens", wait(expect, 15) is not None)
t = wait("Explore", 5, exact=True)
if t:
    tap(t)
for sub, expect in [("Sponsors", "Sponsors help keep"), ("Deals", "Details"), ("Free Steals", "by "), ("Info", "venue")]:
    s = wait(sub, 8, exact=True)
    if s:
        tap(s)
    time.sleep(2)
    step(f"Explore: {sub} shows content", find(expect) is not None)
s = wait("Free Steals", 5, exact=True)
if s:
    tap(s)
    time.sleep(2)
    first = find("by Deepak")
    if first:
        tap(first)
        step("Free Steal sheet shows the host line", wait("Opens ", 8) is not None)
        adb("shell", "input", "keyevent", "KEYCODE_BACK")
        time.sleep(1)
p = wait("People", 5, exact=True)
if p:
    tap(p)
scroll(2)
step("People list has + Meet", wait("+ Meet", 20) is not None)
m = find("+ Meet")
if m:
    tap(m)
    step("Meet sheet opens", wait("Save to My schedule", 8) is not None)
    adb("shell", "input", "keyevent", "KEYCODE_BACK")
    time.sleep(1)
log = adb("logcat", "-d", "-s", "AndroidRuntime:E", "ActivityManager:E")
crash = [l for l in log.splitlines() if "FATAL" in l or ("Process: " + PKG) in l]
step("no crash in the log", not crash)
if crash:
    print("\n".join(crash[:10]))
adb("exec-out", "screencap", "-p")
bad = [n for n, ok in results if not ok]
print(f"\n{len(results) - len(bad)} passed, {len(bad)} failed")
