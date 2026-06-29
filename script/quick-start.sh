#!/bin/bash

# Quick Start Script for Multi-Site Drupal Workspace
# Use this after workspace restarts to quickly get both sites running

set -e

echo "=== Multi-Site Drupal Quick Start ==="

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

print_step "1. Starting services..."
print_status "Starting MySQL..."
sudo service mysql start

print_status "Starting Apache..."
sudo service apache2 start

print_step "2. Verifying Apache configuration..."
if ! grep -q "Listen 8080" /etc/apache2/ports.conf; then
    print_warning "Adding port 8080 to Apache configuration..."
    sudo bash -c "echo 'Listen 8080' >> /etc/apache2/ports.conf"
fi

if [ ! -f "/etc/apache2/sites-available/theoryofconspiracies.conf" ]; then
    print_warning "Theory of Conspiracies Apache site not configured. Run complete-setup.sh"
else
    sudo a2ensite theoryofconspiracies.conf >/dev/null 2>&1 || true
fi

print_status "Reloading Apache configuration..."
sudo service apache2 reload

print_step "3. Testing site accessibility..."
sleep 2

if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|302\|301"; then
    print_status "✅ St. Louis Integration site is accessible at http://localhost"
else
    print_warning "⚠️  St. Louis Integration site may need configuration"
fi

if curl -s -o /dev/null -w "%{http_code}" "http://localhost:8080" | grep -q "200\|302\|301"; then
    print_status "✅ Theory of Conspiracies site is accessible at http://localhost:8080"
else
    print_warning "⚠️  Theory of Conspiracies site may need configuration"
fi

print_step "Quick start complete!"
echo "========================="
echo "Your multi-site environment is ready:"
echo "- St. Louis Integration: http://localhost"
echo "- Theory of Conspiracies: http://localhost:8080"
echo ""
echo "Admin credentials (both sites):"
echo "- Username: admin"
echo "- Password: set via .env (ADMIN_PASSWORD)"
echo ""
echo "Quick commands:"
echo "- Full verification: ./scripts/verify-setup.sh"
echo "- Complete setup: ./scripts/complete-setup.sh"
echo "========================="