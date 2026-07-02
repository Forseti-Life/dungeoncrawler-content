#!/bin/bash
# Setup script for Dungeon Crawler functional tests
# This script prepares the environment for running PHPUnit functional tests

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DRUPAL_ROOT="$SCRIPT_DIR"

echo "Setting up Dungeon Crawler test environment..."

# 1. Ensure sites/simpletest directory exists and is writable
echo "Ensuring sites/simpletest directory is writable..."
mkdir -p "$DRUPAL_ROOT/web/sites/simpletest"
chmod 777 "$DRUPAL_ROOT/web/sites/simpletest"

# 2. Create temp directory for test file storage
echo "Creating /tmp/dungeoncrawler-simpletest directory..."
mkdir -p /tmp/dungeoncrawler-simpletest/browser_output
chmod -R 777 /tmp/dungeoncrawler-simpletest

# 3. Clean up any existing test sites
echo "Cleaning up existing test site directories..."
rm -rf "$DRUPAL_ROOT/web/sites/simpletest"/*

echo ""
echo "Test environment setup complete!"
echo ""
echo "You can now run tests with:"
echo "  cd $DRUPAL_ROOT"
echo "  ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml"
echo ""
