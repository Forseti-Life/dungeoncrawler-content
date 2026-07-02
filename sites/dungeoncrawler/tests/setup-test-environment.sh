#!/usr/bin/env bash
#
# Setup script for DungeonCrawler PHPUnit tests
# This script prepares the test environment by creating necessary directories
# and setting proper permissions.
#
# Usage: ./tests/setup-test-environment.sh

set -e

echo "Setting up test environment..."

# Create temporary directories for simpletest
echo "Creating temporary test directories..."
mkdir -p /tmp/dungeoncrawler-simpletest/browser_output
chmod -R 775 /tmp/dungeoncrawler-simpletest

# Ensure simpletest directory in web root exists and is writable
echo "Setting permissions on simpletest directory..."
mkdir -p web/sites/simpletest
chmod 777 web/sites/simpletest

# Create default site directories if they don't exist
echo "Ensuring default site directories exist..."
mkdir -p web/sites/default/files
chmod 775 web/sites/default/files

echo "Test environment setup complete!"
echo ""
echo "You can now run PHPUnit tests:"
echo "  cd sites/dungeoncrawler"
echo "  ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml"
# Setup script for Dungeon Crawler functional test environment.
#
# This script ensures that all necessary directories and permissions
# are in place before running PHPUnit functional tests.
#
# Usage:
#   ./tests/setup-test-environment.sh

set -e

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}Setting up Dungeon Crawler test environment...${NC}"

# Create temporary file storage directory for simpletest
SIMPLETEST_DIR="/tmp/dungeoncrawler-simpletest"
BROWSER_OUTPUT_DIR="/tmp/dungeoncrawler-simpletest/browser_output"

echo -e "${YELLOW}Creating simpletest directories...${NC}"
mkdir -p "$BROWSER_OUTPUT_DIR"
chmod -R 755 "$SIMPLETEST_DIR"
echo -e "${GREEN}✓${NC} Created and configured: $SIMPLETEST_DIR"

# Ensure sites/simpletest directory exists and is writable
SITES_SIMPLETEST="web/sites/simpletest"
if [ ! -d "$SITES_SIMPLETEST" ]; then
    echo -e "${YELLOW}Creating $SITES_SIMPLETEST directory...${NC}"
    mkdir -p "$SITES_SIMPLETEST"
fi

chmod -R 777 "$SITES_SIMPLETEST"
if command -v setfacl >/dev/null 2>&1; then
    setfacl -Rm u:www-data:rwx "$SITES_SIMPLETEST" || true
    setfacl -Rdm u:www-data:rwx "$SITES_SIMPLETEST" || true
fi
echo -e "${GREEN}✓${NC} Configured permissions for: $SITES_SIMPLETEST"

# Clean up old test sites (optional, uncomment if needed)
# echo -e "${YELLOW}Cleaning up old test sites...${NC}"
# find "$SITES_SIMPLETEST" -mindepth 1 -maxdepth 1 -type d -mtime +7 -exec rm -rf {} \;
# echo -e "${GREEN}✓${NC} Cleaned up old test sites"

echo -e "${GREEN}Test environment setup complete!${NC}"
echo ""
echo -e "You can now run tests with:"
echo -e "  ${YELLOW}./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml${NC}"
