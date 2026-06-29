#!/bin/bash
#
# Test script to verify the simpletest directory fix for PHPUnit tests
# This script ensures the test environment is properly configured
#

set -e

echo "=== PHPUnit Test Environment Verification ==="
echo

# Navigate to dungeoncrawler directory
cd "$(dirname "$0")/sites/dungeoncrawler" || exit 1

# 1. Check if simpletest directory exists with proper .gitignore
echo "1. Checking simpletest directory configuration..."
if [ ! -d "web/sites/simpletest" ]; then
    echo "   ERROR: web/sites/simpletest directory not found"
    exit 1
fi

if [ ! -f "web/sites/simpletest/.gitignore" ]; then
    echo "   ERROR: web/sites/simpletest/.gitignore not found"
    exit 1
fi

if [ ! -f "web/sites/simpletest/.gitkeep" ]; then
    echo "   ERROR: web/sites/simpletest/.gitkeep not found"
    exit 1
fi

echo "   ✓ simpletest directory configured correctly"
echo

# 2. Check permissions
echo "2. Checking directory permissions..."
PERMS=$(stat -c "%a" web/sites/simpletest)
if [ "$PERMS" -lt 755 ]; then
    echo "   WARNING: web/sites/simpletest has restrictive permissions ($PERMS)"
    echo "   Setting permissions to 777 for test execution..."
    chmod 777 web/sites/simpletest
fi

echo "   ✓ Directory is writable (permissions: $(stat -c '%a' web/sites/simpletest))"
echo

# 3. Ensure simpletest directory is clean
echo "3. Cleaning old test sites..."
# Count items (excluding . and .. and .git files)
ITEM_COUNT=$(find web/sites/simpletest -mindepth 1 -maxdepth 1 ! -name ".git*" | wc -l)
if [ "$ITEM_COUNT" -gt 0 ]; then
    echo "   Found $ITEM_COUNT old test site(s), cleaning..."
    find web/sites/simpletest -mindepth 1 -maxdepth 1 ! -name ".git*" -exec rm -rf {} \;
    echo "   ✓ Cleaned old test sites"
else
    echo "   ✓ No old test sites to clean"
fi
echo

# 4. Check if vendor directory exists
echo "4. Checking composer dependencies..."
if [ ! -d "vendor" ]; then
    echo "   WARNING: vendor directory not found"
    echo "   Run 'composer install' to install dependencies before running tests"
    echo
    echo "=== Setup Complete (dependencies not installed) ==="
    exit 0
fi

if [ ! -f "vendor/bin/phpunit" ]; then
    echo "   WARNING: PHPUnit not found in vendor/bin"
    echo "   Run 'composer install' to install dependencies"
    echo
    echo "=== Setup Complete (PHPUnit not installed) ==="
    exit 0
fi

echo "   ✓ Composer dependencies installed"
echo

# 5. Verify PHPUnit configuration
echo "5. Checking PHPUnit configuration..."
if [ ! -f "web/modules/custom/dungeoncrawler_tester/phpunit.xml" ]; then
    echo "   ERROR: PHPUnit configuration not found"
    exit 1
fi

echo "   ✓ PHPUnit configuration found"
echo

# 6. Check environment variables
echo "6. Checking environment configuration..."
if ! grep -q "SIMPLETEST_DB" web/modules/custom/dungeoncrawler_tester/phpunit.xml; then
    echo "   ERROR: SIMPLETEST_DB not configured in phpunit.xml"
    exit 1
fi

echo "   ✓ Test environment variables configured"
echo

echo "=== All Checks Passed ==="
echo
echo "To run the specific failing test:"
echo "  cd sites/dungeoncrawler"
echo "  ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml \\"
echo "    --filter testHowToPlayPagePublicAccessNegative"
echo
echo "To run all tests:"
echo "  cd sites/dungeoncrawler"
echo "  ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml"
echo
