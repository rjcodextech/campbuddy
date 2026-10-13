#!/usr/bin/env bash
# Android builds, with every tool and cache kept inside mobile-app/Tools.
#   bash mobile-app/app/scripts/build-android.sh            debug APK (any phone, for testing)
#   bash mobile-app/app/scripts/build-android.sh release    signed release APK + AAB (Play Store)
#
# Release signing reads storage/app/analytics/android-signing.txt and the .jks
# beside it (gitignored); nothing about the key is ever committed.
set -euo pipefail

MODE="${1:-debug}"
APP="$(cd "$(dirname "$0")/.." && pwd)"
TOOLS="$(cd "$APP/../Tools" && pwd)"
REPO="$(cd "$APP/../.." && pwd)"
BUILD="$TOOLS/android-build"
VERSION="$(cd "$APP" && node -p "require('./package.json').version")"

export JAVA_HOME="$(ls -d "$BUILD"/jdk-21* | head -1)"
export PATH="$JAVA_HOME/bin:$PATH"
export GRADLE_USER_HOME="$TOOLS/gradle-home"
export ANDROID_USER_HOME="$TOOLS/android-home"
mkdir -p "$ANDROID_USER_HOME" "$APP/release"
unset ANDROID_HOME ANDROID_SDK_ROOT
echo "sdk.dir=$(cygpath -m "$BUILD/sdk")" > "$APP/android/local.properties"

if [ "$MODE" = "release" ]; then
  SIGN="$REPO/storage/app/analytics/android-signing.txt"
  [ -f "$SIGN" ] || { echo "Missing $SIGN"; exit 1; }
  export ANDROID_KEYSTORE_PATH="$(cygpath -m "$REPO/storage/app/analytics/$(sed -n 's/^keystore=//p' "$SIGN")")"
  export ANDROID_KEY_ALIAS="$(sed -n 's/^alias=//p' "$SIGN")"
  export ANDROID_KEYSTORE_PASSWORD="$(sed -n 's/^storepass=//p' "$SIGN")"
  export ANDROID_KEY_PASSWORD="$(sed -n 's/^keypass=//p' "$SIGN")"
fi

cd "$APP" && npx cap sync android
cd android
GRADLE="$(ls -d "$BUILD"/gradle-*/bin/gradle.bat | head -1)"

if [ "$MODE" = "release" ]; then
  "$GRADLE" assembleRelease bundleRelease --no-daemon -q
  cp app/build/outputs/apk/release/app-release.apk "$APP/release/CampBuddy-$VERSION-release.apk"
  cp app/build/outputs/bundle/release/app-release.aab "$APP/release/CampBuddy-$VERSION-release.aab"
else
  "$GRADLE" assembleDebug --no-daemon -q
  cp app/build/outputs/apk/debug/app-debug.apk "$APP/release/CampBuddy-$VERSION-debug.apk"
fi
echo "Built into: $APP/release/"
