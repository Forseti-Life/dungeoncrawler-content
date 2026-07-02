#!/usr/bin/env bash
#
# Wrapper script to run PHPUnit tests with automatic environment setup.
#
# This script ensures the test environment is properly configured before
# running PHPUnit, then executes the tests with any provided arguments.
#
# Usage:
#   ./tests/run-tests.sh [phpunit arguments]
#
# Examples:
#   ./tests/run-tests.sh                                  # Run all tests
#   ./tests/run-tests.sh --testsuite=unit                # Run only unit tests
#   ./tests/run-tests.sh --group=controller              # Run controller tests
#   ./tests/run-tests.sh --coverage-html tests/coverage  # Generate coverage report

set -e

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." && pwd )"

cd "$PROJECT_ROOT"

# Color output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Ensuring test environment is set up...${NC}"
"$SCRIPT_DIR/setup-test-environment.sh"

echo ""
echo -e "${YELLOW}Running PHPUnit tests...${NC}"

# Check if vendor/bin/phpunit exists
if [ ! -f "./vendor/bin/phpunit" ]; then
    echo -e "${RED}Error: ./vendor/bin/phpunit not found.${NC}"
    echo -e "${YELLOW}Please run 'composer install' first.${NC}"
    exit 1
fi

# Run PHPUnit with the provided arguments, or default configuration
if [ $# -eq 0 ]; then
    ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml
else
    ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml "$@"
fi
