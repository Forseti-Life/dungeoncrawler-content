#!/bin/bash
#
# Test the new Forseti Mobile setup script
# Runs a dry-run verification of the setup process
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOBILE_DIR="/home/keithaumiller/forseti.life/forseti-mobile"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[→]${NC} $1"
}

echo ""
echo "=========================================="
echo "  Forseti Mobile Setup Verification"
echo "=========================================="
echo ""

# Check if setup script exists
print_step "Checking setup script..."
if [ -f "$SCRIPT_DIR/setup-forseti-mobile-dev.sh" ]; then
    print_status "Setup script found"
    if [ -x "$SCRIPT_DIR/setup-forseti-mobile-dev.sh" ]; then
        print_status "Setup script is executable"
    else
        print_error "Setup script is not executable"
        echo "  Fix: chmod +x $SCRIPT_DIR/setup-forseti-mobile-dev.sh"
    fi
else
    print_error "Setup script not found"
    exit 1
fi

# Check if old scripts were archived
print_step "Checking old scripts archival..."
if [ -d "$SCRIPT_DIR/archive/mobile-legacy" ]; then
    print_status "Archive directory exists"
    
    OLD_SCRIPTS=("setup-mobile.sh" "setup-mobile-web.sh" "setup-android-build.sh")
    for script in "${OLD_SCRIPTS[@]}"; do
        if [ -f "$SCRIPT_DIR/archive/mobile-legacy/$script" ]; then
            print_status "Archived: $script"
        else
            print_warning "Not found in archive: $script"
        fi
        
        if [ -f "$SCRIPT_DIR/$script" ]; then
            print_warning "Old script still in root: $script"
        fi
    done
else
    print_warning "Archive directory not found"
fi

# Check mobile directory
print_step "Checking mobile app directory..."
if [ -d "$MOBILE_DIR" ]; then
    print_status "Mobile directory exists: $MOBILE_DIR"
    
    cd "$MOBILE_DIR"
    
    # Check for package.json
    if [ -f "package.json" ]; then
        print_status "package.json found"
    else
        print_error "package.json not found"
    fi
    
    # Check for node_modules
    if [ -d "node_modules" ]; then
        print_status "node_modules exists"
    else
        print_warning "node_modules not installed yet"
    fi
    
    # Check for critical config files
    CONFIG_FILES=(
        ".eslintrc.js"
        ".prettierrc"
        "jest.config.js"
        "babel.config.js"
        "tsconfig.json"
        ".vscode/launch.json"
        ".vscode/settings.json"
        ".vscode/tasks.json"
    )
    
    print_step "Checking configuration files..."
    MISSING_COUNT=0
    for config in "${CONFIG_FILES[@]}"; do
        if [ -f "$config" ]; then
            print_status "$config"
        else
            print_warning "$config missing"
            ((MISSING_COUNT++))
        fi
    done
    
    if [ $MISSING_COUNT -eq 0 ]; then
        print_status "All configuration files present"
    else
        print_warning "$MISSING_COUNT configuration files missing"
    fi
    
    # Check for environment files
    print_step "Checking environment files..."
    ENV_FILES=(".env" ".env.development" ".env.staging" ".env.production")
    for env in "${ENV_FILES[@]}"; do
        if [ -f "$env" ]; then
            print_status "$env"
        else
            print_warning "$env missing"
        fi
    done
    
    # Check for documentation
    print_step "Checking documentation..."
    DOC_FILES=(
        "CRITICAL_FIXES_SUMMARY.md"
        "QUICK_REFERENCE.md"
        "ENV_VARIABLES.md"
        "BEST_PRACTICES_REVIEW.md"
    )
    
    for doc in "${DOC_FILES[@]}"; do
        if [ -f "$doc" ]; then
            print_status "$doc"
        else
            print_warning "$doc missing"
        fi
    done
    
else
    print_error "Mobile directory not found: $MOBILE_DIR"
    exit 1
fi

# Check script documentation
print_step "Checking script documentation..."
cd "$SCRIPT_DIR"

DOC_FILES=(
    "README.md"
    "MOBILE_SCRIPTS_MIGRATION.md"
    "SCRIPT_ORGANIZATION.md"
    "archive/mobile-legacy/README.md"
)

for doc in "${DOC_FILES[@]}"; do
    if [ -f "$doc" ]; then
        print_status "$doc"
    else
        print_warning "$doc missing"
    fi
done

# Test script help
print_step "Testing setup script help..."
if "$SCRIPT_DIR/setup-forseti-mobile-dev.sh" --help > /dev/null 2>&1; then
    print_status "Help option works"
else
    print_warning "Help option may not work properly"
fi

# Summary
echo ""
echo "=========================================="
echo "  Verification Summary"
echo "=========================================="
echo ""

cd "$MOBILE_DIR"

# Count what's ready
READY_COUNT=0
TOTAL_COUNT=0

# Config files
for config in "${CONFIG_FILES[@]}"; do
    ((TOTAL_COUNT++))
    [ -f "$config" ] && ((READY_COUNT++))
done

# Environment files
for env in "${ENV_FILES[@]}"; do
    ((TOTAL_COUNT++))
    [ -f "$env" ] && ((READY_COUNT++))
done

# Documentation
for doc in "${DOC_FILES[@]}"; do
    ((TOTAL_COUNT++))
    [ -f "$doc" ] && ((READY_COUNT++))
done

PERCENT=$((READY_COUNT * 100 / TOTAL_COUNT))

echo "Readiness: $READY_COUNT/$TOTAL_COUNT files ($PERCENT%)"
echo ""

if [ $PERCENT -ge 90 ]; then
    print_status "Environment is ready for development! 🎉"
    echo ""
    echo "Next steps:"
    echo "  1. Review: cat $SCRIPT_DIR/MOBILE_SCRIPTS_MIGRATION.md"
    echo "  2. Setup: $SCRIPT_DIR/setup-forseti-mobile-dev.sh"
    echo "  3. Develop: cd $MOBILE_DIR && npm run web"
elif [ $PERCENT -ge 70 ]; then
    print_warning "Environment is mostly ready"
    echo ""
    echo "Run setup to complete:"
    echo "  $SCRIPT_DIR/setup-forseti-mobile-dev.sh"
else
    print_error "Environment needs setup"
    echo ""
    echo "Run the setup script:"
    echo "  $SCRIPT_DIR/setup-forseti-mobile-dev.sh"
fi

echo ""
