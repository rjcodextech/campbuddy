#!/usr/bin/env bash
# Builds the native app for iPhone. Needs a Mac with Xcode; every step is explained in
# .claude/skills/campbuddy-docs/mobile/ios-build-on-mac.md
#   bash native-app/build-ios.sh check     what is installed and set up, nothing built
#   bash native-app/build-ios.sh open      makes the Xcode project and opens it (Run on your iPhone from there)
#   bash native-app/build-ios.sh sim       builds and starts the app in the iPhone simulator
#   bash native-app/build-ios.sh archive   a signed App Store build: native-app/iosApp/build/ipa/*.ipa
#   bash native-app/build-ios.sh upload    the same, sent straight to App Store Connect (TestFlight)
# Signing team: CAMPBUDDY_TEAM_ID=ABCDE12345 in native-app/iosApp/local.env (not in git).
# Like build-native.sh, every successful sim/archive/upload build moves the next version up by one.
set -euo pipefail

MODE="${1:-open}"
HERE="$(cd "$(dirname "$0")" && pwd)"
IOS="$HERE/iosApp"
OUT="$IOS/build"
PROJECT="$IOS/iosApp.xcodeproj"

fail() { echo "✗ $*" >&2; exit 1; }

[ "$(uname)" = "Darwin" ] || fail "This needs a Mac (macOS with Xcode)."
if [ -f "$IOS/local.env" ]; then
  set -a; . "$IOS/local.env"; set +a
fi
export CAMPBUDDY_TEAM_ID="${CAMPBUDDY_TEAM_ID:-}"

# Java for Gradle (17 or newer): JAVA_HOME, else Android Studio's own, else any installed JDK.
if [ -z "${JAVA_HOME:-}" ]; then
  for candidate in "/Applications/Android Studio.app/Contents/jbr/Contents/Home" "$(/usr/libexec/java_home -v 17+ 2>/dev/null || true)"; do
    if [ -n "$candidate" ] && [ -x "$candidate/bin/java" ]; then JAVA_HOME="$candidate"; break; fi
  done
fi
[ -n "${JAVA_HOME:-}" ] || fail "No Java 17+ found. Install it: brew install --cask temurin@21"
export JAVA_HOME
command -v xcodebuild >/dev/null || fail "Xcode is missing. Install it from the App Store, open it once, then: sudo xcode-select -s /Applications/Xcode.app"
command -v xcodegen >/dev/null || fail "XcodeGen is missing. Install it: brew install xcodegen"
chmod +x "$HERE/gradlew"

NAME="$(sed -n 's/^versionName=//p' "$HERE/version.properties" | tr -d '\r')"
CODE="$(sed -n 's/^versionCode=//p' "$HERE/version.properties" | tr -d '\r')"

if [ "$MODE" = "check" ]; then
  echo "✓ $(xcodebuild -version | head -1) at $(xcode-select -p)"
  echo "✓ Java: $("$JAVA_HOME/bin/java" -version 2>&1 | head -1)"
  echo "✓ XcodeGen $(xcodegen --version 2>/dev/null | tail -1)"
  if [ -n "$CAMPBUDDY_TEAM_ID" ]; then echo "✓ Signing team $CAMPBUDDY_TEAM_ID"; else echo "• No signing team yet (simulator only): add CAMPBUDDY_TEAM_ID to native-app/iosApp/local.env"; fi
  if [ -f "$IOS/iosApp/GoogleService-Info.plist" ]; then echo "✓ GoogleService-Info.plist: push on"; else echo "• No GoogleService-Info.plist: push off (reminders still work)"; fi
  echo "• The next build will be $NAME ($CODE)"
  exit 0
fi

# The Xcode project is made fresh from project.yml every time, with the version this build gets.
perl -pi -e "s/MARKETING_VERSION: \"[^\"]*\"/MARKETING_VERSION: \"$NAME\"/; s/CURRENT_PROJECT_VERSION: \"[^\"]*\"/CURRENT_PROJECT_VERSION: \"$CODE\"/" "$IOS/project.yml"
(cd "$IOS" && xcodegen generate --quiet)
echo "Xcode project ready for $NAME ($CODE)."

# After a build: the next one is one higher (same rule as bump-version.sh, which Windows/Android uses).
bump() {
  IFS=. read -r MAJOR MINOR PATCH <<< "$NAME"
  perl -pi -e "s/^versionName=.*/versionName=$MAJOR.$MINOR.$((PATCH + 1))/; s/^versionCode=.*/versionCode=$((CODE + 1))/" "$HERE/version.properties"
  echo "Built $NAME ($CODE). The next build will be $MAJOR.$MINOR.$((PATCH + 1)) ($((CODE + 1))). Commit native-app/version.properties and native-app/iosApp/project.yml."
}

archive() {
  [ -n "$CAMPBUDDY_TEAM_ID" ] || fail "An App Store build needs your team: CAMPBUDDY_TEAM_ID=ABCDE12345 in native-app/iosApp/local.env"
  [ -f "$IOS/iosApp/GoogleService-Info.plist" ] || echo "• Warning: no GoogleService-Info.plist, so this build has no push."
  local destination="$1"
  rm -rf "$OUT/CampBuddy.xcarchive" "$OUT/ipa"
  xcodebuild -project "$PROJECT" -scheme iosApp -configuration Release -destination "generic/platform=iOS" \
    -archivePath "$OUT/CampBuddy.xcarchive" -allowProvisioningUpdates archive
  cat > "$OUT/ExportOptions.plist" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>method</key><string>app-store-connect</string>
  <key>destination</key><string>$destination</string>
  <key>teamID</key><string>$CAMPBUDDY_TEAM_ID</string>
  <key>signingStyle</key><string>automatic</string>
  <key>uploadSymbols</key><true/>
  <key>manageAppVersionAndBuildNumber</key><false/>
</dict>
</plist>
EOF
  xcodebuild -exportArchive -archivePath "$OUT/CampBuddy.xcarchive" -exportPath "$OUT/ipa" \
    -exportOptionsPlist "$OUT/ExportOptions.plist" -allowProvisioningUpdates
}

case "$MODE" in
  open)
    open "$PROJECT"
    echo "In Xcode: pick your iPhone (or a simulator) at the top and press Run (⌘R)."
    ;;
  sim)
    DEVICE="$(xcrun simctl list devices available | grep -m1 -E '^ +iPhone' | sed -E 's/.*\(([0-9A-F-]{36})\).*/\1/')"
    [ -n "$DEVICE" ] || fail "No iPhone simulator. Xcode > Settings > Components: add an iOS simulator."
    SIGNING=()
    [ -n "$CAMPBUDDY_TEAM_ID" ] || SIGNING=(CODE_SIGNING_ALLOWED=NO)
    xcodebuild -project "$PROJECT" -scheme iosApp -configuration Debug -destination "id=$DEVICE" \
      -derivedDataPath "$OUT/dd" ${SIGNING[@]+"${SIGNING[@]}"} build
    xcrun simctl boot "$DEVICE" 2>/dev/null || true
    open -a Simulator
    xcrun simctl install "$DEVICE" "$OUT/dd/Build/Products/Debug-iphonesimulator/iosApp.app"
    xcrun simctl launch "$DEVICE" club.campbuddy.app
    bump
    ;;
  archive)
    archive export
    echo "App Store build: $(ls "$OUT"/ipa/*.ipa). Upload it with Apple's Transporter app, or run: bash native-app/build-ios.sh upload"
    bump
    ;;
  upload)
    archive upload
    echo "Sent to App Store Connect. It shows in TestFlight after Apple's processing (about 10-30 minutes)."
    bump
    ;;
  *) fail "Unknown mode '$MODE'. Use check, open, sim, archive or upload." ;;
esac
