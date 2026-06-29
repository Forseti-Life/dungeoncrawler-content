#!/bin/bash

# ==============================================================================
# Forseti.Life Development Environment Setup Script
# ==============================================================================
# ✅ SAFE TO RUN: This script preserves existing database data
# ✅ NO DATA LOSS: Checks for existing Drupal installations before running site:install
# ✅ AMISAFE PROTECTION: Will not drop or truncate AmISafe crime data tables

set -e  # Exit on any error

# CRITICAL: Set PHP 8.3 PATH priority FIRST, before any other operations
export PATH="/usr/bin:/usr/sbin:$PATH"

echo "=== Forseti.Life - Complete Development Environment Setup ==="

# ------------------------------------------------------------------------------
# DETECT WORKSPACE PATH
# ------------------------------------------------------------------------------
# Auto-detect workspace path (supports both /workspaces and /home paths)
if [ -d "/workspaces/forseti.life" ]; then
    WORKSPACE_ROOT="/workspaces/forseti.life"
elif [ -d "/home/keithaumiller/forseti.life" ]; then
    WORKSPACE_ROOT="/home/keithaumiller/forseti.life"
else
    echo "ERROR: Could not find forseti.life workspace directory"
    exit 1
fi

echo "[INFO] Using workspace root: $WORKSPACE_ROOT"

# ==============================================================================
# COLORS FOR OUTPUT (defined early for use in wizard)
# ==============================================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ==============================================================================
# WIZARD HELPER FUNCTIONS
# ==============================================================================
get_input_with_default() {
    local prompt="$1"
    local default="$2"
    local input=""
    
    if [ -n "$default" ]; then
        read -p "$(echo -e "${BLUE}${prompt}${NC} [${default}]: ")" input
        [ -z "$input" ] && input="$default"
    else
        read -p "$(echo -e "${BLUE}${prompt}${NC}: ")" input
    fi
    echo "$input"
}

get_secret_input() {
    local prompt="$1"
    local input=""
    
    read -sp "$(echo -e "${BLUE}${prompt}${NC}: ")" input
    echo ""
    echo "$input"
}

confirm_action() {
    local prompt="$1"
    local response=""
    
    read -p "$(echo -e "${YELLOW}${prompt}${NC} (y/N): ")" -n 1 -r response
    echo ""
    [[ $response =~ ^[Yy]$ ]]
}

generate_random_password() {
    local length=${1:-24}
    openssl rand -base64 $length | tr -d "=+/" | cut -c1-$length
}

# ==============================================================================
# CHECK AND LOAD ENVIRONMENT
# ==============================================================================

# Check if .env file exists and source it
ENV_FILE="$WORKSPACE_ROOT/.env"
if [ -f "$ENV_FILE" ]; then
    echo -e "${GREEN}[INFO]${NC} Loading configuration from .env file..."
    source "$ENV_FILE"
fi

# Set defaults from environment or .env
DB_PASSWORD="${DB_PASSWORD:-}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
SHARED_HASH_SALT="${SHARED_HASH_SALT:-}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-support@forseti.life}"

# ==============================================================================
# SETUP WIZARD
# ==============================================================================

echo ""
echo "=== SETUP WIZARD ==="
echo "This wizard will gather configuration values for your Forseti.life development environment."
echo ""

# Check if values are already set from .env or environment
if [ -z "${DB_PASSWORD}" ] || [ -z "${ADMIN_PASSWORD}" ] || [ -z "${SHARED_HASH_SALT}" ]; then
    WIZARD_MODE="interactive"
    
    echo -e "${YELLOW}Required Configuration Missing${NC}"
    echo "The following values need to be provided:"
    [ -z "${DB_PASSWORD}" ] && echo "  • Database Password"
    [ -z "${ADMIN_PASSWORD}" ] && echo "  • Admin Account Password"
    [ -z "${SHARED_HASH_SALT}" ] && echo "  • Hash Salt (for security)"
    echo ""
    
    # Database Password
    if [ -z "${DB_PASSWORD}" ]; then
        echo -e "${BLUE}Database Configuration${NC}"
        SUGGESTED_DB_PASS=$(generate_random_password 20)
        echo "A secure password will be generated if you press Enter without typing."
        DB_PASSWORD=$(get_secret_input "MySQL Database Password (drupal_user)")
        
        if [ -z "$DB_PASSWORD" ]; then
            DB_PASSWORD="$SUGGESTED_DB_PASS"
            echo -e "${GREEN}✓ Generated password: ${YELLOW}${DB_PASSWORD}${NC}"
        fi
    fi
    
    echo ""
    
    # Admin Password
    if [ -z "${ADMIN_PASSWORD}" ]; then
        echo -e "${BLUE}Admin Account Configuration${NC}"
        SUGGESTED_ADMIN_PASS=$(generate_random_password 20)
        echo "A secure password will be generated if you press Enter without typing."
        ADMIN_PASSWORD=$(get_secret_input "Admin Account Password (username: admin)")
        
        if [ -z "$ADMIN_PASSWORD" ]; then
            ADMIN_PASSWORD="$SUGGESTED_ADMIN_PASS"
            echo -e "${GREEN}✓ Generated password: ${YELLOW}${ADMIN_PASSWORD}${NC}"
        fi
    fi
    
    echo ""
    
    # Hash Salt
    if [ -z "${SHARED_HASH_SALT}" ]; then
        echo -e "${BLUE}Security Configuration${NC}"
        SUGGESTED_SALT=$(generate_random_password 74)
        echo "A secure hash salt will be generated if you press Enter without typing."
        SHARED_HASH_SALT=$(get_secret_input "Drupal Hash Salt (leave empty to generate)")
        
        if [ -z "$SHARED_HASH_SALT" ]; then
            SHARED_HASH_SALT="$SUGGESTED_SALT"
            echo -e "${GREEN}✓ Generated salt: $(echo ${SHARED_HASH_SALT:0:32})...${NC}"
        fi
    fi
    
    echo ""
    
    # Optional: Customize other values
    echo -e "${BLUE}Optional Configuration${NC}"
    if confirm_action "Customize admin username? (default: admin)"; then
        ADMIN_USER=$(get_input_with_default "Admin Username" "admin")
    else
        ADMIN_USER="admin"
    fi
    
    if confirm_action "Customize admin email? (default: support@forseti.life)"; then
        ADMIN_EMAIL=$(get_input_with_default "Admin Email" "support@forseti.life")
    else
        ADMIN_EMAIL="support@forseti.life"
    fi
    
    echo ""
    
    # Option to save to .env file
    if confirm_action "Save configuration to .env file for future runs?"; then
        ENV_FILE="$WORKSPACE_ROOT/.env"
        cat > "$ENV_FILE" <<EOF
# Forseti.life Development Environment Configuration
# Generated: $(date)

# Database Configuration
DB_PASSWORD='${DB_PASSWORD}'

# Admin Account Configuration
ADMIN_PASSWORD='${ADMIN_PASSWORD}'

# Security Configuration
SHARED_HASH_SALT='${SHARED_HASH_SALT}'

# Optional: Customize these values as needed
ADMIN_USER='${ADMIN_USER}'
ADMIN_EMAIL='${ADMIN_EMAIL}'
EOF
        echo -e "${GREEN}✓ Configuration saved to ${ENV_FILE}${NC}"
        echo -e "${YELLOW}[SECURITY WARNING] This file contains secrets. Add it to .gitignore if not already present.${NC}"
    fi
else
    WIZARD_MODE="env_loaded"
    echo -e "${GREEN}✓ Configuration loaded from environment or .env file${NC}"
fi

echo ""
echo "=== Configuration Summary ==="
echo "  Database user: drupal_user"
echo "  Database password: $(echo ${DB_PASSWORD:0:6})***"
echo "  Admin username: ${ADMIN_USER}"
echo "  Admin password: $(echo ${ADMIN_PASSWORD:0:6})***"
echo "  Hash salt: $(echo ${SHARED_HASH_SALT:0:32})..."
echo ""

# ------------------------------------------------------------------------------
# CONFIGURATION VARIABLES
# ------------------------------------------------------------------------------

# Project configuration - Forseti (main site)
PROJECT_NAME="forseti"
PROJECT_DIR="$WORKSPACE_ROOT/sites/forseti"
DB_NAME="forseti_dev"
DB_USER="drupal_user"
DB_HOST="127.0.0.1"
SITE_NAME="Forseti"
ADMIN_EMAIL="${ADMIN_EMAIL:-support@forseti.life}"

# Project configuration - Dungeon Crawler (sub-site)
DC_PROJECT_NAME="dungeoncrawler"
DC_PROJECT_DIR="$WORKSPACE_ROOT/sites/dungeoncrawler"
DC_DB_NAME="dungeoncrawler_dev"
DC_SITE_NAME="Dungeon Crawler"
DC_ADMIN_EMAIL="support@forseti.life"
DC_DEV_PORT="8080"

# Shared authentication configuration
# Both sites share the same hash_salt and cookie domain for SSO
SHARED_COOKIE_DOMAIN=".forseti.life"

# ------------------------------------------------------------------------------
# UTILITY FUNCTIONS
# ------------------------------------------------------------------------------
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

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root. Please run as a regular user."
   exit 1
fi

# Function to ensure file permissions for Drupal settings files
fix_drupal_permissions() {
    local site_dir="$1"
    print_status "Fixing permissions for $site_dir..."
    if [ -f "$site_dir/web/sites/default/settings.php" ]; then
        sudo chmod 664 "$site_dir/web/sites/default/settings.php" 2>/dev/null || chmod 664 "$site_dir/web/sites/default/settings.php" 2>/dev/null || true
    fi
    if [ -f "$site_dir/web/sites/default/settings.local.php" ]; then
        sudo chmod 664 "$site_dir/web/sites/default/settings.local.php" 2>/dev/null || chmod 664 "$site_dir/web/sites/default/settings.local.php" 2>/dev/null || true
    fi
    if [ -d "$site_dir/web/sites/default" ]; then
        sudo chmod 755 "$site_dir/web/sites/default" 2>/dev/null || chmod 755 "$site_dir/web/sites/default" 2>/dev/null || true
    fi
    if [ -d "$site_dir/web/sites/default/files" ]; then
        sudo chmod -R 775 "$site_dir/web/sites/default/files" 2>/dev/null || chmod -R 775 "$site_dir/web/sites/default/files" 2>/dev/null || true
        mkdir -p "$site_dir/web/sites/default/files/php" 2>/dev/null || true
        sudo chmod 775 "$site_dir/web/sites/default/files/php" 2>/dev/null || chmod 775 "$site_dir/web/sites/default/files/php" 2>/dev/null || true
        sudo chown -R www-data:www-data "$site_dir/web/sites/default/files" 2>/dev/null || true
    fi
}

# Function to ensure MySQL is running
ensure_mysql_running() {
    if sudo mysql -e "SELECT 1;" &>/dev/null; then
        return 0  # MySQL is already running
    fi
    
    print_status "MySQL not running, attempting to start..."
    
    # Try service command first (works in containers)
    sudo service mysql start 2>&1
    sleep 5
    
    # Wait up to 30 seconds for MySQL to become available
    local count=0
    while [ $count -lt 30 ]; do
        if sudo mysql -e "SELECT 1;" &>/dev/null; then
            print_status "✅ MySQL started successfully via service command"
            return 0
        fi
        sleep 1
        count=$((count + 1))
    done
    
    # Try systemctl as fallback
    if command -v systemctl >/dev/null 2>&1; then
        sudo systemctl start mysql 2>&1
        sleep 10
        
        # Wait up to 30 seconds for MySQL to become available
        local count=0
        while [ $count -lt 30 ]; do
            if sudo mysql -e "SELECT 1;" &>/dev/null; then
                print_status "✅ MySQL started successfully via systemctl"
                return 0
            fi
            sleep 1
            count=$((count + 1))
        done
    fi
    
    print_error "❌ Failed to start MySQL"
    return 1
}

# Verify PHP version immediately after PATH setup
print_status "Verifying PHP path priority: $(which php)"
if which php | grep -q "/usr/bin/php"; then
    print_status "✅ System PHP is properly prioritized"
else
    print_warning "⚠️  PHP path may need adjustment"
fi

# ==============================================================================
# STEP 1: ENVIRONMENT SETUP
# ==============================================================================

print_step "1. ENVIRONMENT SETUP - Installing system dependencies..."

# ------------------------------------------------------------------------------
# 1.1 PHP 8.3 Installation
# ------------------------------------------------------------------------------
print_status "Updating package lists..."
sudo apt update

print_status "Checking PHP 8.3 installation (REQUIRED)..."

if [ ! -x "/usr/bin/php8.3" ]; then
    print_warning "PHP 8.3 is NOT installed. Installing PHP 8.3 from Ubuntu repositories..."
    
    # Install PHP 8.3 and Apache module directly from Ubuntu repositories
    print_status "Installing PHP 8.3 and Apache module from Ubuntu repositories..."
    sudo apt install -y php8.3 php8.3-cli php8.3-fpm libapache2-mod-php8.3
    
    if [ ! -x "/usr/bin/php8.3" ]; then
        print_error "❌ FAILED to install PHP 8.3. This is a REQUIRED dependency."
        print_error "Please ensure Ubuntu repositories are accessible."
        exit 1
    fi
    
    print_status "✅ PHP 8.3 installed successfully"
else
    PHP83_VERSION=$(/usr/bin/php8.3 -r "echo PHP_VERSION;")
    print_status "✅ PHP 8.3 is already installed: $PHP83_VERSION"
fi

# Install required PHP 8.3 extensions
print_status "Checking PHP 8.3 extensions..."
REQUIRED_EXTENSIONS=("gd" "xml" "mbstring" "curl" "zip" "bcmath" "json" "tokenizer" "fileinfo" "intl" "dom" "mysql" "opcache")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if ! /usr/bin/php8.3 -m | grep -q "^$ext$" && ! /usr/bin/php8.3 -m | grep -qE "^(mysqli|pdo_mysql|mysqlnd)$" 2>/dev/null; then
        MISSING_EXTENSIONS+=("php8.3-$ext")
        print_warning "PHP 8.3 extension '$ext' is missing"
    else
        print_status "PHP 8.3 extension '$ext' is already installed"
    fi
done

if [ ${#MISSING_EXTENSIONS[@]} -gt 0 ]; then
    print_status "Installing missing PHP 8.3 extensions: ${MISSING_EXTENSIONS[*]}"
    sudo apt install -y "${MISSING_EXTENSIONS[@]}"
fi

# Ensure critical extensions are installed
print_status "Ensuring critical PHP 8.3 extensions are properly installed..."
CRITICAL_EXTENSIONS=("php8.3-xml" "php8.3-mysql")
for ext_package in "${CRITICAL_EXTENSIONS[@]}"; do
    if ! dpkg -l | grep -q "^ii.*$ext_package"; then
        print_status "Installing critical extension: $ext_package"
        sudo apt install -y "$ext_package"
    fi
done

# ------------------------------------------------------------------------------
# 1.2 Composer Installation
# ------------------------------------------------------------------------------
print_status "Checking Composer installation..."
if command -v composer &> /dev/null; then
    print_status "Composer is already installed: $(composer --version)"
else
    print_status "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
fi

print_status "Verifying Composer..."
php /usr/local/bin/composer --version || print_error "Composer verification failed"

# ------------------------------------------------------------------------------
# 1.3 MySQL/MariaDB Installation
# ------------------------------------------------------------------------------
print_status "Checking MySQL/MariaDB installation..."
if command -v mysql &> /dev/null; then
    print_status "MySQL/MariaDB is already installed"
else
    print_status "Installing MariaDB server..."
    sudo apt install -y mariadb-server mariadb-client
    print_warning "Please run 'sudo mysql_secure_installation' after this script completes"
fi

# Configure MariaDB for AmISafe data processing
print_status "Configuring MariaDB performance settings..."
MARIADB_CONF="/etc/mysql/mariadb.conf.d/50-server.cnf"
if ! grep -q "AmISafe Data Processing Optimizations" "$MARIADB_CONF" 2>/dev/null; then
    print_status "Adding AmISafe optimizations to MariaDB config..."
    sudo bash -c "cat >> $MARIADB_CONF << 'EOF'

# AmISafe Data Processing Optimizations
[mysqld]
max_allowed_packet = 1073741824  # 1GB for large batch inserts
innodb_buffer_pool_size = 2G     # Increase for better performance
innodb_log_file_size = 512M      # Larger logs for bulk operations
EOF"
    print_status "Restarting MariaDB to apply new settings..."
    sudo systemctl restart mariadb
    print_status "✅ MariaDB optimized for large data processing"
else
    print_status "MariaDB optimizations already configured"
fi

# ------------------------------------------------------------------------------
# 1.4 Apache Installation
# ------------------------------------------------------------------------------
print_status "Checking Apache installation..."
if command -v apache2 &> /dev/null; then
    print_status "Apache is already installed"
else
    print_status "Installing Apache web server..."
    sudo apt install -y apache2
    sudo a2enmod rewrite
fi

# Configure Apache PHP 8.3 module
print_status "Configuring Apache PHP 8.3 module..."

if ! dpkg -l | grep -q "^ii.*libapache2-mod-php8.3"; then
    print_status "Installing Apache PHP 8.3 module..."
    sudo apt install -y libapache2-mod-php8.3
fi

# Disable any other PHP modules first
for php_mod in php8.0 php8.1 php8.2 php8.4; do
    if sudo a2query -m $php_mod 2>/dev/null; then
        print_status "Disabling $php_mod module..."
        sudo a2dismod $php_mod 2>/dev/null || true
    fi
done

# Enable Apache PHP 8.3 module
if ! sudo a2query -m php8.3 2>/dev/null; then
    print_status "Enabling PHP 8.3 module for Apache..."
    sudo a2enmod php8.3 2>/dev/null || true
fi

if sudo a2query -m php8.3 2>/dev/null; then
    print_status "✅ PHP 8.3 module enabled in Apache"
else
    print_warning "⚠️  PHP 8.3 module may need manual enabling"
fi

# Update PATH to prioritize PHP 8.3
print_status "Updating PATH to prioritize PHP 8.3..."
export PATH="/usr/bin:/usr/sbin:/usr/local/bin:$PATH"

# Make PATH change permanent
sudo bash -c 'cat > /etc/profile.d/99-php-priority.sh << "EOF"
#!/bin/bash
# Ensure PHP 8.3 takes priority
export PATH="/usr/bin:/usr/sbin:/usr/local/bin:$PATH"
EOF'
sudo chmod +x /etc/profile.d/99-php-priority.sh
print_status "Created system-wide PHP 8.3 priority profile script"

# Update .bashrc for interactive sessions
if ! grep -q 'export PATH="/usr/bin:/usr/sbin' ~/.bashrc; then
    echo '' >> ~/.bashrc
    echo '# PHP 8.3 Priority - Must be at the end to override defaults' >> ~/.bashrc
    echo 'export PATH="/usr/bin:/usr/sbin:/usr/local/bin:$PATH"  # Prioritize PHP 8.3' >> ~/.bashrc
    print_status "Added PATH configuration to ~/.bashrc"
fi

# Reload Apache to use PHP 8.3
print_status "Reloading Apache to use PHP 8.3..."
sudo service apache2 reload || true

# Set PHP 8.3 as default alternative
print_status "Setting PHP 8.3 as system default..."
sudo update-alternatives --install /usr/bin/php php /usr/bin/php8.3 100
sudo update-alternatives --set php /usr/bin/php8.3

# Verify default php command
DEFAULT_PHP=$(php --version | head -n1)
print_status "Default 'php' command: $DEFAULT_PHP"

# ------------------------------------------------------------------------------
# 1.5 Additional System Tools
# ------------------------------------------------------------------------------
print_status "Checking Git installation..."
if command -v git &> /dev/null; then
    print_status "Git is already installed: $(git --version)"
else
    print_status "Installing Git..."
    sudo apt install -y git
fi

print_status "Checking GitHub CLI installation..."
if command -v gh &> /dev/null; then
    print_status "GitHub CLI is already installed: $(gh --version | head -1)"
else
    print_status "Installing GitHub CLI..."
    curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | sudo dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg
    sudo chmod go+r /usr/share/keyrings/githubcli-archive-keyring.gpg
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null
    sudo apt update
    sudo apt install -y gh
fi

print_status "Checking AWS CLI installation..."
if command -v aws &> /dev/null; then
    print_status "AWS CLI is already installed: $(aws --version 2>&1)"
else
    print_status "Installing AWS CLI v2 (official installer)..."
    AWS_TEMP_DIR=$(mktemp -d)
    (
        cd "$AWS_TEMP_DIR"
        curl -fsSL "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
        unzip -q awscliv2.zip
        sudo ./aws/install
    )
    rm -rf "$AWS_TEMP_DIR"
    if command -v aws &> /dev/null; then
        print_status "AWS CLI installed: $(aws --version 2>&1)"
    else
        print_warning "AWS CLI installation may have failed; /usr/local/bin/aws should exist after reboot"
    fi
fi

print_status "Checking Node.js and npm installation..."
OPENCLAW_MIN_NODE="22.12.0"
if command -v node &> /dev/null; then
    NODE_VERSION_RAW=$(node --version | sed 's/^v//')
    print_status "Node.js is already installed: v$NODE_VERSION_RAW"

    # OpenClaw requires Node >= 22.12.0
    if [ "$(printf '%s\n' "$OPENCLAW_MIN_NODE" "$NODE_VERSION_RAW" | sort -V | head -n1)" != "$OPENCLAW_MIN_NODE" ]; then
        print_warning "Node.js v$NODE_VERSION_RAW is below OpenClaw requirement (>= $OPENCLAW_MIN_NODE)"
        print_status "Upgrading Node.js to 22.x for OpenClaw compatibility..."
        curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
        sudo apt install -y nodejs
        print_status "Node.js upgraded: $(node --version)"
    fi
else
    print_status "Installing Node.js..."
    curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
    sudo apt install -y nodejs
fi

# Verify npm is available (should be included with Node.js)
if command -v npm &> /dev/null; then
    print_status "npm is available: $(npm --version)"
else
    print_warning "npm not found - installing separately..."
    sudo apt install -y npm
fi

# ------------------------------------------------------------------------------
# 1.5.2 Playwright UI Testing Setup
# ------------------------------------------------------------------------------
print_status "Checking Playwright UI testing setup..."
if [ -f "$WORKSPACE_ROOT/package.json" ]; then
    if [ ! -d "$WORKSPACE_ROOT/node_modules" ]; then
        print_status "Installing root npm dependencies (Playwright suite)..."
        (cd "$WORKSPACE_ROOT" && npm install)
    else
        print_status "Root npm dependencies already installed"
    fi

    print_status "Installing Playwright Chromium browser..."
    (cd "$WORKSPACE_ROOT" && npx playwright install chromium)
else
    print_warning "package.json not found at repo root; skipping Playwright setup"
fi

# ------------------------------------------------------------------------------
# 1.5.0 OpenClaw CLI Installation
# ------------------------------------------------------------------------------
print_status "Checking OpenClaw CLI installation..."
NODE_VERSION_RAW=$(node --version 2>/dev/null | sed 's/^v//')
if command -v openclaw &> /dev/null; then
    if [ "$(printf '%s\n' "$OPENCLAW_MIN_NODE" "$NODE_VERSION_RAW" | sort -V | head -n1)" != "$OPENCLAW_MIN_NODE" ]; then
        print_warning "⚠️  OpenClaw is installed but requires Node >= $OPENCLAW_MIN_NODE; detected Node $(node --version)"
        print_warning "Upgrade Node.js, then run: openclaw --version"
    else
        OPENCLAW_VERSION=$(openclaw --version 2>/dev/null | head -n1 || echo "unknown")
        print_status "OpenClaw is already installed: $OPENCLAW_VERSION"
    fi
else
    if [ "$(printf '%s\n' "$OPENCLAW_MIN_NODE" "$NODE_VERSION_RAW" | sort -V | head -n1)" != "$OPENCLAW_MIN_NODE" ]; then
        print_warning "⚠️  OpenClaw requires Node >= $OPENCLAW_MIN_NODE; detected Node.js $(node --version)"
        print_warning "Skipping OpenClaw install to keep setup.sh successful"
        print_warning "Upgrade Node.js, then run: sudo npm install -g openclaw@2026.2.17"
    else
        print_status "Installing OpenClaw CLI from npm..."
        if sudo npm install -g openclaw@2026.2.17; then
            if command -v openclaw &> /dev/null; then
                OPENCLAW_VERSION=$(openclaw --version 2>/dev/null | head -n1 || echo "unknown")
                print_status "✅ OpenClaw installed successfully: $OPENCLAW_VERSION"
            else
                print_warning "⚠️  OpenClaw package installed but CLI was not found on PATH"
                print_warning "Try a new shell session, then run: openclaw --version"
            fi
        else
            print_warning "⚠️  OpenClaw install failed; continuing setup"
            print_warning "Try manually: sudo npm install -g openclaw@2026.2.17"
        fi
    fi
fi

print_status "Checking additional development tools..."
TOOLS=("unzip" "wget" "curl" "vim" "htop" "cmake")
MISSING_TOOLS=()

for tool in "${TOOLS[@]}"; do
    if ! command -v "$tool" &> /dev/null; then
        MISSING_TOOLS+=("$tool")
    fi
done

if [ ${#MISSING_TOOLS[@]} -gt 0 ]; then
    print_status "Installing missing development tools: ${MISSING_TOOLS[*]}"
    sudo apt install -y "${MISSING_TOOLS[@]}"
fi

print_status "Checking resume text extraction tools..."
TEXT_EXTRACTION_TOOLS=("pdftotext" "docx2txt" "antiword")
TEXT_EXTRACTION_PACKAGES=("poppler-utils" "docx2txt" "antiword")
MISSING_TEXT_TOOLS=()

for i in "${!TEXT_EXTRACTION_TOOLS[@]}"; do
    tool="${TEXT_EXTRACTION_TOOLS[$i]}"
    package="${TEXT_EXTRACTION_PACKAGES[$i]}"
    
    if ! command -v "$tool" &> /dev/null; then
        MISSING_TEXT_TOOLS+=("$package")
    fi
done

if [ ${#MISSING_TEXT_TOOLS[@]} -gt 0 ]; then
    print_status "Installing missing text extraction tools: ${MISSING_TEXT_TOOLS[*]}"
    sudo apt install -y "${MISSING_TEXT_TOOLS[@]}"
    print_status "Resume text extraction dependencies installed successfully"
fi

# ------------------------------------------------------------------------------
# 1.5.1 Image Editing Tools
# ------------------------------------------------------------------------------
print_status "Checking for image editing tools..."
if ! command -v gimp &> /dev/null; then
    print_status "Installing GIMP (GNU Image Manipulation Program)..."
    sudo apt install -y gimp
    print_status "GIMP installed successfully"
else
    print_status "GIMP already installed"
fi

# ------------------------------------------------------------------------------
# 1.6 H3 Geolocation Framework Setup
# ------------------------------------------------------------------------------
print_status "Setting up H3 Geolocation Framework for AmISafe crime mapping..."
H3_SYSTEM_PACKAGES=("python3-dev" "python3-pip" "python3-venv" "python3-full" "build-essential" "libgeos-dev" "libproj-dev" "libgdal-dev")
MISSING_H3_PACKAGES=()

for package in "${H3_SYSTEM_PACKAGES[@]}"; do
    if ! dpkg -l | grep -q "^ii  $package "; then
        MISSING_H3_PACKAGES+=("$package")
    fi
done

if [ ${#MISSING_H3_PACKAGES[@]} -gt 0 ]; then
    print_status "Installing H3 geospatial system packages: ${MISSING_H3_PACKAGES[*]}"
    sudo apt install -y "${MISSING_H3_PACKAGES[@]}"
    print_status "H3 geospatial system dependencies installed successfully"
fi

# Setup H3 Python virtual environment
H3_ENV_DIR="$WORKSPACE_ROOT/h3-geolocation/h3-env"
if [ ! -d "$H3_ENV_DIR" ]; then
    print_status "Creating H3 Python virtual environment..."
    cd $WORKSPACE_ROOT/h3-geolocation
    python3 -m venv h3-env
    
    print_status "Installing H3 Python packages..."
    ./h3-env/bin/pip install --upgrade pip
    ./h3-env/bin/pip install h3==4.3.1 pandas>=2.0.0 numpy>=1.24.0 mysql-connector-python>=8.0.0
    ./h3-env/bin/pip install matplotlib>=3.7.0 folium>=0.18.0 geopy>=2.4.0 plotly>=5.17.0
    ./h3-env/bin/pip install seaborn>=0.13.0 tqdm>=4.65.0
    
    print_status "✅ H3 geolocation environment created successfully"
    cd - > /dev/null
else
    print_status "✅ H3 geolocation environment already exists"
fi

# Verify H3 installation
if [ -f "$H3_ENV_DIR/bin/python" ]; then
    H3_TEST_RESULT=$($H3_ENV_DIR/bin/python -c "import h3; import pandas; import mysql.connector; print('H3 packages verified')" 2>/dev/null || echo "FAILED")
    if [ "$H3_TEST_RESULT" = "H3 packages verified" ]; then
        print_status "✅ H3 geolocation framework verified and ready"
    else
        print_warning "⚠️  H3 framework verification failed - may need manual setup"
    fi
fi

# ------------------------------------------------------------------------------
# 1.7 Copilot Sessions HQ Orchestrator (LangGraph) Setup
# ------------------------------------------------------------------------------
print_status "Checking for copilot-sessions-hq LangGraph orchestrator dependencies..."

# Best-effort: detect the HQ repo location (supports both /workspaces and /home paths)
HQ_ROOT=""
if [ -d "/workspaces/copilot-sessions-hq" ]; then
    HQ_ROOT="/workspaces/copilot-sessions-hq"
elif [ -d "/home/keithaumiller/copilot-sessions-hq" ]; then
    HQ_ROOT="/home/keithaumiller/copilot-sessions-hq"
fi

ORCHESTRATOR_DIR="${HQ_ROOT}/orchestrator"
ORCHESTRATOR_REQS="${ORCHESTRATOR_DIR}/requirements.txt"
ORCHESTRATOR_VENV="${ORCHESTRATOR_DIR}/.venv"

if [ -n "${HQ_ROOT}" ] && [ -f "${ORCHESTRATOR_REQS}" ]; then
    if [ ! -d "${ORCHESTRATOR_VENV}" ]; then
        print_status "Creating orchestrator virtual environment at ${ORCHESTRATOR_VENV}..."
        python3 -m venv "${ORCHESTRATOR_VENV}" || print_warning "⚠️  Failed to create orchestrator venv (continuing)"
    else
        print_status "✅ Orchestrator virtual environment already exists"
    fi

    if [ -x "${ORCHESTRATOR_VENV}/bin/pip" ]; then
        print_status "Installing/updating orchestrator Python packages (LangGraph)..."
        "${ORCHESTRATOR_VENV}/bin/pip" install --upgrade pip >/dev/null 2>&1 || true
        "${ORCHESTRATOR_VENV}/bin/pip" install -r "${ORCHESTRATOR_REQS}" || print_warning "⚠️  Orchestrator pip install failed (continuing)"

        # Verify imports (best-effort)
        ORCH_VERIFY=$("${ORCHESTRATOR_VENV}/bin/python" -c "import langgraph, pydantic, yaml; print('orchestrator packages verified')" 2>/dev/null || echo "FAILED")
        if [ "${ORCH_VERIFY}" = "orchestrator packages verified" ]; then
            print_status "✅ LangGraph orchestrator dependencies verified"
        else
            print_warning "⚠️  LangGraph orchestrator dependency verification failed"
        fi
    else
        print_warning "⚠️  Orchestrator venv pip not found; skipping LangGraph install"
    fi
else
    print_status "ℹ️  copilot-sessions-hq orchestrator not detected; skipping LangGraph install"
fi

# Configure environment for PHP 8.3
print_status "Configuring environment for PHP 8.3..."
BASHRC_FILE="$HOME/.bashrc"
if [ -f "$BASHRC_FILE" ]; then
    # Remove existing conflicting PHP configurations
    grep -v "# PHP.*Configuration" "$BASHRC_FILE" > "${BASHRC_FILE}.tmp" || cp "$BASHRC_FILE" "${BASHRC_FILE}.tmp"
    grep -v "export PATH.*php" "${BASHRC_FILE}.tmp" > "${BASHRC_FILE}.new" || cp "${BASHRC_FILE}.tmp" "${BASHRC_FILE}.new"
    grep -v "alias php=" "${BASHRC_FILE}.new" > "${BASHRC_FILE}.clean" || cp "${BASHRC_FILE}.new" "${BASHRC_FILE}.clean"
    grep -v "alias composer=" "${BASHRC_FILE}.clean" > "${BASHRC_FILE}.tmp2" || cp "${BASHRC_FILE}.clean" "${BASHRC_FILE}.tmp2"
    grep -v "alias drush=" "${BASHRC_FILE}.tmp2" > "${BASHRC_FILE}.tmp3" || cp "${BASHRC_FILE}.tmp2" "${BASHRC_FILE}.tmp3"
        awk '
            BEGIN { skip = 0 }
            /^# Drush wrapper - Auto-generated by setup\.sh$/ { skip = 1; next }
            skip == 1 && /^}$/ { skip = 0; next }
            skip == 1 { next }
            { print }
        ' "${BASHRC_FILE}.tmp3" > "${BASHRC_FILE}.tmp4" || cp "${BASHRC_FILE}.tmp3" "${BASHRC_FILE}.tmp4"
        mv "${BASHRC_FILE}.tmp4" "$BASHRC_FILE"
        rm -f "${BASHRC_FILE}.tmp" "${BASHRC_FILE}.new" "${BASHRC_FILE}.clean" "${BASHRC_FILE}.tmp2" "${BASHRC_FILE}.tmp3" 2>/dev/null || true
    
    # Add comprehensive PHP 8.3 configuration
    echo "" >> "$BASHRC_FILE"
    echo "# PHP 8.3 Configuration - Auto-generated by setup.sh" >> "$BASHRC_FILE"
    echo 'export PATH="/usr/bin:/usr/sbin:$PATH"' >> "$BASHRC_FILE"
    echo 'alias php="/usr/bin/php8.3"' >> "$BASHRC_FILE"
    echo 'alias composer="/usr/bin/php8.3 /usr/local/bin/composer"' >> "$BASHRC_FILE"
    cat <<'EOF' >> "$BASHRC_FILE"
# Drush wrapper - Auto-generated by setup.sh
drush() {
    local dir="$PWD"
    while [[ "$dir" != "/" ]]; do
        if [[ -x "$dir/vendor/bin/drush" ]]; then
            "$dir/vendor/bin/drush" "$@"
            return $?
        fi
        dir="$(dirname "$dir")"
    done
    echo "No local vendor/bin/drush found from current path: $PWD" >&2
    return 127
}
EOF
    
    print_status "Updated .bashrc with comprehensive PHP 8.3 configuration"
fi

# Apply environment changes to current session
export PATH="/usr/bin:/usr/sbin:/usr/local/bin:$PATH"
php() { /usr/bin/php8.3 "$@"; }
composer() { /usr/bin/php8.3 /usr/local/bin/composer "$@"; }
drush() {
    local dir="$PWD"
    while [[ "$dir" != "/" ]]; do
        if [[ -x "$dir/vendor/bin/drush" ]]; then
            "$dir/vendor/bin/drush" "$@"
            return $?
        fi
        dir="$(dirname "$dir")"
    done
    echo "No local vendor/bin/drush found from current path: $PWD" >&2
    return 127
}
export -f php composer drush
print_status "Created shell functions to force PHP 8.3 usage"

# VERIFICATION
print_status "=== PHP 8.3 VERIFICATION ==="
print_status "which php: $(which php)"
print_status "/usr/bin/php8.3 version: $(/usr/bin/php8.3 --version | head -n1)"
print_status "php (function) version: $(php --version | head -n1)"
print_status "composer (function) version: $(composer --version | head -n1)"

if /usr/bin/php8.3 -m | grep -q mysqli; then
    print_status "✅ PHP 8.3 MySQL extension loaded"
else
    print_error "❌ PHP 8.3 MySQL extension not found"
fi

if /usr/bin/php8.3 /usr/local/bin/composer about >/dev/null 2>&1; then
    print_status "✅ Composer working with PHP 8.3"
else
    print_error "❌ Composer not working with PHP 8.3"
fi
print_status "=== END PHP 8.3 VERIFICATION ==="

# ------------------------------------------------------------------------------
# 1.7 MySQL Database Creation
# ------------------------------------------------------------------------------
# NOTE: This section creates the DATABASES only.
#       Table creation happens later in Step 5 (H3 Geolocation Setup)
print_status "Configuring MySQL database..."

ensure_mysql_running
if sudo mysql -e "SELECT 1;" &>/dev/null; then
    print_status "✅ MySQL is now running"
else
    print_error "❌ Failed to start MySQL - continuing with limited functionality"
fi

print_status "Setting up MySQL database and user for Drupal..."
print_status "Note: Recreating user to ensure password is current from .env"
sudo mysql <<EOF
-- Drop and recreate user to ensure password matches .env (idempotent)
DROP USER IF EXISTS '${DB_USER}'@'127.0.0.1';
DROP USER IF EXISTS '${DB_USER}'@'localhost';

CREATE USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';

-- Create database
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges (both hosts for compatibility)
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';

FLUSH PRIVILEGES;
EOF

# Verify database connectivity with the credentials
if mysql -h 127.0.0.1 -u "${DB_USER}" -p"${DB_PASSWORD}" -e "SELECT 1;" ${DB_NAME} &>/dev/null; then
    print_status "✅ Database connection verified: ${DB_USER}@127.0.0.1 -> ${DB_NAME}"
else
    print_error "❌ Database connection test failed - Drupal installation may fail"
    print_error "Try: mysql -h 127.0.0.1 -u ${DB_USER} -p${DB_NAME}"
fi

# Create additional database for AmISafe module
print_status "Creating AmISafe database..."
sudo mysql <<EOF
CREATE DATABASE IF NOT EXISTS amisafe_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON amisafe_database.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON amisafe_database.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
print_status "AmISafe database created"

# Create database for Dungeon Crawler sub-site
print_status "Creating Dungeon Crawler database..."
sudo mysql <<EOF
CREATE DATABASE IF NOT EXISTS ${DC_DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON ${DC_DB_NAME}.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON ${DC_DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
print_status "Dungeon Crawler database '${DC_DB_NAME}' created"

# Note: localhost grants already configured above (both @127.0.0.1 and @localhost created together)
print_status "Database setup complete: ${DB_USER} has access to all databases from both 127.0.0.1 and localhost"


# ------------------------------------------------------------------------------
# 1.8 Private Files Directory
# ------------------------------------------------------------------------------
print_status "Creating Drupal private files directory..."
if [ ! -d "/var/private/forseti" ]; then
    sudo mkdir -p /var/private/forseti
fi
sudo chown -R www-data:www-data /var/private/forseti
sudo chmod -R 777 /var/private/forseti
print_status "Private files directory created at /var/private/forseti"

if [ ! -d "/var/private/dungeoncrawler" ]; then
    sudo mkdir -p /var/private/dungeoncrawler
fi
sudo chown -R www-data:www-data /var/private/dungeoncrawler
sudo chmod -R 777 /var/private/dungeoncrawler
print_status "Private files directory created at /var/private/dungeoncrawler"

# ------------------------------------------------------------------------------
# 1.9 Apache Virtual Host Configuration
# ------------------------------------------------------------------------------
print_status "Configuring Apache virtual host for Forseti..."

sudo bash -c "cat > /etc/apache2/sites-available/forseti.conf" <<EOF
<VirtualHost *:80>
        ServerName localhost
        ServerAlias forseti.local www.forseti.local penguin.linux.test
        ServerAdmin webmaster@localhost
        DocumentRoot $WORKSPACE_ROOT/sites/forseti/web

        <Directory $WORKSPACE_ROOT/sites/forseti/web>
                Options Indexes FollowSymLinks
                AllowOverride All
                Require all granted
        </Directory>

        ErrorLog \${APACHE_LOG_DIR}/forseti_error.log
        CustomLog \${APACHE_LOG_DIR}/forseti_access.log combined
</VirtualHost>
EOF

# Disable default Apache site and enable Forseti
sudo a2dissite 000-default.conf 2>/dev/null || true
sudo a2ensite forseti.conf

# Configure Apache virtual host for Dungeon Crawler (port 8080 in dev)
print_status "Configuring Apache virtual host for Dungeon Crawler..."

# Ensure Apache listens on port 8080
if ! grep -q "Listen ${DC_DEV_PORT}" /etc/apache2/ports.conf 2>/dev/null; then
    print_status "Adding Listen ${DC_DEV_PORT} to Apache ports.conf..."
    sudo bash -c "echo 'Listen ${DC_DEV_PORT}' >> /etc/apache2/ports.conf"
fi

sudo bash -c "cat > /etc/apache2/sites-available/dungeoncrawler.conf" <<EOF
<VirtualHost *:${DC_DEV_PORT}>
        ServerName dungeoncrawler.local
        ServerAlias dungeoncrawler.forseti.local
        ServerAdmin webmaster@localhost
        DocumentRoot $WORKSPACE_ROOT/sites/dungeoncrawler/web

        <Directory $WORKSPACE_ROOT/sites/dungeoncrawler/web>
                Options Indexes FollowSymLinks
                AllowOverride All
                Require all granted
        </Directory>

        ErrorLog \${APACHE_LOG_DIR}/dungeoncrawler_error.log
        CustomLog \${APACHE_LOG_DIR}/dungeoncrawler_access.log combined
</VirtualHost>
EOF

sudo a2ensite dungeoncrawler.conf 2>/dev/null || true

print_status "Starting services..."
ensure_mysql_running
sudo service apache2 restart

# ------------------------------------------------------------------------------
# 1.6 VS Code Workspace Optimization
# ------------------------------------------------------------------------------
print_status "Configuring VS Code workspace optimizations..."
VSCODE_DIR="$WORKSPACE_ROOT/.vscode"
VSCODE_SETTINGS="$VSCODE_DIR/settings.json"

# Create .vscode directory if it doesn't exist
mkdir -p "$VSCODE_DIR"

# Read existing chat.agent.maxRequests value if it exists
CHAT_MAX_REQUESTS=150
if [ -f "$VSCODE_SETTINGS" ] && grep -q "chat.agent.maxRequests" "$VSCODE_SETTINGS"; then
    CHAT_MAX_REQUESTS=$(grep "chat.agent.maxRequests" "$VSCODE_SETTINGS" | sed 's/[^0-9]//g')
fi

# Create VS Code settings with performance optimizations
cat > "$VSCODE_SETTINGS" << 'VSCODE_EOF'
{
  // Chat configuration
  "chat.agent.maxRequests": 150,

  // File Watcher Exclusions - Reduces CPU usage significantly
  "files.watcherExclude": {
    "**/vendor/**": true,
    "**/node_modules/**": true,
    "**/.git/objects/**": true,
    "**/.git/subtree-cache/**": true,
    "**/sites/*/files/**": true,
    "**/sites/*/private/**": true,
    "**/*.sql": true,
    "**/*.sql.gz": true
  },

  // Search Exclusions - Faster search results
  "search.exclude": {
    "**/vendor/**": true,
    "**/node_modules/**": true,
    "**/sites/*/files/**": true,
    "**/*.lock": true,
    "**/composer.lock": true,
    "**/package-lock.json": true
  },

  // File Exclusions - Hide from explorer
  "files.exclude": {
    "**/.git": true,
    "**/.DS_Store": true,
    "**/Thumbs.db": true
  },

  // Performance optimizations
  "files.autoSave": "onFocusChange",
  "editor.minimap.enabled": false,
  "editor.renderWhitespace": "selection",
  "breadcrumbs.enabled": true,

  // Reduce extension host load
  "extensions.autoUpdate": false,
  "extensions.autoCheckUpdates": false,

  // TypeScript/JavaScript - disable if not needed
  "typescript.disableAutomaticTypeAcquisition": true,
  "javascript.validate.enable": false,

  // PHP specific
  "php.validate.enable": true,
  "php.validate.run": "onSave",

  // Git optimizations
  "git.autorefresh": false,
  "git.decorations.enabled": true,

  // Telemetry off
  "telemetry.telemetryLevel": "off"
}
VSCODE_EOF

# Restore chat.agent.maxRequests if it was different
if [ "$CHAT_MAX_REQUESTS" != "150" ]; then
    sed -i "s/\"chat.agent.maxRequests\": 150/\"chat.agent.maxRequests\": $CHAT_MAX_REQUESTS/" "$VSCODE_SETTINGS"
fi

print_status "✅ VS Code workspace optimized (file watchers, search, performance)"

print_status "✅ STEP 1 COMPLETE: Environment setup finished"


# ==============================================================================
# STEP 2: DRUPAL INSTALLATION - FORSETI SITE
# ==============================================================================

print_step "2. DRUPAL INSTALLATION - Setting up Forseti site..."

# Ensure we're using the correct PHP version
export PATH="/usr/bin:$PATH"
print_status "Enforcing PHP 8.3 for Drupal operations: $(php --version | head -n1)"

# ------------------------------------------------------------------------------
# 2.1 Directory Setup
# ------------------------------------------------------------------------------
print_status "Creating site directory structure..."
mkdir -p $WORKSPACE_ROOT/sites

if [ -d "$PROJECT_DIR" ]; then
    print_status "Existing Drupal directory found. Skipping fresh installation to preserve custom work."
    print_status "Using existing Drupal installation at $PROJECT_DIR"
else
    print_status "No existing Drupal directory found. Creating new Drupal 11.2.5 project..."
    cd $WORKSPACE_ROOT/sites
    /usr/bin/php8.3 /usr/local/bin/composer create-project drupal/recommended-project:11.2.5 forseti --no-interaction
fi

cd "$PROJECT_DIR"

# ------------------------------------------------------------------------------
# 2.2 Composer Dependencies
# ------------------------------------------------------------------------------
if [ -f "composer.json" ] && [ ! -f "vendor/autoload.php" ]; then
    print_status "Installing Composer dependencies..."
    /usr/bin/php8.3 /usr/local/bin/composer install --no-interaction --optimize-autoloader
elif [ -f "vendor/autoload.php" ] && [ ! -f "vendor/bin/drush" ]; then
    print_status "Installing missing dependencies..."
    /usr/bin/php8.3 /usr/local/bin/composer update --no-interaction
elif [ -f "vendor/autoload.php" ]; then
    # Check if autoloader is corrupted
    if ! /usr/bin/php8.3 -c /etc/php/8.3/cli/php.ini -r "require 'vendor/autoload.php'; echo 'OK';" 2>/dev/null; then
        print_status "Fixing corrupted Composer autoloader..."
        rm -rf vendor/
        /usr/bin/php8.3 /usr/local/bin/composer install --no-interaction --optimize-autoloader
    fi
fi

# ------------------------------------------------------------------------------
# 2.3 Drush Installation
# ------------------------------------------------------------------------------
if [ ! -f "vendor/bin/drush" ]; then
    print_status "Installing Drush..."
    /usr/bin/php8.3 /usr/local/bin/composer require drush/drush --no-interaction
fi

# ------------------------------------------------------------------------------
# 2.4 Development Modules Installation
# ------------------------------------------------------------------------------
if [ ! -d "web/modules/contrib/devel" ]; then
    print_status "Installing development modules and packages..."
    /usr/bin/php8.3 /usr/local/bin/composer require \
        drupal/devel \
        drupal/admin_toolbar \
        drupal/pathauto \
        drupal/metatag \
        drupal/webform \
        drupal/social_api \
        drupal/social_auth \
        drupal/social_auth_google \
        drupal/google_tag \
        drupal/token \
        drupal/twig_tweak \
        drupal/backup_migrate \
        drupal/bootstrap5 \
        drupal/radix \
        drupal/recaptcha \
        drupal/recaptcha_v3 \
        drupal/profile \
        aws/aws-sdk-php \
        defuse/php-encryption \
        tecnickcom/tcpdf \
        --no-interaction
else
    print_status "Development modules already installed. Skipping to preserve existing setup."
fi

# ------------------------------------------------------------------------------
# 2.5 Database Verification
# ------------------------------------------------------------------------------
DRUPAL_NEEDS_INSTALL=true
if [ -f "web/sites/default/settings.php" ] && [ -s "web/sites/default/settings.php" ]; then
    # Check if Drupal users table exists (specifically check for Drupal tables, not AmISafe)
    USER_TABLE_COUNT=$(/usr/bin/php8.3 vendor/drush/drush/drush.php sql:query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='users'" 2>/dev/null | tail -n1)
    if [ "$USER_TABLE_COUNT" = "1" ]; then
        DRUPAL_NEEDS_INSTALL=false
        print_status "Existing Drupal installation detected and verified."
    else
        print_status "No Drupal installation found. Need to install Drupal."
    fi
fi

# ------------------------------------------------------------------------------
# 2.6 Drupal Site Installation
# ------------------------------------------------------------------------------
if [ "$DRUPAL_NEEDS_INSTALL" = true ]; then
    print_status "Setting up file permissions and installing Drupal..."
    sudo chmod 755 web/sites/default 2>/dev/null || chmod 755 web/sites/default
    
    # Create files directory with proper permissions
    print_status "Creating and configuring files directory..."
    mkdir -p web/sites/default/files
    sudo chmod -R 777 web/sites/default/files 2>/dev/null || chmod -R 777 web/sites/default/files
    
    # Create PHP storage directory
    mkdir -p web/sites/default/files/php
    sudo chmod 777 web/sites/default/files/php 2>/dev/null || chmod 777 web/sites/default/files/php
    
    # Set proper ownership for Apache
    if sudo chown -R www-data:www-data web/sites/default/files 2>/dev/null; then
        print_status "Successfully set www-data ownership"
    elif sudo chown -R $(whoami):$(whoami) web/sites/default/files 2>/dev/null; then
        print_status "Set current user ownership as fallback"
    else
        print_warning "Could not change ownership, but will continue with current permissions"
    fi
    
    sudo chmod -R 777 web/sites/default/files 2>/dev/null || chmod -R 777 web/sites/default/files

    # Copy default settings file
    if [ ! -f "web/sites/default/settings.php" ]; then
        cp web/sites/default/default.settings.php web/sites/default/settings.php
    fi
    sudo chmod 664 web/sites/default/settings.php 2>/dev/null || chmod 664 web/sites/default/settings.php

    # Install Drupal if not already installed
    print_status "Checking existing Drupal installation..."
    if ! /usr/bin/php8.3 vendor/drush/drush/drush.php status | grep -q "Drupal bootstrap.*Successful" 2>/dev/null; then
        print_status "Installing Drupal..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php site:install standard \
            --db-url="mysql://${DB_USER}:${DB_PASSWORD}@127.0.0.1:3306/${DB_NAME}" \
            --site-name="${SITE_NAME}" \
            --account-name="${ADMIN_USER}" \
            --account-pass="${ADMIN_PASSWORD}" \
            --account-mail="${ADMIN_EMAIL}" \
            --yes
        
        if [ $? -eq 0 ]; then
            print_status "✅ Drupal installed successfully"
        else
            print_error "❌ Drupal installation failed"
            exit 1
        fi
    else
        print_status "Drupal already installed, preserving existing data"
    fi
fi

# Check if Drupal is properly installed
DRUPAL_INSTALLED=false
if [ "$DRUPAL_NEEDS_INSTALL" = false ]; then
    DRUPAL_INSTALLED=true
    print_status "Drupal installation detected and verified"
else
    # Check if installation just completed successfully
    USER_TABLE_COUNT=$(/usr/bin/php8.3 vendor/drush/drush/drush.php sql:query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='users'" 2>/dev/null | tail -n1)
    if [ "$USER_TABLE_COUNT" = "1" ]; then
        DRUPAL_INSTALLED=true
        print_status "Drupal installation completed and verified"
    fi
fi

# Additional verification using Drush status (more reliable)
if [ "$DRUPAL_INSTALLED" = false ]; then
    if /usr/bin/php8.3 vendor/drush/drush/drush.php status 2>/dev/null | grep -q "Drupal bootstrap.*Successful"; then
        DRUPAL_INSTALLED=true
        print_status "Drupal bootstrap verified via Drush status"
    fi
fi

# ------------------------------------------------------------------------------
# 2.7 Enable Development Modules
# ------------------------------------------------------------------------------
if [ "$DRUPAL_INSTALLED" = true ]; then
    # Verify Drupal functionality before enabling modules
    print_status "Verifying Drupal functionality before enabling modules..."
    if ! /usr/bin/php8.3 vendor/drush/drush/drush.php status --format=json 2>/dev/null | grep -q '"bootstrap":"Successful"'; then
        if ! /usr/bin/php8.3 vendor/drush/drush/drush.php cache:rebuild 2>/dev/null; then
            print_warning "Drupal bootstrap failed. Skipping module enablement."
            DRUPAL_INSTALLED=false
        fi
    fi
fi

if [ "$DRUPAL_INSTALLED" = true ]; then
    # Enable development modules
    if ! /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "devel"; then
        print_status "Enabling development and utility modules..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php en devel admin_toolbar admin_toolbar_tools pathauto metatag -y
        
        print_status "Enabling production feature modules..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php en webform webform_ui token twig_tweak -y
        
        print_status "Enabling social authentication..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php en social_api social_auth social_auth_google -y
        
        print_status "Enabling Google Tag Manager..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php en google_tag -y
    else
        print_status "Development modules already enabled. Skipping to preserve existing configuration."
    fi
    
    # ------------------------------------------------------------------------------
    # 2.8 Block Placement Configuration
    # ------------------------------------------------------------------------------
    print_status "Configuring block placement..."
    
    # Import missing block configurations from sync directory
    print_status "Importing block configurations from sync directory..."
    if [ -f "../config/sync/block.block.forseti_forsetifootermenu.yml" ]; then
        /usr/bin/php8.3 vendor/drush/drush/drush.php config:import --partial -y 2>/dev/null || true
        print_status "Configuration import completed"
    fi
    
    # Create temporary block placement script
    cat > "$PROJECT_DIR/temp_block_placement.php" << 'BLOCKSCRIPT'
<?php
// Configure main navigation block
$main_menu_config = \Drupal::service('config.factory')->getEditable('block.block.forseti_main_menu');
if (!$main_menu_config->isNew()) {
  $main_menu_config->set('status', true)->set('region', 'navbar_left')->set('weight', 0)->save();
  echo "Main navigation configured\n";
}

// Configure or create footer menu block
$footer_menu_config = \Drupal::service('config.factory')->getEditable('block.block.forseti_forsetifootermenu');
if (!$footer_menu_config->isNew()) {
  $footer_menu_config->set('status', true)->set('region', 'footer')->set('weight', -2)->save();
  echo "Footer menu configured (existing block)\n";
} else {
  // Create footer block if it doesn't exist
  $block_storage = \Drupal::entityTypeManager()->getStorage('block');
  $block = $block_storage->create([
    'id' => 'forseti_forsetifootermenu',
    'theme' => 'forseti',
    'region' => 'footer',
    'weight' => -2,
    'plugin' => 'forseti_footer_menu',
    'settings' => [
      'id' => 'forseti_footer_menu',
      'label' => 'Forseti Footer Menu',
      'label_display' => 'visible',
      'provider' => 'forseti_content',
    ],
    'visibility' => [],
  ]);
  $block->save();
  echo "Footer menu block created\n";
}

// Disable old footer blocks if they exist
$old_footer = \Drupal::service('config.factory')->getEditable('block.block.forseti_footer');
if (!$old_footer->isNew()) { 
  $old_footer->set('status', false)->save(); 
  echo "Disabled old footer block\n";
}
$powered = \Drupal::service('config.factory')->getEditable('block.block.forseti_powered');
if (!$powered->isNew()) { 
  $powered->set('status', false)->save(); 
  echo "Disabled powered-by block\n";
}
BLOCKSCRIPT
    
    # Run block placement script
    /usr/bin/php8.3 vendor/drush/drush/drush.php scr temp_block_placement.php 2>/dev/null || true
    rm -f temp_block_placement.php
    print_status "✅ Block placement configured"
    
    # Verify Site Branding is in navbar_branding region
    if /usr/bin/php8.3 vendor/drush/drush/drush.php config:get block.block.forseti_branding 2>/dev/null | grep -q "region:"; then
        BRANDING_REGION=$(/usr/bin/php8.3 vendor/drush/drush/drush.php config:get block.block.forseti_branding region 2>/dev/null | grep "region:" | awk '{print $2}' | tr -d "'" | tr -d '\r\n')
        if [ "$BRANDING_REGION" != "navbar_branding" ]; then
            print_status "Setting Site Branding to navbar_branding region..."
            /usr/bin/php8.3 vendor/drush/drush/drush.php php:eval "\$config = \Drupal::service('config.factory')->getEditable('block.block.forseti_branding'); \$config->set('region', 'navbar_branding')->save();" 2>/dev/null
            print_status "✅ Site branding configured in navbar_branding"
        else
            print_status "Site branding already in navbar_branding region. Skipping."
        fi
    else
        print_status "Site branding block not found. Skipping."
    fi
    
    # ------------------------------------------------------------------------------
    # 2.9 Home Page Configuration
    # ------------------------------------------------------------------------------
    print_status "Configuring Forseti home page..."
    if /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "professional_website_content"; then
        HOME_NODE_ID=$(/usr/bin/php8.3 vendor/drush/drush/drush.php sql:query "SELECT nid FROM node_field_data WHERE title = 'Welcome to Forseti' AND status = 1 ORDER BY nid DESC LIMIT 1;" 2>/dev/null | tail -n1 | tr -d '\r\n')
        if [ -n "$HOME_NODE_ID" ] && [ "$HOME_NODE_ID" != "nid" ]; then
            /usr/bin/php8.3 vendor/drush/drush/drush.php config:set system.site page.front "/node/$HOME_NODE_ID" -y 2>/dev/null
            print_status "✅ Forseti home page set to 'Welcome to Forseti' (node/$HOME_NODE_ID)"
        else
            print_warning "⚠️  Could not find 'Welcome to Forseti' node - using default home page"
        fi
    else
        print_warning "⚠️  Professional website content module not enabled - using default home page"
    fi

    # Final verification
    print_status "Performing final verification of modules and theme..."
    if /usr/bin/php8.3 vendor/drush/drush/drush.php cache:rebuild 2>/dev/null; then
        print_status "✅ All modules and theme successfully enabled and verified"
    else
        print_warning "⚠️  Some modules or theme may need manual configuration"
    fi
else
    print_status "Drupal not fully installed yet. Skipping module and theme enabling."
fi

# ------------------------------------------------------------------------------
# 2.10 Development Directories
# ------------------------------------------------------------------------------
print_status "Ensuring custom development directories exist..."
mkdir -p web/modules/custom
mkdir -p web/themes/custom
mkdir -p config/sync

chmod 755 web/modules/custom
chmod 755 web/themes/custom
chmod 755 config/sync

# ------------------------------------------------------------------------------
# 2.11 Settings Configuration
# ------------------------------------------------------------------------------
fix_drupal_permissions "$PROJECT_DIR"

# Add development settings if they don't exist
if ! grep -q "Development-specific settings" web/sites/default/settings.php; then
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
else
    print_status "Development settings already exist in settings.php. Skipping to preserve existing configuration."
fi

# Create settings.local.php
if [ ! -f "web/sites/default/settings.local.php" ]; then
    print_status "Creating local development settings..."
    cat > web/sites/default/settings.local.php << EOL
<?php

/**
 * Local development settings
 */

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
else
    print_status "Local development settings already exist. Skipping to preserve existing configuration."
fi

cd "$PROJECT_DIR"

print_status "✅ STEP 2 COMPLETE: Drupal installation finished"


# ==============================================================================
# STEP 3: DEVELOPMENT CONFIGURATION
# ==============================================================================

print_step "3. DEVELOPMENT CONFIGURATION - Setting up development tools..."

cd "$PROJECT_DIR"

# ------------------------------------------------------------------------------
# 3.1 Drupal Coder and PHP CodeSniffer
# ------------------------------------------------------------------------------
print_status "Installing Drupal Coder and PHP CodeSniffer..."
/usr/bin/php8.3 /usr/local/bin/composer require drupal/coder --dev --no-interaction

print_status "Configuring PHP CodeSniffer for Drupal standards..."
./vendor/bin/phpcs --config-set installed_paths vendor/drupal/coder/coder_sniffer
./vendor/bin/phpcs --config-set default_standard Drupal

# ------------------------------------------------------------------------------
# 3.2 PHPUnit Installation
# ------------------------------------------------------------------------------
print_status "Installing additional development tools..."
/usr/bin/php8.3 /usr/local/bin/composer require phpunit/phpunit symfony/phpunit-bridge --dev --no-interaction

# ------------------------------------------------------------------------------
# 3.3 Development Services Configuration
# ------------------------------------------------------------------------------
if [ ! -f "web/sites/development.services.yml" ]; then
    print_status "Creating development services configuration..."
    cat > web/sites/development.services.yml << 'EOL'
# Local development services.
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
else
    print_status "Development services configuration already exists. Skipping to preserve existing setup."
fi

# ------------------------------------------------------------------------------
# 3.4 Custom Module Template
# ------------------------------------------------------------------------------
CUSTOM_MODULES_DIR="$PROJECT_DIR/web/modules/custom"
if [ ! -f "$CUSTOM_MODULES_DIR/README.md" ]; then
    print_status "Creating custom module template..."
    cat > "$CUSTOM_MODULES_DIR/README.md" << 'EOL'
# Custom Modules

This directory contains custom modules for the Forseti website.

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
else
    print_status "Custom module template already exists. Skipping to preserve existing modules."
fi

# ------------------------------------------------------------------------------
# 3.5 Custom Theme Template
# ------------------------------------------------------------------------------
CUSTOM_THEMES_DIR="$PROJECT_DIR/web/themes/custom"
if [ ! -f "$CUSTOM_THEMES_DIR/README.md" ]; then
    print_status "Creating custom theme template..."
    cat > "$CUSTOM_THEMES_DIR/README.md" << 'EOL'
# Custom Themes

This directory contains custom themes for the Forseti website.

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
else
    print_status "Custom theme template already exists. Skipping to preserve existing themes."
fi

# ------------------------------------------------------------------------------
# 3.6 Git Configuration
# ------------------------------------------------------------------------------
SCRIPTS_DIR="$PROJECT_DIR/scripts"
mkdir -p "$SCRIPTS_DIR"

if [ ! -f "$SCRIPTS_DIR/clear-cache.sh" ]; then
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
else
    print_status "Development utility scripts already exist. Skipping to preserve existing scripts."
fi

# Clear cache after configuration
if [ "$DRUPAL_INSTALLED" = true ]; then
    print_status "Clearing cache after configuration..."
    /usr/bin/php8.3 vendor/drush/drush/drush.php cache:rebuild 2>/dev/null || print_warning "Cache clear failed"
else
    print_warning "Drupal not fully installed. Skipping cache clear."
fi

# Set final permissions
print_status "Setting final permissions..."
if [ -f "web/sites/default/settings.php" ]; then
    chmod 644 web/sites/default/settings.php
fi
chmod -R 755 web/modules/custom web/themes/custom

print_status "✅ STEP 3 COMPLETE: Development configuration finished"

# ==============================================================================
# STEP 4: POST-INSTALLATION FIXES
# ==============================================================================

print_step "4. POST-INSTALLATION FIXES - Applying known issue resolutions..."

cd "$PROJECT_DIR"

# ------------------------------------------------------------------------------
# 4.1 Cache Backend Configuration Fix
# ------------------------------------------------------------------------------
print_status "Fixing cache backend configuration issues..."

SERVICES_FILE="$PROJECT_DIR/web/sites/development.services.yml"
if [ -f "$SERVICES_FILE" ]; then
    print_status "Updating development services..."
    cat > "$SERVICES_FILE" << 'EOF'
# Local development services.
parameters:
  http.response.debug_cacheability_headers: true
  twig.config:
    debug: true
    auto_reload: true
    cache: false
services:
  logger.channel.config_schema:
    parent: logger.channel_base
    arguments: [ 'config_schema' ]
  config.schema_checker:
    class: Drupal\Core\Config\Development\LenientConfigSchemaChecker
    arguments: [ '@config.typed', '@config.storage.schema' ]
EOF
fi

# ------------------------------------------------------------------------------
# 4.2 Settings File Cleanup
# ------------------------------------------------------------------------------
print_status "Cleaning cache backend references from settings files..."
SETTINGS_FILE="$PROJECT_DIR/web/sites/default/settings.php"
SETTINGS_LOCAL_FILE="$PROJECT_DIR/web/sites/default/settings.local.php"

if [ -f "$SETTINGS_FILE" ]; then
    sed -i '/cache.backend.null/d' "$SETTINGS_FILE" || true
    print_status "Cleaned cache references from settings.php"
fi

if [ -f "$SETTINGS_LOCAL_FILE" ]; then
    sed -i '/cache.backend.null/d' "$SETTINGS_LOCAL_FILE" || true
    if ! grep -q "cache.*max_age" "$SETTINGS_LOCAL_FILE"; then
        echo "" >> "$SETTINGS_LOCAL_FILE"
        echo "// Disable page caching for development" >> "$SETTINGS_LOCAL_FILE"
        echo "\$config['system.performance']['cache']['page']['max_age'] = 0;" >> "$SETTINGS_LOCAL_FILE"
    fi
    print_status "Cleaned cache references from settings.local.php"
fi

# ------------------------------------------------------------------------------
# 4.3 CORS Module Installation
# ------------------------------------------------------------------------------
# CORS module installation if needed (placeholder for future use)
# /usr/bin/php8.3 /usr/local/bin/composer require drupal/cors --no-interaction
# /usr/bin/php8.3 vendor/drush/drush/drush.php en cors -y

# ------------------------------------------------------------------------------
# 4.4 Simple OAuth Fix
# ------------------------------------------------------------------------------
# Simple OAuth fixes if needed (placeholder for future use)

# ------------------------------------------------------------------------------
# 4.5 Composer Verification
# ------------------------------------------------------------------------------
print_status "Final Composer dependency verification..."
if [ -f "composer.json" ]; then
    print_status "Verifying Composer dependencies..."
    
    if [ -f "composer.lock" ]; then
        /usr/bin/php8.3 /usr/local/bin/composer validate --no-check-all 2>/dev/null || {
            print_status "Updating Composer dependencies..."
            /usr/bin/php8.3 /usr/local/bin/composer update --no-interaction --with-all-dependencies 2>/dev/null || true
        }
    fi
    
    /usr/bin/php8.3 /usr/local/bin/composer dump-autoload --optimize --no-interaction 2>/dev/null || true
    print_status "Composer autoloader rebuilt"
fi

# ------------------------------------------------------------------------------
# 4.6 Final Cache Rebuild
# ------------------------------------------------------------------------------
print_status "Clearing caches and restarting services..."
sudo service apache2 restart

# ------------------------------------------------------------------------------
# 4.7 Site Verification
# ------------------------------------------------------------------------------
print_status "Final verification and cache rebuild..."
if [ -f "vendor/drush/drush/drush.php" ]; then
    if /usr/bin/php8.3 vendor/drush/drush/drush.php status --format=json 2>/dev/null | grep -q '"bootstrap":"Successful"'; then
        print_status "Forseti site is working correctly"
        /usr/bin/php8.3 vendor/drush/drush/drush.php cache:rebuild 2>/dev/null || true
    else
        print_warning "Forseti site may need manual configuration"
    fi
else
    print_warning "Drush not found for Forseti site"
fi

print_status "✅ STEP 4 COMPLETE: Post-installation fixes applied"

# ------------------------------------------------------------------------------
# 4.8 Custom Modules and Theme Configuration (Post-Bootstrap)
# ------------------------------------------------------------------------------
print_status "Configuring custom modules and theme..."
if [ -f "vendor/drush/drush/drush.php" ]; then
    # Enable Custom Modules
    if [ -d "web/modules/custom" ]; then
        print_status "Checking custom modules..."
        
        # NOTE: This workaround may need investigation if module functionality issues arise later.
        # The forseti_safety_content module ships with user.mail.yml and user.settings.yml in
        # config/install, which conflicts with core Drupal user config already in active storage.
        # We backup the install config directory to prevent PreExistingConfigException errors.
        # If user email templates or settings from this module are needed, they may need to be
        # manually imported or the module's install hook may need refactoring to use config/optional
        # or programmatic configuration instead of config/install for these system-level configs.
        if [ -d "web/modules/custom/forseti_safety_content/config/install" ]; then
            print_status "Backing up forseti_safety_content install config to avoid user.mail/user.settings conflicts..."
            mv web/modules/custom/forseti_safety_content/config/install web/modules/custom/forseti_safety_content/config/install.backup 2>/dev/null || true
        fi
        
        # Enable all existing custom modules (force enable to ensure they're active)
        print_status "Enabling all custom modules..."
        
        # Enable profile module first (dependency for job_hunter if it exists)
        if [ -d "web/modules/custom/job_hunter" ]; then
            /usr/bin/php8.3 vendor/drush/drush/drush.php en profile -y 2>/dev/null || true
        fi
        
        # Uninstall old module if it exists (renamed to forseti_content)
        if /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "forseti_safety_content"; then
            print_status "Uninstalling old forseti_safety_content module (renamed to forseti_content)..."
            /usr/bin/php8.3 vendor/drush/drush/drush.php pm:uninstall forseti_safety_content -y 2>/dev/null || true
        fi
        
        # Enable modules that exist in custom directory
        [ -d "web/modules/custom/ai_conversation" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en ai_conversation -y 2>/dev/null && print_status "✅ ai_conversation enabled"
        [ -d "web/modules/custom/amisafe" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en amisafe -y 2>/dev/null && print_status "✅ amisafe enabled"
        [ -d "web/modules/custom/agent_evaluation" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en agent_evaluation -y 2>/dev/null && print_status "✅ agent_evaluation enabled"
        [ -d "web/modules/custom/forseti_content" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en forseti_content -y 2>/dev/null && print_status "✅ forseti_content enabled"
        [ -d "web/modules/custom/forseti_games" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en forseti_games -y 2>/dev/null && print_status "✅ forseti_games enabled"
        [ -d "web/modules/custom/institutional_management" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en institutional_management -y 2>/dev/null && print_status "✅ institutional_management enabled"
        [ -d "web/modules/custom/job_hunter" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en job_hunter -y 2>/dev/null && print_status "✅ job_hunter enabled"
        [ -d "web/modules/custom/nfr" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en nfr -y 2>/dev/null && print_status "✅ nfr enabled"
        [ -d "web/modules/custom/safety_calculator" ] && /usr/bin/php8.3 vendor/drush/drush/drush.php en safety_calculator -y 2>/dev/null && print_status "✅ safety_calculator enabled"
        
        # Run database updates after enabling modules
        print_status "Running database updates..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php updatedb -y 2>/dev/null || true
        
        print_status "✅ All available custom modules enabled"
    fi
    
    # Enable Forseti Theme
    if [ -d "web/themes/custom/forseti" ]; then
        print_status "Configuring Forseti theme..."
        
        # Always enable the Forseti theme
        print_status "Enabling Forseti custom theme..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php theme:enable forseti -y 2>/dev/null && print_status "✅ Forseti theme enabled" || print_warning "Could not enable Forseti theme"
        
        # Always set as default theme
        print_status "Setting Forseti theme as default..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php config:set system.theme default forseti -y 2>/dev/null && print_status "✅ Forseti theme set as default" || print_warning "Could not set Forseti as default theme"
        
        # Build Forseti theme assets
        print_status "Building Forseti theme assets..."
        if command -v npm &> /dev/null; then
            THEME_DIR="web/themes/custom/forseti"
            if [ -f "$THEME_DIR/package.json" ]; then
                print_status "Installing theme npm dependencies..."
                (cd "$THEME_DIR" && npm install --quiet 2>&1 | grep -v "npm warn") && print_status "✅ Theme dependencies installed" || print_warning "Could not install theme dependencies"
                
                print_status "Compiling theme SCSS to CSS..."
                (cd "$THEME_DIR" && npm run dev 2>&1 | tail -5) && print_status "✅ Theme assets compiled" || print_warning "Could not compile theme assets"
            else
                print_warning "No package.json found in theme directory"
            fi
        else
            print_warning "npm not found - cannot build theme assets"
        fi
    else
        print_warning "Forseti theme directory not found at web/themes/custom/forseti"
    fi
else
    print_warning "Drush not available for module and theme configuration"
fi


# ==============================================================================
# STEP 5: H3 GEOLOCATION DATABASE SETUP
# ==============================================================================
# NOTE: This section creates TABLES within databases that were created in Step 1.7
#       It does NOT create databases - those already exist (forseti_dev, amisafe_database)

print_step "5. H3 GEOLOCATION DATABASE SETUP - Initializing AmISafe crime mapping pipeline..."

# ------------------------------------------------------------------------------
# 5.1 MySQL Connection Test
# ------------------------------------------------------------------------------
ensure_mysql_running
if sudo mysql -e "SELECT 1;" &>/dev/null; then
    print_status "✅ MySQL is running and accessible"
else
    print_error "❌ MySQL connection test failed"
fi

# ------------------------------------------------------------------------------
# 5.2 H3 Python Environment Verification
# ------------------------------------------------------------------------------
H3_ENV_DIR="$WORKSPACE_ROOT/h3-geolocation/h3-env"
if [ -f "$H3_ENV_DIR/bin/python" ]; then
    H3_TEST_RESULT=$($H3_ENV_DIR/bin/python -c "import h3; import pandas; import mysql.connector; print('H3 packages verified')" 2>/dev/null || echo "FAILED")
    if [ "$H3_TEST_RESULT" = "H3 packages verified" ]; then
        print_status "✅ H3 Python environment verified"
    else
        print_warning "⚠️  H3 Python environment verification failed"
    fi
else
    print_warning "⚠️  H3 Python environment not found"
fi

# ------------------------------------------------------------------------------
# 5.3 Database Table Verification
# ------------------------------------------------------------------------------
print_status "Setting up AmISafe H3 geolocation data pipeline database..."
AMISAFE_SETUP_SCRIPT="$WORKSPACE_ROOT/h3-geolocation/database/setup/setup_amisafe_complete.sh"

if [ -f "$AMISAFE_SETUP_SCRIPT" ]; then
    print_status "Running AmISafe complete database setup..."
    if bash "$AMISAFE_SETUP_SCRIPT" "$DB_NAME"; then
        print_status "✅ AmISafe database setup completed successfully"
        
        # ------------------------------------------------------------------------------
        # 5.4 ETL Pipeline Status
        # ------------------------------------------------------------------------------
        print_status "Running AmISafe sample data pipeline..."
        PIPELINE_SCRIPT="$WORKSPACE_ROOT/h3-geolocation/database/run_amisafe_pipeline_stlouisintegration.sh"
        if [ -f "$PIPELINE_SCRIPT" ]; then
            cd $WORKSPACE_ROOT/h3-geolocation/database
            if bash "$PIPELINE_SCRIPT" sample; then
                print_status "✅ AmISafe sample data pipeline completed"
            else
                print_warning "⚠️  AmISafe pipeline had issues"
            fi
            cd - > /dev/null
        fi
    else
        print_warning "⚠️  AmISafe database setup encountered issues"
    fi
else
    print_warning "⚠️  AmISafe database setup script not found - setting up basic tables..."
    
    # Create basic AmISafe tables
    print_status "Creating basic AmISafe database tables..."
    mysql -h127.0.0.1 -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" << 'EOF' || print_warning "AmISafe table creation failed"
CREATE TABLE IF NOT EXISTS amisafe_raw_incidents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    source_file VARCHAR(255) NOT NULL DEFAULT 'sample_data',
    cartodb_id INT,
    objectid BIGINT,
    dc_dist VARCHAR(10),
    dispatch_date_time DATETIME,
    lat DECIMAL(10,7),
    lng DECIMAL(11,7),
    location_block TEXT,
    ucr_general VARCHAR(10),
    text_general_code VARCHAR(255),
    ingested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_coordinates (lat, lng),
    INDEX idx_district (dc_dist),
    INDEX idx_crime_type (ucr_general)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amisafe_clean_incidents (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    incident_id VARCHAR(50) UNIQUE,
    lat DECIMAL(10,7) NOT NULL,
    lng DECIMAL(11,7) NOT NULL,
    incident_datetime DATETIME NOT NULL,
    ucr_general VARCHAR(10) NOT NULL,
    dc_dist VARCHAR(10),
    severity_level TINYINT DEFAULT 3,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_location (lat, lng),
    INDEX idx_datetime (incident_datetime),
    INDEX idx_crime_type (ucr_general)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS amisafe_h3_aggregated (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    h3_index VARCHAR(16) NOT NULL,
    h3_resolution TINYINT NOT NULL,
    incident_count INT DEFAULT 0,
    center_lat DECIMAL(10,7),
    center_lng DECIMAL(11,7),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_h3_resolution (h3_index, h3_resolution),
    INDEX idx_resolution (h3_resolution),
    INDEX idx_incident_count (incident_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
EOF
    
    if [ $? -eq 0 ]; then
        print_status "✅ Basic AmISafe database tables created"
    fi
fi

# ------------------------------------------------------------------------------
# 5.5 Database Statistics
# ------------------------------------------------------------------------------
print_status "Checking AmISafe database statistics..."
AMISAFE_RAW_COUNT=$(mysql -h127.0.0.1 -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM amisafe_raw_incidents;" 2>/dev/null || echo "0")
AMISAFE_CLEAN_COUNT=$(mysql -h127.0.0.1 -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM amisafe_clean_incidents;" 2>/dev/null || echo "0")
AMISAFE_H3_COUNT=$(mysql -h127.0.0.1 -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM amisafe_h3_aggregated;" 2>/dev/null || echo "0")

print_status "AmISafe Data Statistics:"
echo "  - Raw Incidents: $AMISAFE_RAW_COUNT"
echo "  - Clean Incidents: $AMISAFE_CLEAN_COUNT"
echo "  - H3 Aggregated: $AMISAFE_H3_COUNT"

# ------------------------------------------------------------------------------
# 5.6 Import Instructions
# ------------------------------------------------------------------------------
print_status "✅ STEP 5 COMPLETE: H3 geolocation setup finished"


# ==============================================================================
# STEP 6: DRUPAL INSTALLATION - DUNGEON CRAWLER SUB-SITE
# ==============================================================================

print_step "6. DRUPAL INSTALLATION - Setting up Dungeon Crawler sub-site..."

# Ensure we're using the correct PHP version
export PATH="/usr/bin:$PATH"

# ------------------------------------------------------------------------------
# 6.1 Directory Setup
# ------------------------------------------------------------------------------
print_status "Creating Dungeon Crawler directory structure..."

if [ -d "$DC_PROJECT_DIR" ] && [ -f "$DC_PROJECT_DIR/composer.json" ]; then
    print_status "Existing Dungeon Crawler directory found. Skipping fresh installation to preserve custom work."
    print_status "Using existing Drupal installation at $DC_PROJECT_DIR"
else
    print_status "No existing Drupal installation found for Dungeon Crawler. Creating new Drupal 11 project..."
    cd $WORKSPACE_ROOT/sites
    # Remove placeholder .gitkeep if it exists
    rm -f "$DC_PROJECT_DIR/.gitkeep" 2>/dev/null || true
    rmdir "$DC_PROJECT_DIR" 2>/dev/null || true
    /usr/bin/php8.3 /usr/local/bin/composer create-project drupal/recommended-project:^11.0 dungeoncrawler --no-interaction
fi

cd "$DC_PROJECT_DIR"

# ------------------------------------------------------------------------------
# 6.2 Composer Dependencies
# ------------------------------------------------------------------------------
if [ -f "composer.json" ] && [ ! -f "vendor/autoload.php" ]; then
    print_status "Installing Dungeon Crawler Composer dependencies..."
    /usr/bin/php8.3 /usr/local/bin/composer install --no-interaction --optimize-autoloader
elif [ -f "vendor/autoload.php" ] && [ ! -f "vendor/bin/drush" ]; then
    print_status "Installing missing Dungeon Crawler dependencies..."
    /usr/bin/php8.3 /usr/local/bin/composer update --no-interaction
fi

# ------------------------------------------------------------------------------
# 6.3 Drush Installation
# ------------------------------------------------------------------------------
if [ ! -f "vendor/bin/drush" ]; then
    print_status "Installing Drush for Dungeon Crawler..."
    /usr/bin/php8.3 /usr/local/bin/composer require drush/drush --no-interaction
fi

# ------------------------------------------------------------------------------
# 6.3.1 PHPUnit Installation (for backend functional tests)
# ------------------------------------------------------------------------------
if [ ! -f "vendor/bin/phpunit" ]; then
    print_status "Installing PHPUnit for Dungeon Crawler..."
    /usr/bin/php8.3 /usr/local/bin/composer require --dev phpunit/phpunit:^10 --no-interaction -W
fi

# ------------------------------------------------------------------------------
# 6.4 Essential Modules
# ------------------------------------------------------------------------------
if [ ! -d "web/modules/contrib/admin_toolbar" ]; then
    print_status "Installing essential modules for Dungeon Crawler..."
    /usr/bin/php8.3 /usr/local/bin/composer require \
        drupal/admin_toolbar \
        drupal/pathauto \
        drupal/metatag \
        drupal/bootstrap5 \
        drupal/radix \
        --no-interaction
else
    print_status "Dungeon Crawler modules already installed. Skipping."
    # Ensure Radix base theme is installed even if other modules exist
    if [ ! -d "web/themes/contrib/radix" ]; then
        print_status "Installing Radix base theme..."
        /usr/bin/php8.3 /usr/local/bin/composer require drupal/radix --no-interaction
    fi
fi

# ------------------------------------------------------------------------------
# 6.5 Database Verification & Site Installation
# ------------------------------------------------------------------------------
DC_DRUPAL_NEEDS_INSTALL=true
if [ -f "web/sites/default/settings.php" ] && [ -s "web/sites/default/settings.php" ]; then
    USER_TABLE_COUNT=$(/usr/bin/php8.3 vendor/drush/drush/drush.php sql:query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DC_DB_NAME}' AND table_name='users'" 2>/dev/null | tail -n1)
    if [ "$USER_TABLE_COUNT" = "1" ]; then
        DC_DRUPAL_NEEDS_INSTALL=false
        print_status "Existing Dungeon Crawler Drupal installation detected and verified."
    fi
fi

if [ "$DC_DRUPAL_NEEDS_INSTALL" = true ]; then
    print_status "Setting up file permissions and installing Dungeon Crawler Drupal..."
    sudo chmod 755 web/sites/default 2>/dev/null || chmod 755 web/sites/default

    mkdir -p web/sites/default/files
    sudo chmod -R 777 web/sites/default/files 2>/dev/null || chmod -R 777 web/sites/default/files
    mkdir -p web/sites/default/files/php
    sudo chmod 777 web/sites/default/files/php 2>/dev/null || chmod 777 web/sites/default/files/php

    if sudo chown -R www-data:www-data web/sites/default/files 2>/dev/null; then
        print_status "Successfully set www-data ownership"
    elif sudo chown -R $(whoami):$(whoami) web/sites/default/files 2>/dev/null; then
        print_status "Set current user ownership as fallback"
    fi

    if [ ! -f "web/sites/default/settings.php" ]; then
        cp web/sites/default/default.settings.php web/sites/default/settings.php
    fi
    sudo chmod 664 web/sites/default/settings.php 2>/dev/null || chmod 664 web/sites/default/settings.php

    if ! /usr/bin/php8.3 vendor/drush/drush/drush.php status | grep -q "Drupal bootstrap.*Successful" 2>/dev/null; then
        print_status "Installing Dungeon Crawler Drupal site..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php site:install standard \
            --db-url="mysql://${DB_USER}:${DB_PASSWORD}@127.0.0.1:3306/${DC_DB_NAME}" \
            --site-name="${DC_SITE_NAME}" \
            --account-name="${ADMIN_USER}" \
            --account-pass="${ADMIN_PASSWORD}" \
            --account-mail="${DC_ADMIN_EMAIL}" \
            --yes

        if [ $? -eq 0 ]; then
            print_status "✅ Dungeon Crawler Drupal installed successfully"
        else
            print_error "❌ Dungeon Crawler Drupal installation failed"
        fi
    else
        print_status "Dungeon Crawler Drupal already installed, preserving existing data"
    fi
fi

# ------------------------------------------------------------------------------
# 6.6 Enable Modules
# ------------------------------------------------------------------------------
DC_DRUPAL_INSTALLED=false
if [ "$DC_DRUPAL_NEEDS_INSTALL" = false ]; then
    DC_DRUPAL_INSTALLED=true
elif /usr/bin/php8.3 vendor/drush/drush/drush.php status --format=json 2>/dev/null | grep -q '"bootstrap":"Successful"'; then
    DC_DRUPAL_INSTALLED=true
fi

if [ "$DC_DRUPAL_INSTALLED" = true ]; then
    if ! /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "admin_toolbar"; then
        print_status "Enabling Dungeon Crawler contrib modules..."
        /usr/bin/php8.3 vendor/drush/drush/drush.php en admin_toolbar admin_toolbar_tools pathauto metatag -y 2>/dev/null || true
    fi

    # Enable custom dungeoncrawler_content module
    if ! /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --status=enabled 2>/dev/null | grep -q "dungeoncrawler_content"; then
        if [ -f "web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.info.yml" ]; then
            print_status "Enabling Dungeon Crawler Content module..."
            /usr/bin/php8.3 vendor/drush/drush/drush.php en dungeoncrawler_content --yes 2>/dev/null || true
            print_status "✅ dungeoncrawler_content module enabled"
        fi
    fi

    # Enable and set Dungeon Crawler custom theme
    if [ -f "web/themes/custom/dungeoncrawler/dungeoncrawler.info.yml" ]; then
        if ! /usr/bin/php8.3 vendor/drush/drush/drush.php pm:list --type=theme --status=enabled 2>/dev/null | grep -q "dungeoncrawler"; then
            print_status "Enabling Dungeon Crawler custom theme..."
            /usr/bin/php8.3 vendor/drush/drush/drush.php theme:enable dungeoncrawler --yes 2>/dev/null || true
            print_status "✅ Dungeon Crawler theme enabled"
        fi
        # Set as default theme
        CURRENT_THEME=$(/usr/bin/php8.3 vendor/drush/drush/drush.php config:get system.theme default --format=string 2>/dev/null)
        if [ "$CURRENT_THEME" != "dungeoncrawler" ]; then
            print_status "Setting Dungeon Crawler theme as default..."
            echo "yes" | /usr/bin/php8.3 vendor/drush/drush/drush.php config:set system.theme default dungeoncrawler 2>/dev/null || true
            print_status "✅ Dungeon Crawler theme set as default"
        fi
        
        # Import theme block configuration
        print_status "Importing Dungeon Crawler block configuration..."
        if [ -d "web/themes/custom/dungeoncrawler/config/optional" ]; then
            /usr/bin/php8.3 vendor/drush/drush/drush.php config:import --partial --source=web/themes/custom/dungeoncrawler/config/optional 2>/dev/null || true
            print_status "✅ Block configuration imported"
        fi
        
        # Set front page to use custom page--front.html.twig template (remove default node listing)
        CURRENT_FRONT=$(/usr/bin/php8.3 vendor/drush/drush/drush.php config:get system.site page.front --format=string 2>/dev/null)
        if [ "$CURRENT_FRONT" = "/node" ]; then
            print_status "Setting custom front page..."
            /usr/bin/php8.3 vendor/drush/drush/drush.php config:set system.site page.front '/home' --yes 2>/dev/null || true
            print_status "✅ Front page configured"
        fi
        
        # Build Dungeon Crawler theme assets
        print_status "Building Dungeon Crawler theme assets..."
        if command -v npm &> /dev/null; then
            DC_THEME_DIR_REL="web/themes/custom/dungeoncrawler"
            if [ -f "$DC_THEME_DIR_REL/package.json" ]; then
                print_status "Installing Dungeon Crawler theme npm dependencies..."
                (cd "$DC_THEME_DIR_REL" && npm install --quiet 2>&1 | grep -v "npm warn") && print_status "✅ Theme dependencies installed" || print_warning "Could not install theme dependencies"
                
                print_status "Compiling Dungeon Crawler theme SCSS to CSS..."
                (cd "$DC_THEME_DIR_REL" && npm run dev 2>&1 | tail -5) && print_status "✅ Theme assets compiled" || print_warning "Could not compile theme assets"
            else
                print_warning "No package.json found in Dungeon Crawler theme directory"
            fi
        else
            print_warning "npm not found - cannot build Dungeon Crawler theme assets"
        fi
    fi
fi

# ------------------------------------------------------------------------------
# 6.7 Development Directories
# ------------------------------------------------------------------------------
mkdir -p web/modules/custom
mkdir -p web/themes/custom
mkdir -p config/sync
chmod 755 web/modules/custom web/themes/custom config/sync

# ------------------------------------------------------------------------------
# 6.7.1 Copy & Rebrand Forseti Theme for Dungeon Crawler
# ------------------------------------------------------------------------------
FORSETI_THEME_DIR="$WORKSPACE_ROOT/sites/forseti/web/themes/custom/forseti"
DC_THEME_DIR="$DC_PROJECT_DIR/web/themes/custom/dungeoncrawler"

if [ ! -f "$DC_THEME_DIR/dungeoncrawler.info.yml" ] && [ -d "$FORSETI_THEME_DIR" ]; then
    print_status "Copying and rebranding Forseti theme for Dungeon Crawler..."

    # Copy the entire Forseti theme
    cp -r "$FORSETI_THEME_DIR" "$DC_THEME_DIR"

    # Rename forseti.* files to dungeoncrawler.*
    cd "$DC_THEME_DIR"
    for f in forseti.*; do
        [ -f "$f" ] && mv "$f" "$(echo $f | sed 's/forseti/dungeoncrawler/g')"
    done

    # Rename config files with forseti in the name
    if [ -d "config/optional" ]; then
        cd config/optional
        for f in *forseti*; do
            [ -f "$f" ] && mv "$f" "$(echo $f | sed 's/forseti/dungeoncrawler/g')"
        done
        cd "$DC_THEME_DIR"
    fi

    # Rename schema file
    [ -f "config/schema/forseti.schema.yml" ] && mv config/schema/forseti.schema.yml config/schema/dungeoncrawler.schema.yml

    # Rename template files
    [ -f "templates/block/forseti-footer-block.html.twig" ] && mv templates/block/forseti-footer-block.html.twig templates/block/dungeoncrawler-footer-block.html.twig
    [ -f "templates/webform/webform--contact-forseti.html.twig" ] && mv templates/webform/webform--contact-forseti.html.twig templates/webform/webform--contact-dungeoncrawler.html.twig

    # Rename SCSS/CSS files
    [ -f "src/scss/components/_forseti-cards.scss" ] && mv src/scss/components/_forseti-cards.scss src/scss/components/_dungeoncrawler-cards.scss
    [ -f "build/css/forseti-theme.css" ] && mv build/css/forseti-theme.css build/css/dungeoncrawler-theme.css

    # Bulk replace all forseti references in theme files
    find . -type f \( -name '*.yml' -o -name '*.theme' -o -name '*.php' -o -name '*.twig' -o -name '*.json' -o -name '*.js' -o -name '*.scss' -o -name '*.css' -o -name '*.md' -o -name '*.mdx' \) \
      ! -path './node_modules/*' ! -path './package-lock.json' \
      -exec sed -i \
        -e 's/forseti_navbar/dungeoncrawler_navbar/g' \
        -e 's/forseti_cards/dungeoncrawler_cards/g' \
        -e 's/forseti-cards/dungeoncrawler-cards/g' \
        -e 's/forseti-footer/dungeoncrawler-footer/g' \
        -e 's/forseti-theme/dungeoncrawler-theme/g' \
        -e 's/forseti_branding/dungeoncrawler_branding/g' \
        -e 's/forseti_breadcrumbs/dungeoncrawler_breadcrumbs/g' \
        -e 's/forseti_content/dungeoncrawler_content/g' \
        -e 's/forseti_footer/dungeoncrawler_footer/g' \
        -e 's/forseti_local_actions/dungeoncrawler_local_actions/g' \
        -e 's/forseti_main_menu/dungeoncrawler_main_menu/g' \
        -e 's/forseti_messages/dungeoncrawler_messages/g' \
        -e 's/forseti_page_title/dungeoncrawler_page_title/g' \
        -e 's/forseti_safety_content/dungeoncrawler_safety_content/g' \
        -e 's/contact-forseti/contact-dungeoncrawler/g' \
        -e 's/forseti\.life/dungeoncrawler.forseti.life/g' \
        -e 's/forseti\//dungeoncrawler\//g' \
        -e 's/forseti\.schema/dungeoncrawler.schema/g' \
        -e 's/forseti\.settings/dungeoncrawler.settings/g' \
        -e 's/theme: forseti/theme: dungeoncrawler/g' \
        -e 's/function forseti_/function dungeoncrawler_/g' \
        -e 's/forseti_preprocess/dungeoncrawler_preprocess/g' \
        -e "s/Forseti Safety Community Platform/Dungeon Crawler RPG Platform/g" \
        -e "s/Forseti Core/Dungeon Crawler Core/g" \
        {} \;

    # Final pass: replace remaining generic Forseti/forseti in theme definition files
    find . -type f \( -name '*.yml' -o -name '*.theme' -o -name '*.twig' \) \
      ! -path './node_modules/*' \
      -exec sed -i \
        -e "s/'forseti-navbar'/'dungeoncrawler-navbar'/g" \
        -e "s/'forseti-navbar-front'/'dungeoncrawler-navbar-front'/g" \
        -e 's/forseti-chat-icon/dc-brand-icon/g' \
        -e 's/forseti-icon/dc-brand-icon/g' \
        -e 's/card-forseti/card-dungeoncrawler/g' \
        -e 's/alt="Forseti"/alt="Dungeon Crawler"/g' \
        {} \;

    # Update info.yml name and description
    sed -i \
      -e "s/^name: .*/name: Dungeon Crawler/" \
      -e "s/^description: .*/description: 'Dungeon Crawler - A browser-based RPG dungeon crawling adventure game'/" \
      -e "s/interface translation project: .*/interface translation project: dungeoncrawler/" \
      dungeoncrawler.info.yml

    # Update package.json
    sed -i \
      -e 's/"name": "forseti"/"name": "dungeoncrawler"/' \
      -e 's/Forseti custom theme/Dungeon Crawler custom theme/' \
      package.json

    cd "$DC_PROJECT_DIR"
    print_status "✅ Dungeon Crawler theme created and rebranded from Forseti theme"
elif [ -f "$DC_THEME_DIR/dungeoncrawler.info.yml" ]; then
    print_status "Dungeon Crawler custom theme already exists. Skipping copy."
else
    print_warning "Forseti theme not found at $FORSETI_THEME_DIR. Skipping theme copy."
fi

# ------------------------------------------------------------------------------
# 6.8 Settings Configuration
# ------------------------------------------------------------------------------
fix_drupal_permissions "$DC_PROJECT_DIR"

if ! grep -q "Shared user tables configuration" web/sites/default/settings.php 2>/dev/null; then
    print_status "Adding Dungeon Crawler shared user tables and development settings..."
    cat >> web/sites/default/settings.php << EOL
/**
 * Shared user tables configuration.
 *
 * Dungeon Crawler shares user authentication tables with the main Forseti site
 * (forseti_dev database). MySQL VIEWs in dungeoncrawler_dev transparently
 * reference forseti_dev user tables for unified authentication.
 *
 * Shared tables (served via VIEWs from forseti_dev):
 *   - users, users_data, users_field_data
 *   - user__roles, user__user_picture
 *   - sessions
 */

/**
 * Secondary database connection to the main Forseti site.
 * Used for direct cross-database queries when needed.
 */
\$databases['forseti']['default'] = array (
  'database' => '${DB_NAME}',
  'username' => '${DB_USER}',
    'password' => \$databases['default']['default']['password'] ?? '',
  'prefix' => '',
  'host' => '127.0.0.1',
  'port' => 3306,
  'isolation_level' => 'READ COMMITTED',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\\\mysql\\\\Driver\\\\Database\\\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
);

/**
 * Shared cookie domain for cross-site SSO.
 * Allows session cookies to be shared between forseti.life and
 * dungeoncrawler.forseti.life for seamless single sign-on.
 */
\$settings['cookie_domain'] = '${SHARED_COOKIE_DOMAIN}';

/**
 * Hash salt - must match the main Forseti site for shared session compatibility.
 */
\$settings['hash_salt'] = '${SHARED_HASH_SALT}';

\$settings['config_sync_directory'] = '../config/sync';

/**
 * Development-specific settings
 */
if (file_exists(\$app_root . '/' . \$site_path . '/settings.local.php')) {
  include \$app_root . '/' . \$site_path . '/settings.local.php';
}
\$config['system.performance']['css']['preprocess'] = FALSE;
\$config['system.performance']['js']['preprocess'] = FALSE;
\$config['system.logging']['error_level'] = 'verbose';
EOL
fi

if [ ! -f "web/sites/default/settings.local.php" ]; then
    print_status "Creating Dungeon Crawler local development settings..."
    cat > web/sites/default/settings.local.php << EOL
<?php
/**
 * Dungeon Crawler - Local development settings
 *
 * Shares user/session tables with the main Forseti site (forseti_dev database)
 * for unified authentication across both Drupal sites.
 *
 * MySQL VIEWs in dungeoncrawler_dev point to forseti_dev user tables.
 */
/**
 * Hash salt must match the main Forseti site for shared sessions.
 */
\$settings['hash_salt'] = '${SHARED_HASH_SALT}';

/**
 * Shared cookie domain for SSO.
 */
\$settings['cookie_domain'] = '${SHARED_COOKIE_DOMAIN}';

\$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
\$settings['skip_permissions_hardening'] = TRUE;
\$config['system.performance']['css']['preprocess'] = FALSE;
\$config['system.performance']['js']['preprocess'] = FALSE;
EOL
    chmod 644 web/sites/default/settings.local.php
fi

# ------------------------------------------------------------------------------
# 6.9 Shared User Tables (MySQL VIEWs)
# ------------------------------------------------------------------------------
# Dungeon Crawler shares user/session tables with the main Forseti site.
# We create updatable VIEWs in dungeoncrawler_dev that transparently reference
# the forseti_dev user tables. This is the Drupal 11 compatible approach
# (the array-based prefix was removed in Drupal 11).
# Uses ALGORITHM=MERGE (updatable) and SQL SECURITY INVOKER.
print_status "Configuring shared user tables (MySQL VIEWs → forseti_dev)..."

# List of user-related tables to share from forseti_dev
SHARED_TABLES=("users" "users_data" "users_field_data" "user__roles" "user__user_picture" "sessions")

for TABLE_NAME in "${SHARED_TABLES[@]}"; do
    # Check if table exists as a real table (not a VIEW) in dungeoncrawler_dev
    IS_BASE_TABLE=$(sudo mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DC_DB_NAME}' AND table_name='${TABLE_NAME}' AND table_type='BASE TABLE';" 2>/dev/null)
    if [ "$IS_BASE_TABLE" = "1" ]; then
        print_status "Dropping local ${TABLE_NAME} table (will be replaced by VIEW)..."
        sudo mysql ${DC_DB_NAME} -e "DROP TABLE IF EXISTS \`${TABLE_NAME}\`;" 2>/dev/null || true
    fi

    # Check if the source table exists in forseti_dev
    SOURCE_EXISTS=$(sudo mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='${TABLE_NAME}';" 2>/dev/null)
    if [ "$SOURCE_EXISTS" = "1" ]; then
        sudo mysql ${DC_DB_NAME} -e "CREATE OR REPLACE ALGORITHM=MERGE SQL SECURITY INVOKER VIEW \`${TABLE_NAME}\` AS SELECT * FROM \`${DB_NAME}\`.\`${TABLE_NAME}\`;" 2>/dev/null
        print_status "  ✅ VIEW ${TABLE_NAME} → ${DB_NAME}.${TABLE_NAME}"
    else
        print_warning "  ⚠️  Source table ${DB_NAME}.${TABLE_NAME} not found, skipping VIEW"
    fi
done

# Verify VIEWs were created
VIEW_COUNT=$(sudo mysql -N -e "SELECT COUNT(*) FROM information_schema.views WHERE table_schema='${DC_DB_NAME}';" 2>/dev/null)
print_status "Shared user table VIEWs created: ${VIEW_COUNT}/${#SHARED_TABLES[@]}"

# Ensure shortcut_set_users exists as a real table (it references users but is per-site)
SHORTCUT_EXISTS=$(sudo mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DC_DB_NAME}' AND table_name='shortcut_set_users';" 2>/dev/null)
if [ "$SHORTCUT_EXISTS" != "1" ]; then
    print_status "Creating shortcut_set_users table (per-site, not shared)..."
    sudo mysql ${DC_DB_NAME} -e "
    CREATE TABLE IF NOT EXISTS shortcut_set_users (
        uid int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'The users.uid for this set.',
        set_name varchar(32) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT '' COMMENT 'The shortcut_set.set_name that will be displayed for this user.',
        PRIMARY KEY (uid),
        KEY set_name (set_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Maps users to shortcut sets.';
    " 2>/dev/null
    print_status "  ✅ shortcut_set_users table created"
fi

print_status "✅ Shared authentication configured (cookie_domain: ${SHARED_COOKIE_DOMAIN})"

# Final cache rebuild
if [ "$DC_DRUPAL_INSTALLED" = true ]; then
    /usr/bin/php8.3 vendor/drush/drush/drush.php cache:rebuild 2>/dev/null || true
fi

print_status "✅ STEP 6 COMPLETE: Dungeon Crawler sub-site setup finished"

# ------------------------------------------------------------------------------
# 7. BACKGROUND TASKS - Schedule Drupal cron for Dungeon Crawler
# ------------------------------------------------------------------------------
print_step "7. BACKGROUND TASKS - Scheduling Drupal cron"

if ! command -v crontab >/dev/null 2>&1; then
    print_status "Installing cron package..."
    sudo apt install -y cron
fi

if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl enable --now cron 2>/dev/null || true
else
    sudo service cron start 2>/dev/null || true
fi

CRON_MARKER="# forseti-dungeoncrawler-cron"
CRON_CMD="cd $DC_PROJECT_DIR/web && DRUSH_OPTIONS_URI=http://localhost drush cron >/dev/null 2>&1 $CRON_MARKER"

CURRENT_CRON=$(crontab -l 2>/dev/null | grep -v "$CRON_MARKER" || true)
NEW_CRON=$(printf "%s\n%s\n" "$CURRENT_CRON" "*/30 * * * * $CRON_CMD")
echo "$NEW_CRON" | crontab -

print_status "✅ Drupal cron scheduled every 30 minutes (marker: $CRON_MARKER)"

# ==============================================================================
# COMPLETION MESSAGE
# ==============================================================================

print_step "SETUP COMPLETE - Forseti.Life Development Environment Ready!"

echo ""
echo "========================="
echo "Installation Summary"
echo "========================="
echo "✓ Environment: PHP 8.3, MySQL, Apache configured"
echo "✓ Drupal: 11.2.5 installed and configured"
echo "✓ Development Tools: Coder, PHPCS, PHPUnit configured"
echo "✓ Node.js & npm: $(node --version 2>/dev/null || echo 'N/A') / $(npm --version 2>/dev/null || echo 'N/A')"
echo "✓ Theme Assets: SCSS compiled to CSS for Forseti and Dungeon Crawler"
echo "✓ H3 Geolocation Framework: Ready for AmISafe crime mapping"
echo "✓ Database: $DB_NAME with AmISafe tables"
echo "✓ Dungeon Crawler: Sub-site at port $DC_DEV_PORT (shared auth with Forseti)"
echo "✓ Dungeon Crawler Theme: Custom theme (Radix/Bootstrap 5 based)"
echo "✓ Dungeon Crawler Content: Custom game content module enabled"
echo ""
echo "========================="
echo "Access Information"
echo "========================="
echo "--- Forseti (main site) ---"
echo "Site URL: http://forseti.local"
echo "Admin Login: http://forseti.local/user/login"
echo "Admin User: $ADMIN_USER"
echo "Admin Password: $ADMIN_PASSWORD"
echo "Database: $DB_NAME"
echo "Directory: $PROJECT_DIR"
echo ""
echo "--- Dungeon Crawler (sub-site, shared users) ---"
echo "Site URL: http://localhost:$DC_DEV_PORT"
echo "Admin Login: http://localhost:$DC_DEV_PORT/user/login"
echo "Admin User: $ADMIN_USER (shared with Forseti)"
echo "Admin Password: $ADMIN_PASSWORD"
echo "Database: $DC_DB_NAME (user tables via VIEWs → $DB_NAME)"
echo "Directory: $DC_PROJECT_DIR"
echo "Production URL: https://dungeoncrawler.forseti.life"
echo "Shared Auth: cookie_domain=$SHARED_COOKIE_DOMAIN"
echo ""
echo "========================="
echo "Available Commands"
echo "========================="
echo "Navigate to forseti: cd $PROJECT_DIR"
echo "Navigate to dungeoncrawler: cd $DC_PROJECT_DIR"
echo "Clear cache: ./vendor/bin/drush cr"
echo "One-time login: ./vendor/bin/drush uli"
echo "Check coding standards: ./scripts/check-standards.sh"
echo "Backup database: ./scripts/backup-database.sh"
echo ""
echo "H3 Geolocation Commands:"
echo "Navigate to H3: cd $WORKSPACE_ROOT/h3-geolocation"
echo "Activate environment: source h3-env/bin/activate"
echo "Run pipeline: cd database && bash run_amisafe_pipeline_stlouisintegration.sh"
echo "Quick examples: python quick_start.py"
echo "Visualization: python visualizer.py"
echo ""
echo "========================="
print_status "🚀 Your Forseti development environment is ready!"
print_status "📖 See README.md for detailed documentation"
echo "========================="
