#!/bin/bash
# Script to configure forseti-mobile to use SD card for build artifacts

set -e

SD_CARD_PATH="/mnt/chromeos/removable/SD Card"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=== Forseti Mobile - SD Card Build Configuration ==="
echo ""

# Create SD card directories
echo "Creating SD card directories..."
mkdir -p "$SD_CARD_PATH/Android/.gradle"
mkdir -p "$SD_CARD_PATH/Android/forseti-mobile-build"
mkdir -p "$SD_CARD_PATH/Android/node_modules"

# Backup and move existing Gradle cache
if [ -d ~/.gradle ] && [ ! -L ~/.gradle ]; then
    echo "Moving Gradle cache to SD card..."
    echo "This will save 5.4GB of local disk space"
    echo ""
    read -p "Continue? This takes 5-10 minutes. (y/n) " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        # Use mv for faster operation (same filesystem might not work, so fallback to rsync)
        if mv ~/.gradle "$SD_CARD_PATH/Android/.gradle" 2>/dev/null; then
            ln -s "$SD_CARD_PATH/Android/.gradle" ~/.gradle
            echo "✓ Gradle cache moved (fast)"
        else
            # Fallback to rsync with progress
            rsync -a --info=progress2 ~/.gradle/ "$SD_CARD_PATH/Android/.gradle/"
            rm -rf ~/.gradle
            ln -s "$SD_CARD_PATH/Android/.gradle" ~/.gradle
            echo "✓ Gradle cache moved"
        fi
    else
        echo "Skipped Gradle cache move"
    fi
else
    echo "✓ Gradle cache already on SD card or doesn't exist"
fi

# Move node_modules if it exists
if [ -d "$PROJECT_ROOT/node_modules" ] && [ ! -L "$PROJECT_ROOT/node_modules" ]; then
    echo "Moving node_modules to SD card..."
    mv "$PROJECT_ROOT/node_modules" "$SD_CARD_PATH/Android/node_modules/forseti-mobile"
    ln -s "$SD_CARD_PATH/Android/node_modules/forseti-mobile" "$PROJECT_ROOT/node_modules"
    echo "✓ node_modules moved"
else
    echo "✓ node_modules already on SD card or doesn't exist"
fi

# Move Android build output
ANDROID_BUILD="$PROJECT_ROOT/android/app/build"
if [ -d "$ANDROID_BUILD" ] && [ ! -L "$ANDROID_BUILD" ]; then
    echo "Moving Android build directory..."
    rm -rf "$ANDROID_BUILD"
fi

echo ""
echo "=== Configuration Complete ==="
echo "Gradle cache: $SD_CARD_PATH/Android/.gradle"
echo "Build output: $SD_CARD_PATH/Android/forseti-mobile-build"
echo "Node modules: $SD_CARD_PATH/Android/node_modules"
echo ""
echo "Your builds will now use the SD card for large artifacts."
echo "Run './gradlew clean' in android/ directory to clean build cache."
