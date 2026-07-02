#!/bin/bash
# Setup script for Dungeon Crawler tests
# This script prepares the environment for running PHPUnit tests

set -e

# Get the script directory
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "Setting up test environment for Dungeon Crawler..."

# Ensure simpletest directory exists and is writable
SIMPLETEST_DIR="$PROJECT_ROOT/web/sites/simpletest"
if [ ! -d "$SIMPLETEST_DIR" ]; then
    echo "Creating simpletest directory: $SIMPLETEST_DIR"
    mkdir -p "$SIMPLETEST_DIR"
    chmod 777 "$SIMPLETEST_DIR"
    echo "✓ Simpletest directory created: $SIMPLETEST_DIR"
else
    echo "✓ Simpletest directory already exists: $SIMPLETEST_DIR"
fi

chmod -R 777 "$SIMPLETEST_DIR"

if command -v setfacl >/dev/null 2>&1; then
    setfacl -Rm u:www-data:rwx "$SIMPLETEST_DIR" || true
    setfacl -Rdm u:www-data:rwx "$SIMPLETEST_DIR" || true
fi

# Ensure vendor dependencies are installed
if [ ! -f "$PROJECT_ROOT/vendor/bin/phpunit" ]; then
    echo "Installing composer dependencies..."
    cd "$PROJECT_ROOT"
    composer install --no-interaction
    echo "✓ Composer dependencies installed"
else
    echo "✓ Composer dependencies already installed"
fi

echo "✓ Test environment setup complete!"
echo ""
echo "You can now run tests with:"
echo "  cd $PROJECT_ROOT"
echo "  ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml"
