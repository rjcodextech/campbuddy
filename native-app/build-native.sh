#!/usr/bin/env bash
# Builds the native (Kotlin) Android app with every tool and cache kept inside native-app/Tools (gitignored).
#   bash native-app/build-native.sh [test|debug|release]
set -euo pipefail

MODE="${1:-debug}"
HERE="$(cd "$(dirname "$0")" && pwd)"
TOOLS="$(cd "$HERE/Tools" && pwd)"
BUILD="$TOOLS/android-build"

export JAVA_HOME="$(ls -d "$BUILD"/jdk-21* | head -1)"
export PATH="$JAVA_HOME/bin:$PATH"
export GRADLE_USER_HOME="$TOOLS/gradle-home"
export ANDROID_USER_HOME="$TOOLS/android-home"
unset ANDROID_HOME ANDROID_SDK_ROOT
echo "sdk.dir=$(cygpath -m "$BUILD/sdk")" > "$HERE/local.properties"

GRADLE="$(ls -d "$BUILD"/gradle-*/bin/gradle.bat | head -1)"
VERSION="$(sed -n 's/^versionName=//p' "$HERE/version.properties")"
cd "$HERE"
# Two flavors: "direct" = the APK that updates itself (Admin → Mobile app → App updates), "play" = the Play Store AAB.
case "$MODE" in
  test)    "$GRADLE" :shared:testDebugUnitTest --no-daemon ;;
  release)
    "$GRADLE" :androidApp:assembleDirectRelease :androidApp:bundlePlayRelease --no-daemon
    mkdir -p "$HERE/release"
    cp "$HERE/androidApp/build/outputs/apk/direct/release/androidApp-direct-release.apk" "$HERE/release/CampBuddy-$VERSION.apk"
    cp "$HERE/androidApp/build/outputs/bundle/playRelease/androidApp-play-release.aab" "$HERE/release/CampBuddy-$VERSION-play.aab"
    echo "APK for Admin → Mobile app → App updates: native-app/release/CampBuddy-$VERSION.apk"
    echo "AAB for the Play Console:                 native-app/release/CampBuddy-$VERSION-play.aab" ;;
  *)       "$GRADLE" :androidApp:assembleDebug --no-daemon ;;
esac
# Every build gets its own version: the next one is one higher (version.properties; also the iOS project).
if [ "$MODE" != "test" ]; then bash "$HERE/bump-version.sh"; fi
