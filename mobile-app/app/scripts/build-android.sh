#!/usr/bin/env bash
# One command: debug APK, with every tool and cache kept inside mobile-app/Tools
# (nothing is written outside the campbuddy folder).
#   bash mobile-app/app/scripts/build-android.sh [assembleDebug|bundleRelease]
set -euo pipefail

APP="$(cd "$(dirname "$0")/.." && pwd)"
TOOLS="$(cd "$APP/../Tools" && pwd)"
BUILD="$TOOLS/android-build"

export JAVA_HOME="$(ls -d "$BUILD"/jdk-21* | head -1)"
export PATH="$JAVA_HOME/bin:$PATH"
export GRADLE_USER_HOME="$TOOLS/gradle-home"
export ANDROID_USER_HOME="$TOOLS/android-home"
mkdir -p "$ANDROID_USER_HOME"
unset ANDROID_HOME ANDROID_SDK_ROOT
echo "sdk.dir=$(cygpath -m "$BUILD/sdk")" > "$APP/android/local.properties"

cd "$APP" && npx cap sync android
cd android
"$(ls -d "$BUILD"/gradle-*/bin/gradle.bat | head -1)" "${1:-assembleDebug}" --no-daemon -q

if [ "${1:-assembleDebug}" = "assembleDebug" ]; then
  mkdir -p "$APP/release"
  cp app/build/outputs/apk/debug/app-debug.apk "$APP/release/CampBuddy-$(cd "$APP" && node -p "require('./package.json').version")-debug.apk"
  echo "APK: $APP/release/"
fi
