#!/bin/bash
# Quick setup to use SD card for future builds (without moving existing cache)

set -e

SD_CARD_PATH="/mnt/chromeos/removable/SD Card"

echo "=== Quick SD Card Setup for Forseti Mobile ==="
echo ""

# Create SD card directories
echo "Creating SD card directories..."
mkdir -p "$SD_CARD_PATH/Android/.gradle"
mkdir -p "$SD_CARD_PATH/Android/forseti-mobile-build"

echo "✓ SD card directories created"
echo ""
echo "Configuration updated in gradle.properties:"
echo "  - Gradle cache: $SD_CARD_PATH/Android/.gradle"
echo "  - Build output: $SD_CARD_PATH/Android/forseti-mobile-build"
echo ""
echo "To also free up 5.4GB by moving existing Gradle cache:"
echo "  Run: ./use-sd-card.sh"
echo ""
echo "Your next build will use the SD card automatically!"
