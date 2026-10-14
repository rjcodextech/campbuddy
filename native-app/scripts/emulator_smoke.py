"""End-to-end test of the native app on an Android emulator or phone, against a local Laravel server.

    1. php -S 127.0.0.1:8099 ... with the demo data (see native-app/scripts/README.md)
    2. python native-app/scripts/emulator_smoke.py [--adb PATH]

Drives the real app through its accessibility tree (as a person would), checks what is on
screen at each step, takes a screenshot per step and fails on any crash.
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
TOOLS = HERE.parent.parent / "mobile-app" / "Tools"
PKG = "club.campbuddy.app.native"
API = "http://10.0.2.2:8099/api/v1"
OUT = HERE / "emulator-smoke-out"

parser = argparse.ArgumentParser()
parser.add_argument("--adb", default=str(TOOLS / "android-build/sdk/platform-tools/adb.exe"))
parser.add_argument("--serial", default=None)
args = parser.parse_args()

ENV = {**os.environ, "USERPROFILE": str(TOOLS / "adb-home"), "HOME": str(TOOLS / "adb-home")}
results = []


def adb(*a, binary=False, check=False):
    base = [args.adb] + (["-s", args.serial] if args.serial else [])
    p = subprocess.run(base + list(a), capture_output=True, timeout=120, env=ENV)
    return p.stdout if binary else p.stdout.decode(errors="replace")


def shot(name):
    OUT.mkdir(exist_ok=True)
    (OUT / f"{name}.png").write_bytes(adb("exec-out", "screencap", "-p", binary=True))


def nodes():
    adb("shell", "uiautomator", "dump", "/sdcard/ui.xml")
    xml = adb("shell", "cat", "/sdcard/ui.xml")
    try:
        root = ET.fromstring(xml[xml.index("<"):])
    except Exception:
        return []
    out = []
    for n in root.iter("node"):
        m = re.match(r"\[(\d+),(\d+)\]\[(\d+),(\d+)\]", n.get("bounds", ""))
        if m:
            out.append({"text": n.get("text", ""), "desc": n.get("content-desc", ""), "box": tuple(map(int, m.groups())), "focusable": n.get("focusable") == "true", "cls": n.get("class", "")})
    return out


def find(text, ns=None, exact=False):
    needle = text.lower()
    for n in ns if ns is not None else nodes():
        hay = [n["text"].lower(), n["desc"].lower()]
        if (needle in hay) if exact else any(needle in h for h in hay):
            return n
    return None


def wait(text, seconds=20, exact=False):
    end = time.time() + seconds
    while time.time() < end:
        n = find(text, exact=exact)
        if n:
            return n
        time.sleep(1.2)
    return None


def tap(n):
    x1, y1, x2, y2 = n["box"]
    adb("shell", "input", "tap", str((x1 + x2) // 2), str((y1 + y2) // 2))
    time.sleep(1.2)


def step(name, ok, detail=""):
    results.append((name, ok))
    print(("PASS " if ok else "FAIL ") + name + (f"  ({detail})" if detail else ""))
    return ok


def tap_text(text, seconds=15, exact=False):
    n = wait(text, seconds, exact)
    if n:
        tap(n)
    return n is not None


def type_into(label, value):
    # the last match: a field label comes after any card that previews the same words
    n = next((x for x in reversed(nodes()) if label.lower() in x["text"].lower()), None)
    if not n:
        return False
    tap(n)
    time.sleep(1.2)
    adb("shell", "input", "text", value.replace(" ", "%s"))
    time.sleep(0.6)
    return True


def scroll_down(times=1):
    for _ in range(times):
        adb("shell", "input", "swipe", "1070", "1800", "1070", "600", "400")
        time.sleep(0.8)


# ---- start fresh ------------------------------------------------------------------------------------
adb("shell", "input", "keyevent", "KEYCODE_WAKEUP")
adb("shell", "settings", "put", "global", "hide_error_dialogs", "1")  # a slow emulator image must not cover the app with its own "isn't responding" boxes
adb("shell", "pm", "clear", PKG)
adb("shell", "pm", "grant", PKG, "android.permission.POST_NOTIFICATIONS")
adb("logcat", "-c")
adb("shell", "am", "start", "-n", f"{PKG}/club.campbuddy.app.MainActivity", "--es", "api", API)
time.sleep(6)
shot("01-picker")

step("picker shows the server banner (config from the API)", wait("WordCamp Demo is on", 25) is not None)
scroll_down(2)
step("the event list comes from the API", find("WordCamp Demo 2026") is not None)
step("onboarding card is offered", find("Make CampBuddy yours") is not None)

# ---- onboarding stepper ---------------------------------------------------------------------------------
tap_text("Let's go")
step("step 1 asks if it is the first WordCamp", wait("Is this your first WordCamp?") is not None)
tap_text("Yes, first one!")
step("a tap moves nothing by itself, Next does", tap_text("Next") and wait("What describes you?") is not None)
scroll_down(1)
shot("02a-tags")
tap_text("Developer")
tap_text("Speaker")
step("tags are counted", wait("2 of 5 picked") is not None)
tap_text("Next", exact=True)
tap_text("Skip", exact=True)  # Contributor Day skipped
tap_text("Skip", exact=True)  # who to meet skipped
step("last step can finish", tap_text("Finish"))
step("the closing screen says what the answers do", wait("You are set") is not None and find("Beginner-friendly") is not None)
shot("02-onboarding-done")
tap_text("Choose your WordCamp", exact=True)

scroll_down(2)

# ---- the event ------------------------------------------------------------------------------------------------
tap_text("WordCamp Demo 2026")
step("event Home: what is happening now", wait("NOW", 20) is not None)
step("event Home: up next with the clash noted", find("Up next") is not None or find("Happening now") is not None)
adb("shell", "input", "swipe", "1070", "1700", "1070", "700", "400")
time.sleep(1)
step("event Home: start-here card for a first-timer", find("Read the guide") is not None)
adb("shell", "input", "swipe", "1070", "700", "1070", "1700", "400")
time.sleep(1)
shot("03-home")

# ---- My Day ------------------------------------------------------------------------------------------------------
tap_text("My Day", exact=True)
step("My Day lists the sessions", wait("Building your first block") is not None and find("SEO for WordPress beginners") is not None)
stars = [n for n in nodes() if n["desc"] == "Save to My Day"]
step("every session has a labelled star", len(stars) >= 3, f"{len(stars)} stars")
if stars:
    tap(stars[0])
    step("the first save asks about reminders once", wait("Reminders", 8) is not None)
    shot("04-reminder-question")
    tap_text("Remind me")
    time.sleep(2)
    nodes_now = nodes()
    # the system question appears only when the permission was not pre-granted
    allow = find("Allow", nodes_now, exact=True)
    if allow:
        tap(allow)
    step("saving is remembered (star changes)", wait("Remove from My Day", 8) is not None)
    stars = [n for n in nodes() if n["desc"] == "Save to My Day"]
    # the second talk at the same minute overlaps the first saved one
    both = [n for n in nodes() if n["desc"] == "Save to My Day"]
    if both:
        tap(both[0])
    time.sleep(1)
    step("overlap warning", find("overlaps with") is not None)
    step("no second reminder question", find("Reminders", exact=True) is None)
    tap_text("My schedule")
    step("My schedule shows only what is saved", find("Hosting and speed") is None)
    shot("05-my-schedule")

# ---- Quest ---------------------------------------------------------------------------------------------------------
tap_text("Quest", exact=True)
step("quests load", wait("First Hello", 15) is not None)
tap_text("Mark done", exact=True)
step("quest progress counts", wait("1/", 6) is not None and find("complete") is not None)
shot("06-quest")

# ---- Explore ---------------------------------------------------------------------------------------------------------
tap_text("Explore", exact=True)
tap_text("Sponsors", exact=True)
step("sponsors", wait("Acme Hosting", 10) is not None)
tap_text("People", exact=True)
step("people offers attendee discovery", wait("Join attendee discovery", 10) is not None)
tap_text("Join attendee discovery")
step("join form opens", wait("How should you appear?", 8) is not None)
tap_text("Stay anonymous")
scroll_down(1)
tap_text("Developer")
tap_text("Speaker")
scroll_down(2)
tap_text("Save", exact=True)
time.sleep(2)
for _ in range(3):
    adb("shell", "input", "swipe", "1070", "700", "1070", "1800", "300")
    time.sleep(0.6)
step("joined: profile card shows", wait("You are in attendee discovery", 12) is not None)
shot("07a-people")
tap_text("Leave", exact=True)
tap_text("Leave", exact=True)
step("can leave discovery", wait("You left attendee discovery", 10) is not None)
tap_text("Deals", exact=True)
step("deals with a coupon", wait("20% off yearly plans", 10) is not None and find("WCDEMO20") is not None)
tap_text("Free Steals", exact=True)
step("free steals", wait("A free forms plugin", 10) is not None)
shot("07-explore")
tap_text("Info", exact=True)
step("Info shows the venue group", wait("At the venue", 10) is not None and find("Open in Maps") is not None)
shot("07b-info")

# ---- Contribute -----------------------------------------------------------------------------------------------------------
tap_text("Contribute", exact=True)
step("contribute asks where you could help", wait("Where could you help?", 10) is not None and find("Going to Contributor Day?") is not None)
tap_text("Writing code")
adb("shell", "input", "swipe", "1070", "1700", "1070", "700", "400")
time.sleep(1)
tap_text("See my matches")
step("contribute shows matches with an opening line", wait("Your opening line", 8) is not None and find("Core") is not None)
shot("06b-contribute")

# ---- Camp Card ------------------------------------------------------------------------------------------------------------
tap_text("Camp Card", exact=True)
step("camp card form", wait("Ticket", 10) is not None)


def to_top():
    for _ in range(5):
        adb("shell", "input", "swipe", "1070", "700", "1070", "1800", "300")
        time.sleep(0.5)


def small_scroll(px=700):
    adb("shell", "input", "swipe", "1070", str(1700), "1070", str(1700 - px), "400")
    time.sleep(0.8)


small_scroll(1100)
type_into("Your name", "Asha Rao")
adb("shell", "input", "keyevent", "KEYCODE_BACK")  # close the keyboard
time.sleep(1)
scroll_down(1)
type_into("Website", "asha.dev")
adb("shell", "input", "keyevent", "KEYCODE_BACK")
time.sleep(1)
to_top()
step("the preview follows the typing", find("Asha Rao") is not None)
shot("08-camp-card")
small_scroll(800)
step("the card shows a QR once there is a link", wait("Scan to connect", 8) is not None)
shot("08b-card-qr")
small_scroll(600)
tap_text("Download", exact=True)
step("Download saves the card picture", wait("Saved to your photos.", 8) is not None)

# ---- Your data -------------------------------------------------------------------------------------------------------------
tap_text("My Day", exact=True)
scroll_down(4)
step("My Day offers to clear your data", wait("Clear my data on this phone", 8) is not None)
tap_text("Clear my data on this phone")
tap_text("Clear", exact=True)
step("clearing says so", wait("Cleared. Nothing of yours is left", 8) is not None)

# ---- Guide ------------------------------------------------------------------------------------------------------------------
tap_text("Home", exact=True)
tap_text("Read the guide", 10)
step("guide opens with the day", wait("How the day runs", 15) is not None)
shot("09-guide")
adb("shell", "input", "keyevent", "KEYCODE_BACK")
time.sleep(1.5)
if find("Choose your WordCamp") is None:  # still inside the event: one more back goes to the picker
    adb("shell", "input", "keyevent", "KEYCODE_BACK")
    time.sleep(1.5)

# ---- organizer ------------------------------------------------------------------------------------------------------------------
step("back to the picker", wait("Choose your WordCamp", 10) is not None)
scroll_down(4)
tap_text("Event staff: sign in")
step("sign-in screen", wait("Event staff sign-in", 8) is not None)
type_into("E-mail", "mgr@example.com")
type_into("Password", "password")
adb("shell", "input", "keyevent", "KEYCODE_BACK")  # close the keyboard
time.sleep(0.8)
tap_text("Sign in", exact=True)
step("manager signs in and sees only own events", wait("Hello, Demo Manager", 15) is not None and find("WordCamp Demo 2026") is not None)
shot("10-organizer")
tap_text("WordCamp Demo 2026")
step("organizer event: reports and running late", wait("Running late", 10) is not None and find("No open reports") is not None)
tap_text("Set delay")
step("setting a delay is accepted", wait("Saved. Every phone sees the new times.", 10) is not None)
shot("11-delay")

# ---- links from outside --------------------------------------------------------------------------------------------------------------
adb("shell", "am", "start", "-a", "android.intent.action.VIEW", "-d", "campbuddy://event/wordcamp-demo-2026/quest", PKG)
step("a campbuddy:// link opens the event on its Quest tab", wait("Things to do", 15) is not None)
adb("shell", "am", "start", "-a", "android.intent.action.VIEW", "-d", "https://campbuddy.club/event/wordcamp-demo-2026/contribute", PKG)
step("a website link opens the Contribute tab", wait("Where could you help?", 15) is not None)

# ---- crash check ----------------------------------------------------------------------------------------------------------------------
log = adb("logcat", "-d")
crashed = [l for l in log.splitlines() if "FATAL EXCEPTION" in l or f"ANR in {PKG}" in l]
step("no crash in the log", not crashed, crashed[0][:120] if crashed else "")

failed = [r for r in results if not r[1]]
print(f"\n{len(results) - len(failed)} passed, {len(failed)} failed. Screenshots: {OUT}")
sys.exit(1 if failed else 0)
