#!/usr/bin/env bash
# Moves the native app's next version up by one: 0.0.1 → 0.0.2, versionCode 4 → 5 (version.properties), and
# copies the version just built into the iOS project (iosApp/project.yml), so Android and iOS never disagree.
# Run by build-native.sh after every successful debug or release build; by hand: bash native-app/bump-version.sh
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
FILE="$HERE/version.properties"
NAME="$(sed -n 's/^versionName=//p' "$FILE" | tr -d '\r')"
CODE="$(sed -n 's/^versionCode=//p' "$FILE" | tr -d '\r')"

# The iOS project carries the version that was just built.
sed -i -E "s/MARKETING_VERSION: \"[^\"]*\"/MARKETING_VERSION: \"$NAME\"/; s/CURRENT_PROJECT_VERSION: \"[^\"]*\"/CURRENT_PROJECT_VERSION: \"$CODE\"/" "$HERE/iosApp/project.yml"

IFS=. read -r MAJOR MINOR PATCH <<< "$NAME"
NEXT="$MAJOR.$MINOR.$((PATCH + 1))"
sed -i -E "s/^versionName=.*/versionName=$NEXT/; s/^versionCode=.*/versionCode=$((CODE + 1))/" "$FILE"
echo "Built $NAME ($CODE). The next build will be $NEXT ($((CODE + 1)))."
