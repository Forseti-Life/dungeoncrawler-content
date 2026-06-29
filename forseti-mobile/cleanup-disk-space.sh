#!/bin/bash
# Cleanup script to free disk space for forseti-mobile builds

set -e

echo "=== Forseti Mobile - Disk Space Cleanup ==="
echo ""
echo "This will clean up temporary files and caches that can be regenerated."
echo "Estimated space to recover: ~2.6 GB"
echo ""

# Function to show size before cleanup
show_size() {
    if [ -d "$1" ]; then
        du -sh "$1" 2>/dev/null || echo "0"
    else
        echo "Directory doesn't exist"
    fi
}

# Track total saved
total_saved=0

echo "Current disk usage:"
df -h / | grep -v Filesystem
echo ""

# 1. Clean Android build outputs (183MB) - safe, will rebuild
echo "1. Cleaning Android build outputs..."
SIZE_BEFORE=$(show_size ~/forseti.life/forseti-mobile/android/app/build)
rm -rf ~/forseti.life/forseti-mobile/android/app/build
rm -rf ~/forseti.life/forseti-mobile/android/.gradle
echo "   Cleaned: $SIZE_BEFORE"

# 2. Clean NPM cache (632MB) - safe, can redownload
echo "2. Cleaning NPM cache..."
SIZE_BEFORE=$(show_size ~/.npm)
rm -rf ~/.npm
echo "   Cleaned: $SIZE_BEFORE"

# 3. Clean general cache (406MB)
echo "3. Cleaning system cache..."
SIZE_BEFORE=$(show_size ~/.cache)
rm -rf ~/.cache/*
echo "   Cleaned: $SIZE_BEFORE"

# 4. Clean Gradle build cache (20MB) - safe
echo "4. Cleaning Gradle build cache..."
SIZE_BEFORE=$(show_size ~/.gradle/caches/build-cache-1)
rm -rf ~/.gradle/caches/build-cache-1
echo "   Cleaned: $SIZE_BEFORE"

# 5. Clean Gradle daemon logs (6MB)
echo "5. Cleaning Gradle daemon logs..."
SIZE_BEFORE=$(show_size ~/.gradle/daemon)
rm -rf ~/.gradle/daemon/*.log
echo "   Cleaned: $SIZE_BEFORE"

# 6. Clean Python cache
echo "6. Cleaning Python cache..."
find ~/forseti.life -type d -name "__pycache__" -exec rm -rf {} + 2>/dev/null || true
find ~/forseti.life -type f -name "*.pyc" -delete 2>/dev/null || true
echo "   Cleaned: Python bytecode"

# 7. Optional: Clean Gradle downloaded dependencies (1.2GB) - will redownload when needed
echo ""
echo "Optional cleanup (will redownload when needed):"
echo "7. Gradle module cache: $(show_size ~/.gradle/caches/modules-2)"
echo "   To clean: rm -rf ~/.gradle/caches/modules-2"
echo ""
echo "8. Gradle wrapper distributions: $(show_size ~/.gradle/wrapper)"
echo "   To clean: rm -rf ~/.gradle/wrapper"
echo ""

echo "=== Cleanup Complete ==="
echo ""
echo "New disk usage:"
df -h / | grep -v Filesystem
echo ""
echo "To recover even more space, move to SD card using: ./use-sd-card.sh"
