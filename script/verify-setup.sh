#!/bin/bash

# Multi-Site Setup Verification Script
# Verifies that both Drupal sites are properly configured and accessible

set -e

echo "=== Multi-Site Drupal Setup Verification ==="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[✓]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[!]${NC} $1"
}

print_error() {
    echo -e "${RED}[✗]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[i]${NC} $1"
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Auto-detect supported workspace layouts
if [ -d "/home/keithaumiller/forseti.life/sites/forseti" ] && [ -d "/home/keithaumiller/forseti.life/sites/dungeoncrawler" ]; then
    PRIMARY_SITE_DIR="/home/keithaumiller/forseti.life/sites/forseti"
    SECONDARY_SITE_DIR="/home/keithaumiller/forseti.life/sites/dungeoncrawler"
    PRIMARY_SITE_NAME="Forseti"
    SECONDARY_SITE_NAME="Dungeon Crawler"
    PRIMARY_DB_NAME="forseti_dev"
    SECONDARY_DB_NAME="dungeoncrawler_dev"
else
    PRIMARY_SITE_DIR="/workspaces/stlouisintegration.com/sites/stlouisintegration"
    SECONDARY_SITE_DIR="/workspaces/stlouisintegration.com/sites/theoryofconspiracies"
    PRIMARY_SITE_NAME="St. Louis Integration"
    SECONDARY_SITE_NAME="Theory of Conspiracies"
    PRIMARY_DB_NAME="stlouisintegration_dev"
    SECONDARY_DB_NAME="theoryofconspiracies_dev"
fi

# Check directory structure
print_info "Checking directory structure..."
if [ -d "$PRIMARY_SITE_DIR" ]; then
    print_status "$PRIMARY_SITE_NAME site directory exists"
else
    print_error "$PRIMARY_SITE_NAME site directory missing"
fi

if [ -d "$SECONDARY_SITE_DIR" ]; then
    print_status "$SECONDARY_SITE_NAME site directory exists"
else
    print_error "$SECONDARY_SITE_NAME site directory missing"
fi

# Check Apache configuration
print_info "Checking Apache configuration..."
if sudo apache2ctl configtest 2>&1 | grep -q "Syntax OK"; then
    print_status "Apache configuration is valid"
else
    print_error "Apache configuration has syntax errors"
fi

# Check if Apache is listening on both ports
if sudo netstat -tlnp | grep -q ":80 "; then
    print_status "Apache listening on port 80"
else
    print_warning "Apache not listening on port 80"
fi

if sudo netstat -tlnp | grep -q ":8080 "; then
    print_status "Apache listening on port 8080"
else
    print_warning "Apache not listening on port 8080"
fi

# Check database connections
print_info "Checking database connections..."
DB_PASSWORD="${DB_PASSWORD:-}"
if [ -z "$DB_PASSWORD" ]; then
    print_warning "DB_PASSWORD not set; skipping database connection checks"
else
    if mysql -u drupal_user -p"$DB_PASSWORD" -h 127.0.0.1 -e "USE ${PRIMARY_DB_NAME}; SELECT 1;" >/dev/null 2>&1; then
        print_status "$PRIMARY_SITE_NAME database accessible"
    else
        print_error "$PRIMARY_SITE_NAME database connection failed"
    fi

    if mysql -u drupal_user -p"$DB_PASSWORD" -h 127.0.0.1 -e "USE ${SECONDARY_DB_NAME}; SELECT 1;" >/dev/null 2>&1; then
        print_status "$SECONDARY_SITE_NAME database accessible"
    else
        print_error "$SECONDARY_SITE_NAME database connection failed"
    fi
fi

# Check website accessibility
print_info "Checking website accessibility..."
if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|302\|301"; then
    print_status "$PRIMARY_SITE_NAME site (http://localhost) is accessible"
else
    print_warning "$PRIMARY_SITE_NAME site may not be accessible"
fi

if curl -s -o /dev/null -w "%{http_code}" "http://localhost:8080" | grep -q "200\|302\|301"; then
    print_status "$SECONDARY_SITE_NAME site (http://localhost:8080) is accessible"
else
    print_warning "$SECONDARY_SITE_NAME site may not be accessible"
fi

# Check Drush functionality
print_info "Checking Drush functionality..."
if [ -d "$PRIMARY_SITE_DIR" ]; then
    cd "$PRIMARY_SITE_DIR"
    if [ -f "vendor/bin/drush" ]; then
        if ./vendor/bin/drush status --fields=bootstrap 2>/dev/null | grep -q "Successful"; then
            print_status "$PRIMARY_SITE_NAME Drush working"
        else
            print_warning "$PRIMARY_SITE_NAME Drush may have issues"
        fi
    else
        print_error "$PRIMARY_SITE_NAME Drush not found"
    fi
else
    print_warning "$PRIMARY_SITE_NAME directory missing, skipping Drush check"
fi

if [ -d "$SECONDARY_SITE_DIR" ]; then
    cd "$SECONDARY_SITE_DIR"
    if [ -f "vendor/bin/drush" ]; then
        if ./vendor/bin/drush status --fields=bootstrap 2>/dev/null | grep -q "Successful"; then
            print_status "$SECONDARY_SITE_NAME Drush working"
        else
            print_warning "$SECONDARY_SITE_NAME Drush may have issues"
        fi
    else
        print_error "$SECONDARY_SITE_NAME Drush not found"
    fi
else
    print_warning "$SECONDARY_SITE_NAME directory missing, skipping Drush check"
fi

# Check custom modules on primary site
print_info "Checking custom modules on $PRIMARY_SITE_NAME..."
if [ -d "$PRIMARY_SITE_DIR" ]; then
    cd "$PRIMARY_SITE_DIR"

    if [ "$PRIMARY_SITE_NAME" = "Forseti" ]; then
        CUSTOM_MODULES=("ai_conversation" "amisafe" "agent_evaluation" "forseti_content")
    else
        CUSTOM_MODULES=("professional_website_content" "ai_conversation" "stli_site_customizations" "job_application_automation" "resume_tailoring")
    fi

    for module in "${CUSTOM_MODULES[@]}"; do
        if [ -d "web/modules/custom/$module" ]; then
            if ./vendor/bin/drush pm:list --status=enabled 2>/dev/null | grep -q "$module"; then
                print_status "Custom module '$module' is enabled"
            else
                print_warning "Custom module '$module' exists but not enabled"
            fi
        else
            print_warning "Custom module '$module' directory not found"
        fi
    done
else
    print_warning "$PRIMARY_SITE_NAME directory missing, skipping custom module checks"
fi

# Check custom theme
if [ -d "$PRIMARY_SITE_DIR" ]; then
    cd "$PRIMARY_SITE_DIR"

    if [ "$PRIMARY_SITE_NAME" = "Forseti" ]; then
        CUSTOM_THEME="forseti"
    else
        CUSTOM_THEME="stlouisintegration"
    fi

    if [ -d "web/themes/custom/$CUSTOM_THEME" ]; then
        if ./vendor/bin/drush pm:list --type=theme --status=enabled 2>/dev/null | grep -q "$CUSTOM_THEME"; then
            print_status "Custom theme '$CUSTOM_THEME' is enabled"
        else
            print_warning "Custom theme '$CUSTOM_THEME' exists but not enabled"
        fi
    else
        print_warning "Custom theme '$CUSTOM_THEME' directory not found"
    fi
else
    print_warning "$PRIMARY_SITE_NAME directory missing, skipping theme checks"
fi

# Check OpenClaw runtime integration
print_info "Checking OpenClaw runtime..."
if [ -x "$SCRIPT_DIR/verify-openclaw.sh" ]; then
    if "$SCRIPT_DIR/verify-openclaw.sh" >/dev/null 2>&1; then
        print_status "OpenClaw runtime verification passed"
    else
        print_warning "OpenClaw runtime verification failed (run ./script/verify-openclaw.sh for details)"
    fi
else
    print_warning "OpenClaw verification script not found at $SCRIPT_DIR/verify-openclaw.sh"
fi

print_info "Verification complete!"
echo "========================="
echo "Site URLs:"
echo "- $PRIMARY_SITE_NAME: http://localhost"
echo "- $SECONDARY_SITE_NAME: http://localhost:8080"
echo "========================="