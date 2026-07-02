#!/bin/bash
#
# Verification script for bootstrap.php fix
# This script validates that the bootstrap file is properly formatted
# and can be parsed without errors.
#

set -e

echo "=== Bootstrap.php Fix Verification ==="
echo

BOOTSTRAP_FILE="web/modules/custom/dungeoncrawler_tester/tests/bootstrap.php"

# Navigate to dungeoncrawler directory
cd "$(dirname "$0")" || exit 1

# 1. Check if bootstrap file exists
echo "1. Checking bootstrap file exists..."
if [ ! -f "$BOOTSTRAP_FILE" ]; then
    echo "   ❌ ERROR: Bootstrap file not found at $BOOTSTRAP_FILE"
    exit 1
fi
echo "   ✓ Bootstrap file exists"
echo

# 2. Check PHP syntax
echo "2. Checking PHP syntax..."
if ! php -l "$BOOTSTRAP_FILE" > /dev/null 2>&1; then
    echo "   ❌ ERROR: Bootstrap file has PHP syntax errors"
    php -l "$BOOTSTRAP_FILE"
    exit 1
fi
echo "   ✓ PHP syntax is valid"
echo

# 3. Check for duplicate content markers
echo "3. Checking for duplicate/corrupted content..."
# Look for the telltale sign of the corruption - the broken comment line
if grep -q "require_once DRUPAL_ROOT.*\* Custom bootstrap" "$BOOTSTRAP_FILE"; then
    echo "   ❌ ERROR: Bootstrap file still contains duplicate content"
    exit 1
fi
echo "   ✓ No duplicate content detected"
echo

# 4. Check file has required components
echo "4. Checking bootstrap file contains required components..."
REQUIRED_ITEMS=(
    "umask(0002)"
    "PHPUNIT_COMPOSER_INSTALL"
    "DRUPAL_ROOT"
    "simpletest"
    "core/tests/bootstrap.php"
)

for item in "${REQUIRED_ITEMS[@]}"; do
    if ! grep -q "$item" "$BOOTSTRAP_FILE"; then
        echo "   ❌ ERROR: Bootstrap file missing required component: $item"
        exit 1
    fi
done
echo "   ✓ All required components present"
echo

# 5. Count lines - should be around 50 lines, not 60+
echo "5. Checking file length..."
LINE_COUNT=$(wc -l < "$BOOTSTRAP_FILE")
if [ "$LINE_COUNT" -gt 55 ]; then
    echo "   ⚠ WARNING: Bootstrap file has $LINE_COUNT lines (expected ~50)"
    echo "   This might indicate duplicate content"
elif [ "$LINE_COUNT" -lt 40 ]; then
    echo "   ⚠ WARNING: Bootstrap file has only $LINE_COUNT lines"
    echo "   This might indicate missing content"
else
    echo "   ✓ File length is appropriate ($LINE_COUNT lines)"
fi
echo

# 6. Verify test environment setup extension exists
echo "6. Checking TestEnvironmentSetup extension..."
EXTENSION_FILE="web/modules/custom/dungeoncrawler_tester/tests/src/Extension/TestEnvironmentSetup.php"
if [ ! -f "$EXTENSION_FILE" ]; then
    echo "   ⚠ WARNING: TestEnvironmentSetup extension not found"
    echo "   Tests may require manual environment setup"
else
    echo "   ✓ TestEnvironmentSetup extension exists"
fi
echo

# 7. Validate PHPUnit XML configuration
echo "7. Validating phpunit.xml syntax..."
PHPUNIT_XML="web/modules/custom/dungeoncrawler_tester/phpunit.xml"
if [ ! -f "$PHPUNIT_XML" ]; then
    echo "   ❌ ERROR: phpunit.xml not found at $PHPUNIT_XML"
    exit 1
fi

# Use PHP to validate XML
if ! php -r "if (simplexml_load_file('$PHPUNIT_XML') === false) exit(1);" 2>/dev/null; then
    echo "   ❌ ERROR: phpunit.xml has syntax errors"
    exit 1
fi
echo "   ✓ phpunit.xml is valid XML"
echo

# 8. Check simpletest directory configuration
echo "8. Checking simpletest directory configuration..."
SIMPLETEST_DIR="web/sites/simpletest"
if [ ! -d "$SIMPLETEST_DIR" ]; then
    echo "   ⚠ INFO: Creating simpletest directory..."
    mkdir -p "$SIMPLETEST_DIR"
fi

if [ ! -f "$SIMPLETEST_DIR/.gitignore" ]; then
    echo "   ⚠ WARNING: .gitignore not found in simpletest directory"
else
    echo "   ✓ simpletest directory has .gitignore"
fi

PERMS=$(stat -c "%a" "$SIMPLETEST_DIR" 2>/dev/null || echo "000")
if [ "$PERMS" -lt 775 ]; then
    echo "   ⚠ INFO: Setting simpletest directory permissions to 775"
    chmod 775 "$SIMPLETEST_DIR"
fi
echo "   ✓ simpletest directory permissions: $(stat -c '%a' "$SIMPLETEST_DIR")"
echo

echo "=== Verification Complete ==="
echo
echo "Summary:"
echo "✓ Bootstrap.php file is properly formatted"
echo "✓ No duplicate content detected"
echo "✓ All required components present"
echo "✓ PHP syntax is valid"
echo
echo "The bootstrap fix is ready for testing."
echo
echo "To run tests (requires composer dependencies):"
echo "  cd sites/dungeoncrawler"
echo "  ./prepare-test-env.sh"
echo "  ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml \\"
echo "    --filter testCharacterCreationAccessNegative"
echo
