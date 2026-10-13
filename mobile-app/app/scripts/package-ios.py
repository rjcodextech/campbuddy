"""Zips the iOS project so it can be opened on a Mac (Xcode) or handed to Codemagic.

    python mobile-app/app/scripts/package-ios.py

An .ipa can only be built on macOS with Apple signing, so this is the closest a
Windows machine gets: the whole Xcode project, the plugin sources it points to
(ios/App/CapApp-SPM refers to ../../../node_modules), the bundled web files and
a short README. GoogleService-Info.plist is included when it is in place.
"""

import json
import zipfile
from pathlib import Path

APP = Path(__file__).resolve().parent.parent
OUT = APP / "release"
version = json.loads((APP / "package.json").read_text(encoding="utf-8"))["version"]
target = OUT / f"CampBuddy-{version}-ios-xcode-project.zip"
OUT.mkdir(exist_ok=True)

SKIP = ("Pods/", "build/", "DerivedData/", ".DS_Store", "xcuserdata/")

README = f"""CampBuddy {version} - iOS project (made on Windows, built on a Mac)

1. Unzip, then open ios/App/App.xcodeproj in Xcode 16 or newer.
2. Xcode > the App target > Signing & Capabilities: pick your Team.
   Capabilities already declared: Push Notifications, Associated Domains (campbuddy.club).
3. File > Add Package Dependencies...
   https://github.com/firebase/firebase-ios-sdk  ->  add FirebaseMessaging to the App target.
   (AppDelegate.swift turns push on by itself once the package and GoogleService-Info.plist are there.)
4. Product > Archive > Distribute App > App Store Connect (TestFlight).

Or skip the Mac: Codemagic builds it from the repo with codemagic.yaml.
Details: mobile-app/SETUP.md section 4.
"""


def wanted(path):
    posix = path.as_posix()
    return not any(part in posix for part in SKIP)


with zipfile.ZipFile(target, "w", zipfile.ZIP_DEFLATED) as zf:
    for folder in ("ios", "www"):
        for path in sorted((APP / folder).rglob("*")):
            if path.is_file() and wanted(path):
                zf.write(path, path.relative_to(APP).as_posix())

    # The plugin sources the Swift package points at.
    for name in ("capacitor.config.json", "package.json"):
        zf.write(APP / name, name)
    for plugin in sorted((APP / "node_modules").glob("@*/*")):
        if plugin.name in ("android", "cli"):
            continue
        for path in sorted(plugin.rglob("*")):
            rel = path.relative_to(APP).as_posix()
            if path.is_file() and wanted(path) and "/android/" not in rel and "/node_modules/" not in rel.replace("node_modules/@", "", 1):
                zf.write(path, rel)

    zf.writestr("README-IOS.txt", README)

print(f"{target}  ({target.stat().st_size / 1024 / 1024:.1f} MB)")
