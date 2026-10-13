#!/usr/bin/env bash
# Builds the native (Kotlin) Android app with every tool and cache kept inside mobile-app/Tools.
#   bash native-app/build-native.sh [test|debug|release]
set -euo pipefail

MODE="${1:-debug}"
HERE="$(cd "$(dirname "$0")" && pwd)"
TOOLS="$(cd "$HERE/../mobile-app/Tools" && pwd)"
BUILD="$TOOLS/android-build"

export JAVA_HOME="$(ls -d "$BUILD"/jdk-21* | head -1)"
export PATH="$JAVA_HOME/bin:$PATH"
export GRADLE_USER_HOME="$TOOLS/gradle-home"
export ANDROID_USER_HOME="$TOOLS/android-home"
unset ANDROID_HOME ANDROID_SDK_ROOT
echo "sdk.dir=$(cygpath -m "$BUILD/sdk")" > "$HERE/local.properties"

GRADLE="$(ls -d "$BUILD"/gradle-*/bin/gradle.bat | head -1)"
cd "$HERE"
case "$MODE" in
  test)    "$GRADLE" :shared:testDebugUnitTest --no-daemon ;;
  release) "$GRADLE" :androidApp:assembleRelease :androidApp:bundleRelease --no-daemon ;;
  *)       "$GRADLE" :androidApp:assembleDebug --no-daemon ;;
esac
