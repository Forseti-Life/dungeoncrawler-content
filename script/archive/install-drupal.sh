#!/bin/bash

# St. Louis Integration - Drupal 11 Installation Script
# This script creates a new Drupal 11 project and runs the installation

set -e  # Exit on any error

echo "=== St. Louis Integration - Installing Drupal 11 ==="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Configuration
PROJECT_NAME="stlouisintegration"
PROJECT_DIR="/workspaces/stlouisintegration.com/drupal"
DB_NAME="stlouisintegration_dev"
DB_USER="drupal_user"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_HOST="localhost"
SITE_NAME="St. Louis Integration"
ADMIN_USER="admin"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
ADMIN_EMAIL="support@forseti.life"

# Check if .env file exists and source it
ENV_FILE="/workspaces/stlouisintegration.com/.env"
if [ -f "$ENV_FILE" ]; then
    print_status "Loading configuration from .env file..."
    source "$ENV_FILE"
fi

if [ -z "${DB_PASSWORD}" ] || [ -z "${ADMIN_PASSWORD}" ]; then
    print_error "DB_PASSWORD and ADMIN_PASSWORD must be set in the environment."
    exit 1
fi

print_step "1. Checking prerequisites..."

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed. Please run ./setup-environment.sh first."
    exit 1
fi

# Check if MySQL is running
if ! sudo systemctl is-active --quiet mysql; then
    print_status "Starting MySQL service..."
    sudo systemctl start mysql
fi

print_step "2. Setting up database..."

# Create database and user
print_status "Creating database and user..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';"
sudo mysql -e "FLUSH PRIVILEGES;"

print_status "Database '${DB_NAME}' created successfully"

print_step "3. Creating Drupal 11 project..."

# Remove existing project directory if it exists
if [ -d "$PROJECT_DIR" ]; then
    print_warning "Existing Drupal directory found. Removing..."
    rm -rf "$PROJECT_DIR"
fi

# Create Drupal project using Composer
print_status "Creating Drupal 11 project with Composer..."
cd /workspaces/stlouisintegration.com
composer create-project drupal/recommended-project:^11.0 drupal --no-interaction

# Move into the project directory
cd "$PROJECT_DIR"

print_step "4. Installing additional dependencies..."

# Install Drush
print_status "Installing Drush..."
composer require drush/drush --no-interaction

# Install useful development modules
print_status "Installing development modules..."
composer require drupal/devel drupal/admin_toolbar drupal/pathauto drupal/metatag --no-interaction

print_step "5. Configuring file permissions..."

# Set up proper file permissions
print_status "Setting up file permissions..."
chmod 755 web/sites/default
mkdir -p web/sites/default/files
chmod 775 web/sites/default/files

# Copy default settings file
cp web/sites/default/default.settings.php web/sites/default/settings.php
chmod 664 web/sites/default/settings.php

print_step "6. Installing Drupal..."

# Install Drupal using Drush
print_status "Running Drupal installation..."
./vendor/bin/drush site:install standard \
    --db-url="mysql://${DB_USER}:${DB_PASSWORD}@127.0.0.1:3306/${DB_NAME}" \
    --site-name="${SITE_NAME}" \
    --account-name="${ADMIN_USER}" \
    --account-pass="${ADMIN_PASSWORD}" \
    --account-mail="${ADMIN_EMAIL}" \
    --yes

print_step "7. Enabling additional modules..."

# Enable useful modules
print_status "Enabling development and utility modules..."
./vendor/bin/drush en devel admin_toolbar admin_toolbar_tools pathauto metatag -y

print_step "8. Setting up custom directories..."

# Create custom modules and themes directories
print_status "Creating custom development directories..."
mkdir -p web/modules/custom
mkdir -p web/themes/custom
mkdir -p config/sync

# Set proper permissions
chmod 755 web/modules/custom
chmod 755 web/themes/custom
chmod 755 config/sync

print_step "9. Configuring development settings..."

# Add development settings to settings.php
print_status "Adding development-specific settings..."
cat >> web/sites/default/settings.php << 'EOL'

/**
 * Development-specific settings
 */

// Enable local development settings
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}

// Configuration sync directory
$settings['config_sync_directory'] = '../config/sync';

// Disable CSS and JS aggregation for development
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;

// Enable verbose error reporting
$config['system.logging']['error_level'] = 'verbose';

// Disable caching for development
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['page'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
EOL

# Create settings.local.php for local development
print_status "Creating local development settings..."
cat > web/sites/default/settings.local.php << EOL
<?php

/**
 * Local development settings
 */

// Database configuration
\$databases['default']['default'] = [
  'database' => '${DB_NAME}',
  'username' => '${DB_USER}',
  'password' => '${DB_PASSWORD}',
  'host' => '127.0.0.1',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

// Hash salt for local development
\$settings['hash_salt'] = '$(openssl rand -base64 32)';

// Development services
\$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';

// Skip file system permissions hardening
\$settings['skip_permissions_hardening'] = TRUE;

// Disable caching
\$settings['cache']['bins']['render'] = 'cache.backend.null';
\$settings['cache']['bins']['page'] = 'cache.backend.null';
\$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';

// Enable local development mode
\$config['system.performance']['css']['preprocess'] = FALSE;
\$config['system.performance']['js']['preprocess'] = FALSE;
EOL

chmod 644 web/sites/default/settings.local.php

print_step "10. Final configuration..."

# Clear cache
print_status "Clearing Drupal cache..."
./vendor/bin/drush cache:rebuild

# Set final permissions
print_status "Setting final file permissions..."
chmod 644 web/sites/default/settings.php
chmod 444 web/sites/default/settings.local.php

print_status "Drupal 11 installation completed successfully!"

echo "========================="
echo "Installation Summary:"
echo "========================="
echo "Site Name: ${SITE_NAME}"
echo "Project Directory: ${PROJECT_DIR}"
echo "Database: ${DB_NAME}"
echo "Admin User: ${ADMIN_USER}"
echo "Admin Password: ${ADMIN_PASSWORD}"
echo "Admin Email: ${ADMIN_EMAIL}"
echo "========================="

print_status "Next steps:"
echo "1. Run './configure-development.sh' to set up development tools"
echo "2. Configure a web server to serve the site from ${PROJECT_DIR}/web"
echo "3. Access your site at the configured domain"
echo "4. Start developing custom modules in web/modules/custom/"

print_warning "Important:"
echo "- Change the admin password after first login"
echo "- Configure your web server document root to point to ${PROJECT_DIR}/web"
echo "- The site is configured for development - disable debug settings for production"