#!/bin/bash
#
# Verification script for Campaign State Validation Test fix
#
# This script validates that the test environment is properly configured
# and the fixes for the bootstrap and phpunit.xml files are working.
#

set -e

echo "======================================================================"
echo "Campaign State Validation Test - Fix Verification"
echo "======================================================================"
echo ""

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DUNGEONCRAWLER_DIR="$SCRIPT_DIR/sites/dungeoncrawler"
WEB_DIR="$DUNGEONCRAWLER_DIR/web"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

pass() {
  echo -e "${GREEN}✓${NC} $1"
}

fail() {
  echo -e "${RED}✗${NC} $1"
  return 1
}

warn() {
  echo -e "${YELLOW}⚠${NC} $1"
}

echo "Step 1: Checking directory structure..."
if [ -d "$DUNGEONCRAWLER_DIR" ]; then
  pass "dungeoncrawler directory exists"
else
  fail "dungeoncrawler directory not found at $DUNGEONCRAWLER_DIR"
fi

if [ -d "$WEB_DIR" ]; then
  pass "web directory exists"
else
  fail "web directory not found at $WEB_DIR"
fi

echo ""
echo "Step 2: Checking test files..."

BOOTSTRAP_FILE="$WEB_DIR/modules/custom/dungeoncrawler_tester/tests/bootstrap.php"
if [ -f "$BOOTSTRAP_FILE" ]; then
  pass "bootstrap.php exists"
  
  # Check for duplicate content
  if grep -q "require_once DRUPAL_ROOT" "$BOOTSTRAP_FILE" && ! grep -q "require __DIR__" "$BOOTSTRAP_FILE"; then
    pass "bootstrap.php appears to be fixed (no duplicate require statements)"
  else
    warn "bootstrap.php may have duplicate require statements"
  fi
else
  fail "bootstrap.php not found"
fi

PHPUNIT_XML="$WEB_DIR/modules/custom/dungeoncrawler_tester/phpunit.xml"
if [ -f "$PHPUNIT_XML" ]; then
  pass "phpunit.xml exists"
  
  # Check for malformed XML
  if grep -A1 'failOnWarning="false"' "$PHPUNIT_XML" | grep -q 'failOnPhpunitDeprecation="false">'; then
    pass "phpunit.xml appears to be fixed (failOnPhpunitDeprecation in correct location)"
  else
    warn "phpunit.xml may need review"
  fi
else
  fail "phpunit.xml not found"
fi

echo ""
echo "Step 3: Checking MySQL database..."

# Try to connect to MySQL
if command -v mysql &> /dev/null; then
  pass "MySQL client available"
  
  # Test database connection (using env-provided test credentials)
  DB_PASSWORD="${SIMPLETEST_DB_PASSWORD:-}"
  if [ -z "$DB_PASSWORD" ]; then
    warn "SIMPLETEST_DB_PASSWORD is not set; skipping database connection check"
    echo "  Set SIMPLETEST_DB_PASSWORD to run the MySQL connectivity check."
  elif MYSQL_PWD="$DB_PASSWORD" mysql -h 127.0.0.1 -u drupal_user -e "USE dungeoncrawler_dev; SELECT 1;" &> /dev/null; then
    pass "MySQL database 'dungeoncrawler_dev' is accessible"
  else
    warn "Cannot connect to MySQL database 'dungeoncrawler_dev'"
    echo "  To set up the database, run:"
    echo "    mysql -e \"CREATE DATABASE IF NOT EXISTS dungeoncrawler_dev;\""
    echo "    mysql -e \"CREATE USER IF NOT EXISTS 'drupal_user'@'127.0.0.1' IDENTIFIED BY '<your_password>';\""
    echo "    mysql -e \"GRANT ALL PRIVILEGES ON dungeoncrawler_dev.* TO 'drupal_user'@'127.0.0.1'; FLUSH PRIVILEGES;\""
    echo "  Note: Replace <your_password> with the password used for SIMPLETEST_DB_PASSWORD"
  fi
else
  warn "MySQL client not available"
fi

echo ""
echo "Step 4: Checking PHP and Composer..."

if command -v php &> /dev/null; then
  PHP_VERSION=$(php -r "echo PHP_VERSION;")
  pass "PHP is available (version $PHP_VERSION)"
  
  if php -r "exit(version_compare(PHP_VERSION, '8.3.0', '>=') ? 0 : 1);"; then
    pass "PHP version is 8.3 or higher"
  else
    warn "PHP version should be 8.3 or higher (current: $PHP_VERSION)"
  fi
else
  fail "PHP not available"
fi

if command -v composer &> /dev/null; then
  pass "Composer is available"
else
  warn "Composer not available"
fi

echo ""
echo "Step 5: Checking Composer dependencies..."

if [ -d "$DUNGEONCRAWLER_DIR/vendor" ]; then
  pass "vendor directory exists"
  
  if [ -f "$DUNGEONCRAWLER_DIR/vendor/autoload.php" ]; then
    pass "Composer autoloader exists"
  else
    warn "Composer autoloader not found - run 'composer install'"
  fi
  
  if [ -f "$DUNGEONCRAWLER_DIR/vendor/bin/phpunit" ] || [ -L "$DUNGEONCRAWLER_DIR/vendor/bin/phpunit" ]; then
    pass "PHPUnit binary exists"
  else
    warn "PHPUnit binary not found - run 'composer install'"
  fi
else
  warn "vendor directory not found - run 'composer install' in $DUNGEONCRAWLER_DIR"
fi

echo ""
echo "Step 6: Checking test directories..."

SIMPLETEST_DIR="$WEB_DIR/sites/simpletest"
if [ -d "$SIMPLETEST_DIR" ]; then
  pass "simpletest directory exists"
  
  # Check permissions
  PERMS=$(stat -c "%a" "$SIMPLETEST_DIR" 2>/dev/null || stat -f "%A" "$SIMPLETEST_DIR" 2>/dev/null)
  if [ "$PERMS" = "775" ] || [ "$PERMS" = "777" ]; then
    pass "simpletest directory has correct permissions ($PERMS)"
  else
    warn "simpletest directory permissions should be 775 or 777 (current: $PERMS)"
    echo "  Run: chmod 775 $SIMPLETEST_DIR"
  fi
else
  warn "simpletest directory not found (will be created by bootstrap)"
fi

TMP_DIR="/tmp/dungeoncrawler-simpletest"
if [ -d "$TMP_DIR" ]; then
  pass "tmp test directory exists"
else
  warn "tmp test directory not found (will be created by tests)"
fi

echo ""
echo "======================================================================"
echo "Verification Summary"
echo "======================================================================"
echo ""
echo "Next steps:"
echo ""
echo "1. If 'vendor' directory is missing:"
echo "   cd $DUNGEONCRAWLER_DIR"
echo "   composer install"
echo ""
echo "2. If MySQL database is not set up, create it using the commands shown above"
echo ""
echo "3. Run the specific failing test:"
echo "   cd $WEB_DIR"
echo "   ../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml --filter testMissingStatePayload"
echo ""
echo "4. Or run all tests:"
echo "   cd $WEB_DIR"
echo "   ../vendor/bin/phpunit -c modules/custom/dungeoncrawler_tester/phpunit.xml"
echo ""
echo "5. Or use the old command style (should now work with the bootstrap fix):"
echo "   cd $DUNGEONCRAWLER_DIR"
echo "   ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml"
echo ""
echo "======================================================================"
