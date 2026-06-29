#!/bin/bash
# Complete build and deploy workflow for Forseti Mobile
# 1. Increments version
# 2. Builds release APK
# 3. Copies APK to website deployment location
# 4. Shows git status for commit

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="$SCRIPT_DIR/../sites/forseti/web/sites/default/files/forseti/mobile"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Forseti Mobile Complete Build & Deploy${NC}"
echo "================================================"
echo ""

# Step 1: Increment version
echo -e "${YELLOW}Step 1: Incrementing build version...${NC}"
"$SCRIPT_DIR/increment-build.sh"
echo ""

# Step 2: Build release APK
echo -e "${YELLOW}Step 2: Building release APK...${NC}"
cd "$SCRIPT_DIR/android"
./gradlew clean assembleRelease --no-daemon

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ Build failed!${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}✅ Build successful!${NC}"
echo ""

# Step 3: Copy APK using dedicated script
echo -e "${YELLOW}Step 3: Deploying APK to website location...${NC}"
cd "$SCRIPT_DIR"
./scripts/copy-apk.sh release

if [ $? -ne 0 ]; then
    echo -e "${RED}❌ APK deployment failed!${NC}"
    exit 1
fi

# Step 4: Show git status
echo -e "${YELLOW}Step 4: Git status${NC}"
cd "$SCRIPT_DIR/.."
git status

echo ""
echo -e "${GREEN}================================================${NC}"
echo -e "${GREEN}🎉 Build and Deploy Complete!${NC}"
echo -e "${GREEN}================================================${NC}"
echo ""
echo -e "${BLUE}Files changed:${NC}"
echo "  • forseti-mobile/App.tsx (version display)"
echo "  • forseti-mobile/android/app/build.gradle (versionCode)"
echo "  • sites/forseti/web/sites/default/files/forseti/mobile/Forseti-latest.apk"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "  1. Review changes: ${YELLOW}git diff${NC}"
echo "  2. Commit changes: ${YELLOW}git add -A && git commit -m 'Build and deploy Forseti Mobile v1.0.3-<version>'${NC}"
echo "  3. Push to trigger deployment: ${YELLOW}git push origin main${NC}"
echo ""
echo -e "${BLUE}Deployment URL:${NC} https://forseti.life/sites/default/files/forseti/mobile/Forseti-latest.apk"
