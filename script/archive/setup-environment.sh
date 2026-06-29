#!/bin/bash

# St. Louis Integration - Environment Setup Script
# This script installs all necessary dependencies for Drupal 11 development
# including resume text extraction tools for the Job Application Automation module

set -e  # Exit on any error

echo "=== St. Louis Integration - Setting up Development Environment ==="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root. Please run as a regular user."
   exit 1
fi

print_status "Updating package lists..."
sudo apt update

# Check PHP version
print_status "Checking PHP installation..."
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    print_status "PHP $PHP_VERSION is installed"
    
    # Check if PHP version is 8.1 or higher
    if php -r "exit(version_compare(PHP_VERSION, '8.1.0', '>=') ? 0 : 1);"; then
        print_status "PHP $PHP_VERSION meets Drupal 11 requirements (8.1+)"
    else
        print_error "PHP 8.1 or higher is required for Drupal 11. Current version: $PHP_VERSION"
        print_status "Installing PHP 8.3..."
        sudo apt install -y software-properties-common
        sudo add-apt-repository ppa:ondrej/php -y
        sudo apt update
        sudo apt install -y php8.3 php8.3-cli php8.3-fpm
    fi
else
    print_status "Installing PHP 8.3..."
    sudo apt install -y software-properties-common
    sudo add-apt-repository ppa:ondrej/php -y
    sudo apt update
    sudo apt install -y php8.3 php8.3-cli php8.3-fpm
fi

# Ensure PHP 8.3 is installed regardless of system PHP version
print_status "Ensuring PHP 8.3 is properly installed..."
if ! dpkg -l | grep -q "^ii.*php8.3"; then
    print_status "Installing PHP 8.3 packages..."
    sudo apt install -y php8.3 php8.3-cli php8.3-fpm
fi

# Install required PHP extensions (including DOM for Drupal)
print_status "Checking PHP extensions..."
REQUIRED_EXTENSIONS=("gd" "xml" "mbstring" "curl" "zip" "bcmath" "json" "tokenizer" "fileinfo" "intl" "dom")
MISSING_EXTENSIONS=()

# Note: xml extension provides dom, simplexml, xmlreader, xmlwriter, and xsl modules

# Check standard extensions
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! php -m | grep -q "^$ext$"; then
        MISSING_EXTENSIONS+=("php8.3-$ext")
        print_warning "PHP extension '$ext' is missing"
    else
        print_status "PHP extension '$ext' is already installed"
    fi
done

# Check MySQL extensions (mysqli, pdo_mysql, or mysqlnd)
if ! php -m | grep -qE "^(mysqli|pdo_mysql|mysqlnd)$"; then
    MISSING_EXTENSIONS+=("php8.3-mysql")
    print_warning "PHP MySQL extension is missing"
else
    print_status "PHP MySQL extension is already installed"
fi

# Check OPcache (shows as "Zend OPcache")
if ! php -m | grep -qi "opcache"; then
    MISSING_EXTENSIONS+=("php8.3-opcache")
    print_warning "PHP OPcache extension is missing"
else
    print_status "PHP OPcache extension is already installed"
fi

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    print_status "Installing missing PHP extensions: ${MISSING_EXTENSIONS[*]}"
    sudo apt install -y "${MISSING_EXTENSIONS[@]}"
else
    print_status "All required PHP extensions are already installed"
fi

# Ensure critical extensions are installed with correct package names
print_status "Ensuring critical PHP extensions are properly installed..."
CRITICAL_EXTENSIONS=("php8.3-xml" "php8.3-mysql")
for ext_package in "${CRITICAL_EXTENSIONS[@]}"; do
    if ! dpkg -l | grep -q "^ii.*$ext_package"; then
        print_status "Installing critical extension: $ext_package"
        sudo apt install -y "$ext_package"
    else
        print_status "Critical extension $ext_package is already installed"
    fi
done

# Install Composer
print_status "Checking Composer installation..."
if command -v composer &> /dev/null; then
    print_status "Composer is already installed: $(composer --version)"
else
    print_status "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
fi

# Install MySQL/MariaDB
print_status "Checking MySQL/MariaDB installation..."
if command -v mysql &> /dev/null; then
    print_status "MySQL is already installed"
else
    print_status "Installing MySQL server..."
    sudo apt install -y mysql-server mysql-client
    
    # Secure MySQL installation
    print_warning "Please run 'sudo mysql_secure_installation' after this script completes"
fi

# Install Apache (optional, for local development)
print_status "Checking Apache installation..."
if command -v apache2 &> /dev/null; then
    print_status "Apache is already installed"
else
    print_status "Installing Apache web server..."
    sudo apt install -y apache2
    sudo a2enmod rewrite
    sudo systemctl enable apache2
fi

# Install Apache PHP module
print_status "Configuring Apache PHP 8.3 module..."

# First disable any conflicting PHP modules
for php_ver in 7.4 8.0 8.1 8.2; do
    if sudo a2query -m php$php_ver 2>/dev/null; then
        print_status "Disabling PHP $php_ver module..."
        sudo a2dismod php$php_ver 2>/dev/null || true
    fi
done

# Install and enable PHP 8.3 module for Apache
if ! dpkg -l | grep -q "^ii.*libapache2-mod-php8.3"; then
    print_status "Installing Apache PHP 8.3 module..."
    sudo apt install -y libapache2-mod-php8.3
fi

# Ensure PHP 8.3 module is enabled
print_status "Enabling PHP 8.3 module for Apache..."
sudo a2enmod php8.3

print_status "Apache PHP 8.3 module configured and enabled"

# Install Git
print_status "Checking Git installation..."
if command -v git &> /dev/null; then
    print_status "Git is already installed: $(git --version)"
else
    print_status "Installing Git..."
    sudo apt install -y git
fi

# Install Node.js and npm (for theme development)
print_status "Checking Node.js installation..."
if command -v node &> /dev/null; then
    print_status "Node.js is already installed: $(node --version)"
else
    print_status "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
    sudo apt install -y nodejs
fi

# Install additional development tools
print_status "Checking additional development tools..."
TOOLS=("unzip" "wget" "curl" "vim" "htop")
MISSING_TOOLS=()

for tool in "${TOOLS[@]}"; do
    if ! command -v "$tool" &> /dev/null; then
        MISSING_TOOLS+=("$tool")
        print_warning "Tool '$tool' is missing"
    else
        print_status "Tool '$tool' is already installed"
    fi
done

if [ ${#MISSING_TOOLS[@]} -gt 0 ]; then
    print_status "Installing missing development tools: ${MISSING_TOOLS[*]}"
    sudo apt install -y "${MISSING_TOOLS[@]}"
else
    print_status "All development tools are already installed"
fi

# Install resume text extraction dependencies (Required for Job Application Automation)
print_status "Checking resume text extraction tools..."
TEXT_EXTRACTION_TOOLS=("pdftotext" "docx2txt" "antiword")
TEXT_EXTRACTION_PACKAGES=("poppler-utils" "docx2txt" "antiword")
MISSING_TEXT_TOOLS=()

for i in "${!TEXT_EXTRACTION_TOOLS[@]}"; do
    tool="${TEXT_EXTRACTION_TOOLS[$i]}"
    package="${TEXT_EXTRACTION_PACKAGES[$i]}"
    
    if ! command -v "$tool" &> /dev/null; then
        MISSING_TEXT_TOOLS+=("$package")
        print_warning "Text extraction tool '$tool' is missing"
    else
        print_status "Text extraction tool '$tool' is already installed"
    fi
done

if [ ${#MISSING_TEXT_TOOLS[@]} -gt 0 ]; then
    print_status "Installing missing text extraction tools: ${MISSING_TEXT_TOOLS[*]}"
    sudo apt install -y "${MISSING_TEXT_TOOLS[@]}"
    print_status "Resume text extraction dependencies installed successfully"
else
    print_status "All text extraction tools are already installed"
fi

# Configure PHP 8.3 as default version
print_status "Configuring PHP 8.3 as default version..."

# First, ensure update-alternatives is properly configured
if command -v update-alternatives &> /dev/null && [ -x "/usr/bin/php8.3" ]; then
    # Remove any existing alternatives to start fresh
    sudo update-alternatives --remove-all php 2>/dev/null || true
    
    # Install PHP 8.3 as the primary alternative with highest priority
    sudo update-alternatives --install /usr/bin/php php /usr/bin/php8.3 100
    
    # Set PHP 8.3 as the default
    sudo update-alternatives --set php /usr/bin/php8.3
    print_status "PHP 8.3 configured as default CLI version via update-alternatives"
else
    print_warning "update-alternatives not available or PHP 8.3 not found"
fi

# Configure PATH and environment for current user
print_status "Configuring environment for PHP 8.3..."

# Update .bashrc to ensure PHP 8.3 takes precedence
BASHRC_FILE="$HOME/.bashrc"
if [ -f "$BASHRC_FILE" ]; then
    # Remove any existing PHP PATH configurations
    grep -v "# PHP 8.3 Configuration" "$BASHRC_FILE" > "${BASHRC_FILE}.tmp" || true
    grep -v "export PATH.*php" "${BASHRC_FILE}.tmp" > "$BASHRC_FILE" || true
    rm -f "${BASHRC_FILE}.tmp"
    
    # Add PHP 8.3 configuration
    echo "" >> "$BASHRC_FILE"
    echo "# PHP 8.3 Configuration" >> "$BASHRC_FILE"
    echo 'export PATH="/usr/bin:$PATH"' >> "$BASHRC_FILE"
    echo 'alias php="/usr/bin/php8.3"' >> "$BASHRC_FILE"
    echo 'alias composer="/usr/bin/php8.3 /usr/local/bin/composer"' >> "$BASHRC_FILE"
    
    print_status "Updated .bashrc with PHP 8.3 configuration"
fi

# Apply environment changes to current session
export PATH="/usr/bin:$PATH"
alias php="/usr/bin/php8.3"
alias composer="/usr/bin/php8.3 /usr/local/bin/composer"

# Verify the configuration
CURRENT_PHP_VERSION=$(/usr/bin/php8.3 --version 2>/dev/null | head -n1 | grep -o 'PHP [0-9]\+\.[0-9]\+' | grep -o '[0-9]\+\.[0-9]\+' || echo "unknown")
if [[ "$CURRENT_PHP_VERSION" == "8.3" ]]; then
    print_status "✅ PHP 8.3 is properly configured and accessible"
    
    # Test composer with PHP 8.3
    COMPOSER_PHP_VERSION=$(/usr/bin/php8.3 /usr/local/bin/composer --version 2>/dev/null | grep -o 'PHP version [0-9]\+\.[0-9]\+' | grep -o '[0-9]\+\.[0-9]\+' || echo "unknown")
    if [[ "$COMPOSER_PHP_VERSION" == "8.3" ]]; then
        print_status "✅ Composer is now using PHP 8.3"
    else
        print_warning "⚠️  Composer may still be using older PHP version: $COMPOSER_PHP_VERSION"
    fi
else
    print_warning "⚠️  PHP 8.3 configuration verification failed"
fi

# Set up PHP configuration for development
print_status "Configuring PHP for development..."
PHP_INI_DIR=$(/usr/bin/php8.3 --ini | grep "Configuration File" | awk '{print $4}' | xargs dirname)
PHP_INI_FILE="$PHP_INI_DIR/php.ini"

if [ -f "$PHP_INI_FILE" ]; then
    # Backup original php.ini
    sudo cp "$PHP_INI_FILE" "$PHP_INI_FILE.backup"
    
    # Update PHP settings for Drupal development
    sudo sed -i 's/memory_limit = .*/memory_limit = 256M/' "$PHP_INI_FILE"
    sudo sed -i 's/max_execution_time = .*/max_execution_time = 300/' "$PHP_INI_FILE"
    sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 64M/' "$PHP_INI_FILE"
    sudo sed -i 's/post_max_size = .*/post_max_size = 64M/' "$PHP_INI_FILE"
    
    print_status "PHP configuration updated for Drupal development"
fi

# Configure MySQL database for Drupal
print_status "Configuring MySQL database..."
DRUPAL_DB_PASSWORD="${DRUPAL_DB_PASSWORD:-}"
DRUPAL_ADMIN_PASSWORD="${DRUPAL_ADMIN_PASSWORD:-CHANGE_ME}"
if [ -z "$DRUPAL_DB_PASSWORD" ]; then
    print_error "DRUPAL_DB_PASSWORD is not set. Export it before running this script."
    exit 1
fi

# Ensure MySQL is running before database operations
if ! sudo mysql -e "SELECT 1;" &>/dev/null; then
    print_status "MySQL not running, attempting to start..."
    sudo service mysql start || print_warning "Failed to start MySQL service"
    sleep 2
fi

if sudo mysql -e "SELECT User FROM mysql.user WHERE User='drupal_user' AND Host='127.0.0.1';" 2>/dev/null | grep -q drupal_user; then
    print_status "MySQL drupal_user already exists"
else
    print_status "Creating MySQL database and user for Drupal..."
    sudo mysql <<EOF
CREATE DATABASE IF NOT EXISTS stlouisintegration_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'drupal_user'@'127.0.0.1' IDENTIFIED BY '${DRUPAL_DB_PASSWORD}';
GRANT ALL PRIVILEGES ON stlouisintegration_dev.* TO 'drupal_user'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
    print_status "MySQL database 'stlouisintegration_dev' and user 'drupal_user' created"
fi

# Create private files directory for Drupal
print_status "Creating Drupal private files directory..."
if [ ! -d "/var/private/stlouisintegration" ]; then
    sudo mkdir -p /var/private/stlouisintegration
    sudo chown -R $USER:$USER /var/private/stlouisintegration
    sudo chmod -R 775 /var/private/stlouisintegration
    print_status "Private files directory created at /var/private/stlouisintegration"
else
    print_status "Private files directory already exists"
fi

# Configure Apache virtual host for Drupal
print_status "Configuring Apache virtual host..."
DRUPAL_ROOT="/workspaces/stlouisintegration.com/drupal/web"
if [ -d "$DRUPAL_ROOT" ]; then
    sudo bash -c "cat > /etc/apache2/sites-available/000-default.conf" <<'EOF'
<VirtualHost *:80>
        ServerAdmin webmaster@localhost
        DocumentRoot /workspaces/stlouisintegration.com/drupal/web

        <Directory /workspaces/stlouisintegration.com/drupal/web>
                Options Indexes FollowSymLinks
                AllowOverride All
                Require all granted
        </Directory>

        ErrorLog ${APACHE_LOG_DIR}/error.log
        CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF
    print_status "Apache virtual host configured for Drupal"
else
    print_warning "Drupal directory not found at $DRUPAL_ROOT - skipping Apache configuration"
fi

# Start services
print_status "Starting services..."
if command -v systemctl &> /dev/null && systemctl is-system-running &> /dev/null; then
    sudo systemctl start mysql
    # Restart Apache to ensure PHP 8.3 module is properly loaded
    sudo systemctl restart apache2
    
    print_status "Enabling services to start on boot..."
    sudo systemctl enable mysql
    sudo systemctl enable apache2
else
    print_status "Using service command (systemd not available)..."
    sudo service mysql start
    # Restart Apache to ensure PHP 8.3 module is properly loaded
    sudo service apache2 restart
    print_status "Services started (auto-enable not available in container)"
fi

# Verify Apache is using PHP 8.3
print_status "Verifying Apache PHP configuration..."
if curl -s "http://localhost" | grep -q "Drupal\|PHP" || curl -s -I "http://localhost" | grep -q "HTTP/1.[01] [23][0-9][0-9]"; then
    print_status "✅ Apache is serving content successfully"
else
    print_warning "⚠️  Apache may need additional configuration"
fi

# Verify installations
print_status "Verifying installations..."
echo "========================="
echo "PHP Version (system): $(/usr/bin/php8.3 --version | head -n 1)"
echo "PHP 8.3 Version: $(/usr/bin/php8.3 --version | head -n 1)"
echo "Apache PHP Module: $(apache2ctl -M 2>/dev/null | grep php || echo 'Not found')"
echo "Composer Version: $(/usr/bin/php8.3 /usr/local/bin/composer --version)"
echo "MySQL Version: $(mysql --version)"
echo "Apache Version: $(apache2 -v | head -n 1)"
echo "Git Version: $(git --version)"
echo "Node.js Version: $(node --version)"
echo "npm Version: $(npm --version)"
echo "========================="

# Validate critical PHP extensions
print_status "Validating PHP 8.3 extensions..."
REQUIRED_EXTENSIONS=("dom" "mysql" "mysqli" "pdo_mysql" "xml" "gd" "curl" "zip" "intl")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! /usr/bin/php8.3 -m | grep -q "^$ext$"; then
        MISSING_EXTENSIONS+=("$ext")
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -eq 0 ]; then
    print_status "✅ All required PHP 8.3 extensions are loaded"
else
    print_warning "⚠️  Missing PHP 8.3 extensions: ${MISSING_EXTENSIONS[*]}"
fi

echo "========================="

# Install Composer dependencies for Drupal if composer.json exists
DRUPAL_ROOT="/workspaces/stlouisintegration.com/drupal"
if [ -f "$DRUPAL_ROOT/composer.json" ]; then
    print_status "Installing Drupal Composer dependencies..."
    cd "$DRUPAL_ROOT"
    
    # Check if vendor directory exists and has packages
    if [ ! -d "vendor" ] || [ -z "$(ls -A vendor 2>/dev/null)" ]; then
        print_status "Vendor directory is empty or missing, installing dependencies..."
        /usr/bin/php8.3 /usr/local/bin/composer install --no-dev --optimize-autoloader
        print_status "✅ Composer dependencies installed successfully"
    else
        # Check for specific missing packages that cause common errors
        if [ ! -d "vendor/mtdowling" ]; then
            print_warning "Missing critical packages detected, reinstalling dependencies..."
            /usr/bin/php8.3 /usr/local/bin/composer install --no-dev --optimize-autoloader
            print_status "✅ Composer dependencies reinstalled successfully"
        else
            print_status "✅ Composer dependencies already installed"
        fi
    fi
    
    # Verify critical packages are installed
    if [ -d "vendor/mtdowling/jmespath.php" ]; then
        print_status "✅ Critical packages (jmespath.php) verified"
    else
        print_warning "⚠️  Some packages may still be missing"
    fi
    
    # Return to original directory
    cd - > /dev/null
else
    print_warning "⚠️  Drupal composer.json not found at $DRUPAL_ROOT"
fi

print_status "Environment setup completed successfully!"
print_status "Next steps:"
echo "1. Reload environment: source ~/.bashrc"
echo "2. Install Drupal: cd drupal && ./vendor/bin/drush site:install standard --db-url=mysql://drupal_user:${DRUPAL_DB_PASSWORD}@127.0.0.1:3306/stlouisintegration_dev --site-name='St. Louis Integration Dev' --account-name=admin --account-pass=${DRUPAL_ADMIN_PASSWORD} -y"
echo "3. Access site at http://localhost with admin/${DRUPAL_ADMIN_PASSWORD} credentials"

print_warning "Remember to:"
echo "- Reload environment first: source ~/.bashrc"
echo "- PHP 8.3 is now default via aliases: php = /usr/bin/php8.3, composer uses PHP 8.3"
echo "- Composer dependencies: Automatically installed during setup"
echo "- Start services if needed: sudo service mysql start && sudo service apache2 start"
echo "- Site credentials: Username 'admin' / Password '${DRUPAL_ADMIN_PASSWORD}'"

# Test website availability
print_status "Testing website availability..."
if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|302\|301"; then
    print_status "✅ Website is accessible at http://localhost"
    print_status "🌐 Homepage response: $(curl -s -w "HTTP %{http_code}" "http://localhost" | tail -n1)"
else
    print_warning "⚠️  Website may not be fully configured yet"
    print_status "📝 Check Apache configuration and Drupal setup"
fi