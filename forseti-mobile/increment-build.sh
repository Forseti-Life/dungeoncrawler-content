#!/bin/bash
# Auto-increment build version for Forseti Mobile
# Updates versionCode in build.gradle and version display in App.tsx

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_GRADLE="$SCRIPT_DIR/android/app/build.gradle"
APP_TSX="$SCRIPT_DIR/App.tsx"
PACKAGE_JSON="$SCRIPT_DIR/package.json"
APP_VERSION_TS="$SCRIPT_DIR/src/config/AppVersion.ts"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}📦 Forseti Mobile Build Version Incrementer${NC}"
echo "================================================"

# Get current version from package.json
CURRENT_VERSION=$(grep '"version":' "$PACKAGE_JSON" | sed -E 's/.*"version": "([^"]+)".*/\1/')
echo -e "${BLUE}Current package.json version:${NC} $CURRENT_VERSION"

# Get current versionCode from build.gradle (only the defaultConfig line)
CURRENT_VERSION_CODE=$(grep 'versionCode' "$BUILD_GRADLE" | grep -v 'def versionCode' | head -1 | sed -E 's/.*versionCode ([0-9]+).*/\1/')
echo -e "${BLUE}Current versionCode:${NC} $CURRENT_VERSION_CODE"

# Increment versionCode
NEW_VERSION_CODE=$((CURRENT_VERSION_CODE + 1))
echo -e "${GREEN}New versionCode:${NC} $NEW_VERSION_CODE"

# Get current date
BUILD_DATE=$(date +%Y-%m-%d)
echo -e "${BLUE}Build date:${NC} $BUILD_DATE"

# Update build.gradle (only in defaultConfig section)
echo -e "\n${YELLOW}Updating build.gradle...${NC}"
sed -i "/defaultConfig {/,/}/ s/versionCode $CURRENT_VERSION_CODE/versionCode $NEW_VERSION_CODE/" "$BUILD_GRADLE"

# Update versionName in build.gradle to match package.json
sed -i "s/versionName \".*\"/versionName \"$CURRENT_VERSION\"/" "$BUILD_GRADLE"

# Update App.tsx with new version display (includes build code)
echo -e "${YELLOW}Updating App.tsx version display...${NC}"
VERSION_DISPLAY="v${CURRENT_VERSION}-${NEW_VERSION_CODE} ($BUILD_DATE)"
sed -i "s|<Text style={styles.versionText}>v.*</Text>|<Text style={styles.versionText}>$VERSION_DISPLAY</Text>|" "$APP_TSX"

# Update AppVersion.ts with new version info
echo -e "${YELLOW}Updating AppVersion.ts...${NC}"
sed -i "s/BUILD_CODE: [0-9]*/BUILD_CODE: $NEW_VERSION_CODE/" "$APP_VERSION_TS"
sed -i "s/BUILD_DATE: '[0-9-]*'/BUILD_DATE: '$BUILD_DATE'/" "$APP_VERSION_TS"
sed -i "s/VERSION: '[^']*'/VERSION: '$CURRENT_VERSION'/" "$APP_VERSION_TS"

echo -e "\n${GREEN}✅ Build version updated successfully!${NC}"
echo "================================================"
echo -e "Version: ${GREEN}$CURRENT_VERSION${NC}"
echo -e "Build Code: ${GREEN}$NEW_VERSION_CODE${NC}"
echo -e "Date: ${GREEN}$BUILD_DATE${NC}"
echo -e "Display: ${GREEN}$VERSION_DISPLAY${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "  1. Review changes: git diff"
echo "  2. Build APK: cd android && ./gradlew assembleRelease"
echo "  3. Commit changes: git add -A && git commit -m 'Build $CURRENT_VERSION-$NEW_VERSION_CODE'"
