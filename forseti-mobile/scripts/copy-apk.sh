#!/bin/bash
# Copy APK to deployment location
# Usage: ./copy-apk.sh [debug|release]

set -e

BUILD_TYPE=${1:-release}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOBILE_DIR="$(dirname "$SCRIPT_DIR")"
DEPLOY_DIR="$MOBILE_DIR/../sites/forseti/web/sites/default/files/forseti/mobile"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}📦 Copying ${BUILD_TYPE} APK to deployment location...${NC}"

# Set APK directory based on build type
APK_DIR="$MOBILE_DIR/android/app/build/outputs/apk/${BUILD_TYPE}"

if [ ! -d "$APK_DIR" ]; then
    echo -e "${RED}❌ APK directory not found: $APK_DIR${NC}"
    exit 1
fi

# Find the arm64-v8a APK (most common architecture)
ARM64_APK=$(find "$APK_DIR" -name "*arm64-v8a.apk" | head -1)

if [ -z "$ARM64_APK" ]; then
    echo -e "${RED}❌ Could not find arm64-v8a APK in $APK_DIR${NC}"
    echo "Available files:"
    ls -lh "$APK_DIR"/ 2>/dev/null || echo "Directory is empty"
    exit 1
fi

APK_FILENAME=$(basename "$ARM64_APK")
APK_SIZE=$(du -h "$ARM64_APK" | cut -f1)

echo -e "${BLUE}Found APK:${NC} $APK_FILENAME ($APK_SIZE)"

# Create deployment directory if it doesn't exist
mkdir -p "$DEPLOY_DIR"

# Copy APK to deployment location with appropriate name
if [ "$BUILD_TYPE" = "debug" ]; then
    DEPLOY_NAME="Forseti-debug-latest.apk"
else
    DEPLOY_NAME="Forseti-latest.apk"
fi

cp "$ARM64_APK" "$DEPLOY_DIR/$DEPLOY_NAME"

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Failed to copy APK to deployment location!${NC}"
    exit 1
fi

echo -e "${GREEN}✅ APK copied to:${NC} $DEPLOY_DIR/$DEPLOY_NAME"

# Show deployment info
echo ""
echo -e "${BLUE}Deployment Details:${NC}"
echo -e "  Build Type: ${YELLOW}${BUILD_TYPE}${NC}"
echo -e "  Source APK: ${YELLOW}$APK_FILENAME${NC}"
echo -e "  Deploy Name: ${YELLOW}$DEPLOY_NAME${NC}"
echo -e "  Size: ${YELLOW}$APK_SIZE${NC}"
echo -e "  Location: ${YELLOW}$DEPLOY_DIR/$DEPLOY_NAME${NC}"

if [ "$BUILD_TYPE" = "release" ]; then
    echo -e "  Public URL: ${YELLOW}https://forseti.life/sites/default/files/forseti/mobile/$DEPLOY_NAME${NC}"
else
    echo -e "  Debug URL: ${YELLOW}https://forseti.life/sites/default/files/forseti/mobile/$DEPLOY_NAME${NC}"
fi

echo ""
echo -e "${GREEN}✅ APK deployment complete!${NC}"