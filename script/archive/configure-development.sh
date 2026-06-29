#!/bin/bash

# St. Louis Integration - Development Configuration Script
# This script sets up development tools and coding standards

set -e  # Exit on any error

echo "=== St. Louis Integration - Configuring Development Environment ==="

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
PROJECT_DIR="/workspaces/stlouisintegration.com/drupal"

# Check if Drupal is installed
if [ ! -d "$PROJECT_DIR" ]; then
    print_error "Drupal project not found. Please run ./install-drupal.sh first."
    exit 1
fi

cd "$PROJECT_DIR"

print_step "1. Installing development dependencies..."

# Install Drupal coding standards
print_status "Installing Drupal Coder and PHP CodeSniffer..."
composer require drupal/coder --dev --no-interaction

# Install additional development tools
print_status "Installing additional development tools..."
composer require phpunit/phpunit symfony/phpunit-bridge --dev --no-interaction

print_step "2. Configuring PHP CodeSniffer..."

# Set up PHP CodeSniffer with Drupal standards
print_status "Configuring PHP CodeSniffer for Drupal standards..."
./vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer

# Set Drupal as the default standard
./vendor/bin/phpcs --config-set default_standard Drupal

print_step "3. Creating development services configuration..."

# Create development.services.yml
print_status "Creating development services configuration..."
cat > web/sites/development.services.yml << 'EOL'
# Local development services.
#
# To activate this feature, follow the instructions at the top of the
# 'example.settings.local.php' file, which sits next to this file.
parameters:
  http.response.debug_cacheability_headers: true
  twig.config:
    debug: true
    auto_reload: true
    cache: false
services:
  cache.backend.null:
    class: Drupal\Core\Cache\NullBackendFactory
EOL

print_step "4. Setting up custom module template..."

# Create a template for custom modules
CUSTOM_MODULES_DIR="$PROJECT_DIR/web/modules/custom"
mkdir -p "$CUSTOM_MODULES_DIR"

print_status "Creating custom module template..."
cat > "$CUSTOM_MODULES_DIR/README.md" << 'EOL'
# Custom Modules

This directory contains custom modules for the St. Louis Integration website.

## Module Structure

Each custom module should follow Drupal 11 standards:

```
module_name/
├── module_name.info.yml
├── module_name.module
├── src/
│   ├── Controller/
│   ├── Form/
│   ├── Plugin/
│   └── Service/
├── config/
│   └── install/
├── templates/
└── tests/
```

## Creating a New Module

1. Create a new directory with your module name
2. Create the `.info.yml` file with module metadata
3. Add your module logic in the appropriate directories
4. Follow Drupal coding standards

## Coding Standards

All custom modules should follow:
- Drupal coding standards
- PSR-4 autoloading
- Proper documentation
- Unit and functional tests where appropriate

## Testing

Run coding standards check:
```bash
../../../vendor/bin/phpcs --standard=Drupal /path/to/your/module
```

Fix coding standards automatically:
```bash
../../../vendor/bin/phpcbf --standard=Drupal /path/to/your/module
```
EOL

print_step "5. Setting up custom theme template..."

# Create a template for custom themes
CUSTOM_THEMES_DIR="$PROJECT_DIR/web/themes/custom"
mkdir -p "$CUSTOM_THEMES_DIR"

print_status "Creating custom theme template..."
cat > "$CUSTOM_THEMES_DIR/README.md" << 'EOL'
# Custom Themes

This directory contains custom themes for the St. Louis Integration website.

## Theme Structure

Each custom theme should follow Drupal 11 standards:

```
theme_name/
├── theme_name.info.yml
├── theme_name.theme
├── theme_name.libraries.yml
├── css/
├── js/
├── images/
├── templates/
└── config/
```

## Creating a New Theme

1. Create a new directory with your theme name
2. Create the `.info.yml` file with theme metadata
3. Add your theme logic and assets
4. Follow Drupal theming best practices

## Development

For theme development with modern tools:
- Use SCSS/Sass for stylesheets
- Use modern JavaScript (ES6+)
- Implement proper build processes
- Ensure mobile-first responsive design

## Testing

Test themes across:
- Multiple browsers
- Different screen sizes
- Accessibility standards
- Performance metrics
EOL

print_step "6. Creating development scripts..."

# Create useful development scripts
SCRIPTS_DIR="$PROJECT_DIR/scripts"
mkdir -p "$SCRIPTS_DIR"

print_status "Creating development utility scripts..."

# Cache clear script
cat > "$SCRIPTS_DIR/clear-cache.sh" << 'EOL'
#!/bin/bash
# Clear Drupal cache
echo "Clearing Drupal cache..."
../vendor/bin/drush cache:rebuild
echo "Cache cleared successfully!"
EOL

# Code standards check script
cat > "$SCRIPTS_DIR/check-standards.sh" << 'EOL'
#!/bin/bash
# Check coding standards for custom modules and themes
echo "Checking coding standards..."

# Check custom modules
if [ -d "../web/modules/custom" ]; then
    echo "Checking custom modules..."
    ../vendor/bin/phpcs --standard=Drupal ../web/modules/custom
fi

# Check custom themes
if [ -d "../web/themes/custom" ]; then
    echo "Checking custom themes..."
    ../vendor/bin/phpcs --standard=Drupal ../web/themes/custom
fi

echo "Standards check completed!"
EOL

# Fix coding standards script
cat > "$SCRIPTS_DIR/fix-standards.sh" << 'EOL'
#!/bin/bash
# Fix coding standards for custom modules and themes
echo "Fixing coding standards..."

# Fix custom modules
if [ -d "../web/modules/custom" ]; then
    echo "Fixing custom modules..."
    ../vendor/bin/phpcbf --standard=Drupal ../web/modules/custom
fi

# Fix custom themes
if [ -d "../web/themes/custom" ]; then
    echo "Fixing custom themes..."
    ../vendor/bin/phpcbf --standard=Drupal ../web/themes/custom
fi

echo "Standards fixing completed!"
EOL

# Database backup script
cat > "$SCRIPTS_DIR/backup-database.sh" << 'EOL'
#!/bin/bash
# Backup database
BACKUP_DIR="../backups"
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/db-backup-$(date +%Y%m%d-%H%M%S).sql"

echo "Creating database backup..."
../vendor/bin/drush sql:dump --result-file="$BACKUP_FILE"
echo "Database backup created: $BACKUP_FILE"
EOL

# Make scripts executable
chmod +x "$SCRIPTS_DIR"/*.sh

print_step "7. Setting up Git hooks (optional)..."

# Create pre-commit hook for coding standards
GIT_HOOKS_DIR="$PROJECT_DIR/.git/hooks"
if [ -d "$GIT_HOOKS_DIR" ]; then
    print_status "Creating Git pre-commit hook for coding standards..."
    cat > "$GIT_HOOKS_DIR/pre-commit" << 'EOL'
#!/bin/bash
# Pre-commit hook to check coding standards

echo "Running coding standards check..."

# Get list of changed PHP files
CHANGED_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' | grep -E '(modules/custom|themes/custom)')

if [ ! -z "$CHANGED_FILES" ]; then
    # Check coding standards for changed files
    ./vendor/bin/phpcs --standard=Drupal $CHANGED_FILES
    
    if [ $? -ne 0 ]; then
        echo "Coding standards check failed. Please fix the issues before committing."
        echo "You can run './scripts/fix-standards.sh' to automatically fix some issues."
        exit 1
    fi
fi

echo "Coding standards check passed!"
EOL
    
    chmod +x "$GIT_HOOKS_DIR/pre-commit"
    print_status "Git pre-commit hook installed"
else
    print_warning "Git hooks directory not found. Skipping Git hook setup."
fi

print_step "8. Final configuration..."

# Clear cache after all configuration
print_status "Clearing cache after configuration..."
./vendor/bin/drush cache:rebuild

# Set final permissions
print_status "Setting final permissions..."
chmod -R 755 web/modules/custom web/themes/custom
chmod -R 644 web/sites/default/settings*.php

print_status "Development environment configuration completed!"

echo "========================="
echo "Development Tools Installed:"
echo "========================="
echo "✓ Drupal Coder (coding standards)"
echo "✓ PHP CodeSniffer (PHPCS)"
echo "✓ PHPUnit (testing framework)"
echo "✓ Development services configuration"
echo "✓ Custom module and theme templates"
echo "✓ Development utility scripts"
echo "✓ Git pre-commit hook (if Git repo exists)"
echo "========================="

print_status "Available commands:"
echo "- Check coding standards: ./scripts/check-standards.sh"
echo "- Fix coding standards: ./scripts/fix-standards.sh"
echo "- Clear cache: ./scripts/clear-cache.sh"
echo "- Backup database: ./scripts/backup-database.sh"
echo "- Drush commands: ./vendor/bin/drush [command]"

print_status "Development setup complete! You can now start developing custom modules and themes."

print_warning "Remember to:"
echo "- Follow Drupal coding standards"
echo "- Test your code thoroughly"
echo "- Document your custom modules and themes"
echo "- Regular database backups during development"