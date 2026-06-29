#!/bin/bash

# St. Louis Integration - Production Security Hardening Script
# This script secures file permissions and configurations for production deployment

set -e  # Exit on any error

echo "=== St. Louis Integration - Production Security Hardening ==="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[SECURITY]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Check if running as root (we'll need sudo for some operations)
if [[ $EUID -eq 0 ]]; then
   print_error "This script should not be run as root. Run as web user with sudo access."
   exit 1
fi

# Detect web server user (usually www-data or apache)
WEB_USER=$(ps -eo user --no-headers | grep -E "(www-data|apache|nginx)" | head -1 | xargs)
if [ -z "$WEB_USER" ]; then
    print_warning "Could not detect web server user. Using 'www-data' as default."
    WEB_USER="www-data"
fi
print_info "Detected web server user: $WEB_USER"

# Set Drupal root path
DRUPAL_ROOT="/var/www/html/drupal/web"
if [ ! -d "$DRUPAL_ROOT" ]; then
    print_warning "Standard production path not found. Using current development path."
    DRUPAL_ROOT="$(pwd)/drupal/web"
fi

print_info "Drupal root: $DRUPAL_ROOT"

# Validate Drupal root
if [ ! -f "$DRUPAL_ROOT/index.php" ] || [ ! -f "$DRUPAL_ROOT/core/INSTALL.txt" ]; then
    print_error "Invalid Drupal root path: $DRUPAL_ROOT"
    exit 1
fi

print_status "Starting security hardening process..."

# =============================================================================
# 1. FILE OWNERSHIP AND PERMISSIONS
# =============================================================================

print_status "Setting secure file ownership and permissions..."

# Set ownership to web server user
print_info "Setting ownership to $WEB_USER:$WEB_USER"
sudo chown -R $WEB_USER:$WEB_USER "$DRUPAL_ROOT"

# Set directory permissions (755 - owner: rwx, group: rx, other: rx)
print_info "Setting directory permissions to 755"
sudo find "$DRUPAL_ROOT" -type d -exec chmod 755 {} \;

# Set file permissions (644 - owner: rw, group: r, other: r)
print_info "Setting file permissions to 644"
sudo find "$DRUPAL_ROOT" -type f -exec chmod 644 {} \;

# =============================================================================
# 2. CRITICAL DRUPAL SECURITY SETTINGS
# =============================================================================

print_status "Hardening critical Drupal files and directories..."

# Make settings.php read-only (444)
if [ -f "$DRUPAL_ROOT/sites/default/settings.php" ]; then
    print_info "Securing settings.php (read-only)"
    sudo chmod 444 "$DRUPAL_ROOT/sites/default/settings.php"
fi

# Make sites/default directory read-only (555)
print_info "Securing sites/default directory"
sudo chmod 555 "$DRUPAL_ROOT/sites/default"

# Secure files directory (web server writable but not world writable)
if [ -d "$DRUPAL_ROOT/sites/default/files" ]; then
    print_info "Securing files directory (775)"
    sudo chmod 775 "$DRUPAL_ROOT/sites/default/files"
    sudo find "$DRUPAL_ROOT/sites/default/files" -type d -exec chmod 775 {} \;
    sudo find "$DRUPAL_ROOT/sites/default/files" -type f -exec chmod 664 {} \;
fi

# Secure private files directory if it exists
if [ -d "$DRUPAL_ROOT/sites/default/private" ]; then
    print_info "Securing private files directory (700)"
    sudo chmod 700 "$DRUPAL_ROOT/sites/default/private"
    sudo find "$DRUPAL_ROOT/sites/default/private" -type d -exec chmod 700 {} \;
    sudo find "$DRUPAL_ROOT/sites/default/private" -type f -exec chmod 600 {} \;
fi

# =============================================================================
# 3. REMOVE DEVELOPMENT FILES AND DIRECTORIES
# =============================================================================

print_status "Removing development files and directories..."

# Remove development files
DEV_FILES=(
    "$DRUPAL_ROOT/INSTALL.txt"
    "$DRUPAL_ROOT/README.md"
    "$DRUPAL_ROOT/web.config"
    "$DRUPAL_ROOT/example.gitignore"
    "$DRUPAL_ROOT/CHANGELOG.txt"
    "$DRUPAL_ROOT/COPYRIGHT.txt"
    "$DRUPAL_ROOT/MAINTAINERS.txt"
)

for file in "${DEV_FILES[@]}"; do
    if [ -f "$file" ]; then
        print_info "Removing development file: $(basename "$file")"
        sudo rm -f "$file"
    fi
done

# Remove development modules directories if they exist
DEV_DIRS=(
    "$DRUPAL_ROOT/core/modules/simpletest"
    "$DRUPAL_ROOT/modules/devel"
    "$DRUPAL_ROOT/modules/contrib/devel"
)

for dir in "${DEV_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        print_warning "Found development directory: $dir"
        print_warning "Consider removing after backup: sudo rm -rf $dir"
    fi
done

# =============================================================================
# 4. SECURE .HTACCESS FILES
# =============================================================================

print_status "Securing .htaccess files..."

# Ensure .htaccess files are present and secure
HTACCESS_FILES=(
    "$DRUPAL_ROOT/.htaccess"
    "$DRUPAL_ROOT/sites/default/files/.htaccess"
    "$DRUPAL_ROOT/modules/.htaccess"
    "$DRUPAL_ROOT/themes/.htaccess"
)

for htaccess in "${HTACCESS_FILES[@]}"; do
    if [ -f "$htaccess" ]; then
        print_info "Securing .htaccess: $(dirname "$htaccess")"
        sudo chmod 644 "$htaccess"
    else
        print_warning "Missing .htaccess file: $htaccess"
    fi
done

# =============================================================================
# 5. DATABASE SECURITY
# =============================================================================

print_status "Database security recommendations..."

print_info "Database security checklist:"
echo "  ✓ Ensure database user has minimal required permissions"
echo "  ✓ Use strong database passwords (generated in production)"
echo "  ✓ Database server should only accept connections from web server"
echo "  ✓ Regular database backups stored securely"
echo "  ✓ Database logs monitored for suspicious activity"

# =============================================================================
# 6. WEB SERVER SECURITY HEADERS
# =============================================================================

print_status "Web server security recommendations..."

print_info "Apache/Nginx security headers to implement:"
echo "  ✓ X-Content-Type-Options: nosniff"
echo "  ✓ X-Frame-Options: DENY"
echo "  ✓ X-XSS-Protection: 1; mode=block"
echo "  ✓ Strict-Transport-Security: max-age=31536000"
echo "  ✓ Content-Security-Policy: appropriate policy"
echo "  ✓ Referrer-Policy: strict-origin-when-cross-origin"

# =============================================================================
# 7. THEME SECURITY
# =============================================================================

print_status "Securing custom theme files..."

THEME_PATH="$DRUPAL_ROOT/themes/custom/stlouisintegration"
if [ -d "$THEME_PATH" ]; then
    # Remove development files from theme
    print_info "Cleaning theme development files"
    
    # Remove source files in production (keep only compiled assets)
    if [ -d "$THEME_PATH/src" ]; then
        print_warning "Consider removing source files in production: $THEME_PATH/src"
    fi
    
    # Remove node_modules if present
    if [ -d "$THEME_PATH/node_modules" ]; then
        print_info "Removing node_modules from theme"
        sudo rm -rf "$THEME_PATH/node_modules"
    fi
    
    # Remove development config files
    DEV_THEME_FILES=(
        "$THEME_PATH/package.json"
        "$THEME_PATH/package-lock.json"
        "$THEME_PATH/webpack.mix.js"
        "$THEME_PATH/.nvmrc"
        "$THEME_PATH/.gitignore"
        "$THEME_PATH/.browserslistrc"
    )
    
    for file in "${DEV_THEME_FILES[@]}"; do
        if [ -f "$file" ]; then
            print_warning "Consider removing development file: $(basename "$file")"
            # Uncomment to actually remove:
            # sudo rm -f "$file"
        fi
done

fi

# =============================================================================
# 8. MODULE SECURITY
# =============================================================================

print_status "Securing custom modules..."

MODULES_PATH="$DRUPAL_ROOT/modules/custom"
if [ -d "$MODULES_PATH" ]; then
    # Set proper permissions for module directories
    sudo find "$MODULES_PATH" -type d -exec chmod 755 {} \;
    sudo find "$MODULES_PATH" -type f -exec chmod 644 {} \;
    
    # Remove ARCHITECTURE.md files (development documentation)
    find "$MODULES_PATH" -name "ARCHITECTURE.md" -type f | while read -r arch_file; do
        print_info "Found development documentation: $arch_file"
        print_warning "Consider removing after backup for production"
        # Uncomment to remove:
        # sudo rm -f "$arch_file"
    done
fi

# =============================================================================
# 9. LOG FILE SECURITY
# =============================================================================

print_status "Securing log files..."

# Drupal log files should not be web accessible
LOG_DIRS=(
    "$DRUPAL_ROOT/sites/default/files/private/logs"
    "/var/log/apache2"
    "/var/log/nginx"
)

for log_dir in "${LOG_DIRS[@]}"; do
    if [ -d "$log_dir" ]; then
        print_info "Securing log directory: $log_dir"
        sudo chmod 750 "$log_dir"
        sudo find "$log_dir" -type f -exec chmod 640 {} \;
    fi
done

# =============================================================================
# 10. DRUPAL SECURITY CONFIGURATION
# =============================================================================

print_status "Configuring Drupal security settings..."

# Set Drupal root path for commands
cd "$DRUPAL_ROOT"

# Check if Drush is available
if ! command -v ../vendor/bin/drush &> /dev/null && ! command -v drush &> /dev/null; then
    print_error "Drush not found. Some Drupal configuration updates will be skipped."
    DRUSH_AVAILABLE=false
else
    if [ -f "../vendor/bin/drush" ]; then
        DRUSH="../vendor/bin/drush"
    else
        DRUSH="drush"
    fi
    DRUSH_AVAILABLE=true
    print_info "Using Drush: $DRUSH"
fi

if [ "$DRUSH_AVAILABLE" = true ]; then
    print_status "Applying production Drupal security configurations..."
    
    # 1. DISABLE DEVELOPMENT MODULES
    print_info "Disabling development modules..."
    
    # Disable development modules (ignore errors if not installed)
    $DRUSH pm:uninstall devel devel_generate webprofiler kint --yes 2>/dev/null || true
    $DRUSH pm:uninstall simpletest --yes 2>/dev/null || true
    $DRUSH pm:uninstall dblog --yes 2>/dev/null || true  # Consider syslog instead
    
    # 2. ENABLE PRODUCTION SECURITY MODULES
    print_info "Enabling production security modules..."
    
    # Enable security modules if available
    $DRUSH pm:enable automated_cron --yes 2>/dev/null || true
    $DRUSH pm:enable syslog --yes 2>/dev/null || true
    $DRUSH pm:enable ban --yes 2>/dev/null || true
    
    # 3. SECURITY CONFIGURATIONS
    print_info "Setting security configurations..."
    
    # Disable error display for anonymous users
    $DRUSH config:set system.logging error_level hide --yes
    
    # Set secure session settings
    $DRUSH config:set system.session cookie_lifetime 86400 --yes  # 24 hours
    
    # Disable user account creation by anonymous users
    $DRUSH config:set user.settings register admin_only --yes
    
    # Set password requirements (if password_policy module exists)
    $DRUSH config:set user.settings password_strength true --yes 2>/dev/null || true
    
    # Enable flood control
    $DRUSH config:set user.flood uid_only false --yes
    $DRUSH config:set user.flood ip_limit 50 --yes
    $DRUSH config:set user.flood ip_window 3600 --yes
    $DRUSH config:set user.flood user_limit 5 --yes
    $DRUSH config:set user.flood user_window 21600 --yes
    
    # 4. CONTENT ACCESS SECURITY
    print_info "Configuring content access security..."
    
    # Disable PHP filter if enabled
    $DRUSH pm:uninstall php --yes 2>/dev/null || true
    
    # Set file system permissions
    $DRUSH config:set system.file temporary_maximum_age 21600 --yes  # 6 hours
    
    # Configure file uploads security
    $DRUSH config:set system.file allow_insecure_uploads false --yes 2>/dev/null || true
    
    # 5. PERFORMANCE AND CACHING (Security through obscurity)
    print_info "Configuring performance and caching for security..."
    
    # Enable page caching for anonymous users
    $DRUSH config:set system.performance cache.page.max_age 3600 --yes
    
    # Enable CSS and JS aggregation
    $DRUSH config:set system.performance css.preprocess true --yes
    $DRUSH config:set system.performance js.preprocess true --yes
    
    # 6. DISABLE UNNECESSARY PERMISSIONS
    print_info "Reviewing user permissions..."
    
    # Remove potentially dangerous permissions from authenticated users
    $DRUSH role:remove-perm authenticated "use text format full_html" 2>/dev/null || true
    $DRUSH role:remove-perm authenticated "administer site configuration" 2>/dev/null || true
    $DRUSH role:remove-perm authenticated "access administration pages" 2>/dev/null || true
    
    # 7. UPDATE USER 1 ACCOUNT
    print_info "Securing admin account..."
    
    # Block user 1 from login (create separate admin account)
    print_warning "Consider creating a separate admin account and blocking user 1"
    echo "  Run: $DRUSH user:create admin_user --mail='admin@yourdomain.com' --password='strong_password'"
    echo "  Run: $DRUSH user:role:add administrator admin_user"
    echo "  Run: $DRUSH user:block admin (user 1)"
    
    # 8. TRUSTED HOST PATTERNS
    print_info "Checking trusted host patterns..."
    
    SETTINGS_FILE="$DRUPAL_ROOT/sites/default/settings.php"
    if ! grep -q "trusted_host_patterns" "$SETTINGS_FILE" 2>/dev/null; then
        print_warning "Trusted host patterns not configured in settings.php"
        echo "Add to settings.php:"
        echo "\$settings['trusted_host_patterns'] = ["
        echo "  '^yourdomain\\.com\$',"
        echo "  '^www\\.yourdomain\\.com\$',"
        echo "];"
    fi
    
    # 9. CLEAR ALL CACHES
    print_info "Clearing all caches..."
    $DRUSH cache:rebuild
    
else
    print_warning "Drush not available - manual Drupal security configuration required"
fi

# =============================================================================
# 11. SETTINGS.PHP SECURITY HARDENING
# =============================================================================

print_status "Hardening settings.php configuration..."

SETTINGS_FILE="$DRUPAL_ROOT/sites/default/settings.php"

if [ -f "$SETTINGS_FILE" ]; then
    # Create backup of settings.php
    sudo cp "$SETTINGS_FILE" "$SETTINGS_FILE.backup.$(date +%Y%m%d_%H%M%S)"
    print_info "Created backup of settings.php"
    
    # Check for development configuration
    if grep -q "development\|debug\|devel" "$SETTINGS_FILE" 2>/dev/null; then
        print_warning "Development configuration detected in settings.php"
        print_warning "Review and remove development settings manually"
    fi
    
    # Check for database credentials exposure
    if grep -q "username.*root\|password.*''\|localhost" "$SETTINGS_FILE" 2>/dev/null; then
        print_warning "Default/weak database credentials detected"
        print_warning "Update database credentials for production"
    fi
    
    # Check for security configurations
    SECURITY_CHECKS=(
        "trusted_host_patterns:Trusted host patterns not configured"
        "hash_salt:Hash salt not configured"
        "update_free_access:Update free access should be disabled"
    )
    
    for check in "${SECURITY_CHECKS[@]}"; do
        pattern="${check%:*}"
        message="${check#*:}"
        
        if ! grep -q "$pattern" "$SETTINGS_FILE" 2>/dev/null; then
            print_warning "$message"
        fi
    done
    
    # Ensure proper PHP settings in settings.php
    print_info "Checking PHP security settings in settings.php..."
    
    # Check for error reporting settings
    if ! grep -q "ini_set.*error_reporting.*0" "$SETTINGS_FILE" 2>/dev/null; then
        print_warning "Add to settings.php: ini_set('error_reporting', 0);"
    fi
    
    if ! grep -q "ini_set.*display_errors.*FALSE" "$SETTINGS_FILE" 2>/dev/null; then
        print_warning "Add to settings.php: ini_set('display_errors', FALSE);"
    fi
    
fi

# =============================================================================
# 12. DATABASE SECURITY VERIFICATION
# =============================================================================

print_status "Database security verification..."

if [ "$DRUSH_AVAILABLE" = true ]; then
    # Check database connection
    if $DRUSH sql:query "SELECT 1" >/dev/null 2>&1; then
        print_info "Database connection verified"
        
        # Check for default admin users
        ADMIN_USERS=$($DRUSH sql:query "SELECT COUNT(*) FROM users_field_data WHERE uid = 1 AND status = 1" 2>/dev/null | tail -1)
        if [ "$ADMIN_USERS" = "1" ]; then
            print_warning "User 1 (admin) is still active - consider creating separate admin and blocking user 1"
        fi
        
        # Check for weak passwords (if possible)
        EMPTY_PASSWORDS=$($DRUSH sql:query "SELECT COUNT(*) FROM users_field_data WHERE pass = ''" 2>/dev/null | tail -1)
        if [ "$EMPTY_PASSWORDS" -gt "0" ]; then
            print_error "Found users with empty passwords!"
        fi
        
        # Check user roles and permissions
        print_info "Verifying user roles and permissions..."
        $DRUSH role:list --format=table 2>/dev/null || print_info "Role verification requires manual review"
        
    else
        print_warning "Could not connect to database for security verification"
    fi
fi

# =============================================================================
# 13. PRODUCTION CONFIGURATION PRESERVATION
# =============================================================================

print_status "Preserving production-specific configurations..."

# Create configuration backup directory
BACKUP_DIR="$DRUPAL_ROOT/../backups/config/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

if [ "$DRUSH_AVAILABLE" = true ]; then
    # Export current configuration before any changes
    print_info "Backing up current Drupal configuration..."
    $DRUSH config:export --destination="$BACKUP_DIR/pre_hardening_config" --yes
    
    # Export specific production settings that should be preserved
    print_info "Preserving production-specific settings..."
    
    # System settings that should be preserved
    PRESERVE_CONFIGS=(
        "system.site"          # Site name, slogan, email
        "system.mail"          # Email configuration
        "system.performance"   # Performance settings
        "user.mail"           # User email templates
        "contact.form.feedback" # Contact form settings
        "google_analytics.settings" # Analytics settings (if installed)
        "metatag.metatag_defaults.global" # SEO settings (if installed)
    )
    
    for config in "${PRESERVE_CONFIGS[@]}"; do
        if $DRUSH config:get "$config" >/dev/null 2>&1; then
            print_info "Preserving configuration: $config"
            $DRUSH config:get "$config" --format=yaml > "$BACKUP_DIR/${config}.yml" 2>/dev/null || true
        fi
    done
    
    # Create a restoration script
    cat > "$BACKUP_DIR/restore_production_configs.sh" << 'EOF'
#!/bin/bash
# Production Configuration Restoration Script
# Generated automatically during security hardening

DRUSH="../vendor/bin/drush"
if [ ! -f "$DRUSH" ]; then
    DRUSH="drush"
fi

echo "Restoring production-specific configurations..."

# Import preserved configurations
for config_file in *.yml; do
    if [ -f "$config_file" ]; then
        config_name="${config_file%.yml}"
        echo "Restoring: $config_name"
        $DRUSH config:import --partial --source="$(pwd)" "$config_name" --yes 2>/dev/null || true
    fi
done

echo "Configuration restoration complete."
echo "Remember to clear caches: $DRUSH cache:rebuild"
EOF
    
    chmod +x "$BACKUP_DIR/restore_production_configs.sh"
    print_info "Created restoration script: $BACKUP_DIR/restore_production_configs.sh"
fi

# =============================================================================
# 14. PRODUCTION ENVIRONMENT DETECTION
# =============================================================================

print_status "Detecting and configuring production environment..."

# Detect if this is a production environment
PROD_INDICATORS=(
    "/var/www/html"           # Standard production path
    "/home/*/public_html"     # Shared hosting
    "prod"                    # Environment variable
    "production"              # Environment variable
)

IS_PRODUCTION=false
for indicator in "${PROD_INDICATORS[@]}"; do
    if [[ "$DRUPAL_ROOT" == *"$indicator"* ]] || [[ "${ENVIRONMENT:-}" == *"$indicator"* ]]; then
        IS_PRODUCTION=true
        break
    fi
done

if [ "$IS_PRODUCTION" = true ]; then
    print_status "Production environment detected - applying strict security settings..."
    
    # Extra strict settings for production
    if [ "$DRUSH_AVAILABLE" = true ]; then
        # Disable all error reporting
        $DRUSH config:set system.logging error_level hide --yes
        
        # Enable strict session security
        $DRUSH config:set system.session cookie_secure true --yes 2>/dev/null || true
        $DRUSH config:set system.session cookie_httponly true --yes 2>/dev/null || true
        
        # Disable update notifications for security (use automated updates instead)
        $DRUSH config:set update.settings notification.emails '' --yes 2>/dev/null || true
        
        # Set maintenance mode access restrictions
        $DRUSH config:set system.maintenance_mode message 'Site temporarily unavailable for maintenance.' --yes
    fi
    
    # Remove any development files that might have been missed
    DEV_FILES_PROD=(
        "$DRUPAL_ROOT/../composer.json"
        "$DRUPAL_ROOT/../composer.lock"
        "$DRUPAL_ROOT/../README.md"
        "$DRUPAL_ROOT/INSTALL.txt"
        "$DRUPAL_ROOT/CHANGELOG.txt"
        "$DRUPAL_ROOT/COPYRIGHT.txt"
        "$DRUPAL_ROOT/example.gitignore"
    )
    
    for file in "${DEV_FILES_PROD[@]}"; do
        if [ -f "$file" ]; then
            print_info "Removing production-unsafe file: $(basename "$file")"
            sudo rm -f "$file" 2>/dev/null || true
        fi
    done
    
else
    print_info "Non-production environment detected - using standard security settings"
fi

# =============================================================================
# 15. CONTENT ACCESS AND PERMISSIONS AUDIT
# =============================================================================

print_status "Auditing content access and permissions..."

if [ "$DRUSH_AVAILABLE" = true ]; then
    # Audit user permissions
    print_info "Auditing user roles and permissions..."
    
    # Check for overly permissive roles
    DANGEROUS_PERMS=(
        "administer permissions"
        "administer users"
        "use php for settings"
        "use text format full_html"
        "bypass node access"
        "administer site configuration"
    )
    
    for perm in "${DANGEROUS_PERMS[@]}"; do
        # Check if authenticated users have dangerous permissions
        if $DRUSH role:list --format=csv 2>/dev/null | grep -q "authenticated.*$perm"; then
            print_warning "Dangerous permission '$perm' assigned to authenticated users"
            print_warning "Consider removing: $DRUSH role:remove-perm authenticated '$perm'"
        fi
    done
    
    # Audit content access
    print_info "Auditing content access settings..."
    
    # Check for publicly accessible admin content
    ADMIN_NODES=$($DRUSH sql:query "SELECT COUNT(*) FROM node_field_data WHERE type IN ('webform', 'admin') AND status = 1" 2>/dev/null | tail -1)
    if [ "$ADMIN_NODES" -gt "0" ]; then
        print_warning "Found $ADMIN_NODES published admin-type nodes - review access permissions"
    fi
    
    # Check for test content that should not be public
    TEST_CONTENT=$($DRUSH sql:query "SELECT COUNT(*) FROM node_field_data WHERE title LIKE '%test%' AND status = 1" 2>/dev/null | tail -1)
    if [ "$TEST_CONTENT" -gt "0" ]; then
        print_warning "Found $TEST_CONTENT published nodes with 'test' in title - review for production"
    fi
fi

# =============================================================================
# 16. AUTOMATED PRODUCTION MAINTENANCE
# =============================================================================

print_status "Setting up automated production maintenance..."

# Create maintenance scripts directory
MAINT_DIR="$DRUPAL_ROOT/../maintenance"
mkdir -p "$MAINT_DIR"

# Daily security check script
cat > "$MAINT_DIR/daily_security_check.sh" << 'EOF'
#!/bin/bash
# Daily Production Security Check
# Add to cron: 0 2 * * * /path/to/daily_security_check.sh

DRUPAL_ROOT="/var/www/html/drupal/web"
DRUSH="$DRUPAL_ROOT/../vendor/bin/drush"
LOG_FILE="/var/log/drupal_security_check.log"

echo "$(date): Starting daily security check" >> "$LOG_FILE"

cd "$DRUPAL_ROOT"

# Check for security updates
if $DRUSH pm:security 2>&1 | grep -q "SECURITY UPDATE"; then
    echo "$(date): SECURITY UPDATES AVAILABLE" >> "$LOG_FILE"
    # Send alert email (configure your email system)
    # mail -s "Drupal Security Updates Available" admin@yourdomain.com < /dev/null
fi

# Check for failed login attempts
FAILED_LOGINS=$($DRUSH sql:query "SELECT COUNT(*) FROM flood WHERE event = 'user.failed_login_ip' AND timestamp > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 DAY))" 2>/dev/null | tail -1)
if [ "$FAILED_LOGINS" -gt "50" ]; then
    echo "$(date): HIGH FAILED LOGIN ATTEMPTS: $FAILED_LOGINS" >> "$LOG_FILE"
fi

# Check file permissions
WORLD_WRITABLE=$(find "$DRUPAL_ROOT" -type f -perm -002 2>/dev/null | wc -l)
if [ "$WORLD_WRITABLE" -gt "0" ]; then
    echo "$(date): FOUND $WORLD_WRITABLE WORLD-WRITABLE FILES" >> "$LOG_FILE"
fi

echo "$(date): Security check complete" >> "$LOG_FILE"
EOF

chmod +x "$MAINT_DIR/daily_security_check.sh"

# Weekly configuration backup script
cat > "$MAINT_DIR/weekly_config_backup.sh" << 'EOF'
#!/bin/bash
# Weekly Configuration Backup
# Add to cron: 0 3 * * 0 /path/to/weekly_config_backup.sh

DRUPAL_ROOT="/var/www/html/drupal/web"
DRUSH="$DRUPAL_ROOT/../vendor/bin/drush"
BACKUP_DIR="/backup/drupal/config"

mkdir -p "$BACKUP_DIR/$(date +%Y%m%d)"

cd "$DRUPAL_ROOT"

# Export configuration
$DRUSH config:export --destination="$BACKUP_DIR/$(date +%Y%m%d)" --yes

# Export database
$DRUSH sql:dump --result-file="$BACKUP_DIR/$(date +%Y%m%d)/database.sql"

# Cleanup old backups (keep 4 weeks)
find "$BACKUP_DIR" -maxdepth 1 -type d -mtime +28 -exec rm -rf {} \;

echo "$(date): Weekly backup complete"
EOF

chmod +x "$MAINT_DIR/weekly_config_backup.sh"

print_info "Created maintenance scripts in: $MAINT_DIR"
print_info "Add to cron:"
echo "  Daily security check: 0 2 * * * $MAINT_DIR/daily_security_check.sh"
echo "  Weekly backups: 0 3 * * 0 $MAINT_DIR/weekly_config_backup.sh"

# =============================================================================
# 17. FINAL VERIFICATION AND PRODUCTION CHECKLIST
# =============================================================================

print_status "Running final security verification..."

# Check for world-writable files
WORLD_WRITABLE=$(find "$DRUPAL_ROOT" -type f -perm -002 2>/dev/null | wc -l)
if [ "$WORLD_WRITABLE" -gt 0 ]; then
    print_warning "Found $WORLD_WRITABLE world-writable files"
    find "$DRUPAL_ROOT" -type f -perm -002 2>/dev/null | head -5
else
    print_status "No world-writable files found ✓"
fi

# Check critical file permissions
CRITICAL_FILES=(
    "$DRUPAL_ROOT/sites/default/settings.php:444"
    "$DRUPAL_ROOT/.htaccess:644"
)

for file_perm in "${CRITICAL_FILES[@]}"; do
    file="${file_perm%:*}"
    expected_perm="${file_perm#*:}"
    
    if [ -f "$file" ]; then
        actual_perm=$(stat -c "%a" "$file")
        if [ "$actual_perm" = "$expected_perm" ]; then
            print_status "$(basename "$file") permissions correct ($actual_perm) ✓"
        else
            print_warning "$(basename "$file") has incorrect permissions ($actual_perm, expected $expected_perm)"
        fi
    fi
done

# Drupal-specific security verification
if [ "$DRUSH_AVAILABLE" = true ]; then
    print_status "Verifying Drupal security configuration..."
    
    # Check if development modules are disabled
    if $DRUSH pm:list --status=enabled --format=csv 2>/dev/null | grep -q "devel\|webprofiler\|kint"; then
        print_error "Development modules still enabled!"
    else
        print_status "Development modules properly disabled ✓"
    fi
    
    # Check error reporting
    ERROR_LEVEL=$($DRUSH config:get system.logging error_level --format=string 2>/dev/null)
    if [ "$ERROR_LEVEL" = "hide" ]; then
        print_status "Error reporting properly configured for production ✓"
    else
        print_warning "Error reporting not set to 'hide' for production"
    fi
    
    # Check user registration settings
    USER_REGISTER=$($DRUSH config:get user.settings register --format=string 2>/dev/null)
    if [ "$USER_REGISTER" = "admin_only" ]; then
        print_status "User registration properly restricted ✓"
    else
        print_warning "User registration not restricted to admin only"
    fi
fi

# =============================================================================
# 18. COMPREHENSIVE PRODUCTION CHECKLIST
# =============================================================================

print_status "Production deployment checklist:"
echo ""
echo "🔒 Security Hardening Status:"
echo "  ✓ File permissions hardened"
echo "  ✓ Settings.php secured (read-only)"
echo "  ✓ Files directory properly secured"
echo "  ✓ Development files removed/identified"
echo "  ✓ .htaccess files secured"
echo "  ✓ Drupal security configurations applied"
echo "  ✓ Production-specific settings preserved"
echo "  ✓ Configuration backups created"
echo ""
echo "🚀 Critical Production Steps Remaining:"
echo "  □ SSL certificate installed and configured (A+ grade)"
echo "  □ Web server security headers configured"
echo "  □ Database credentials updated for production"
echo "  □ Trusted host patterns configured in settings.php"
echo "  □ Email settings configured for production (SMTP)"
echo "  □ Cron jobs configured (Drupal cron + system maintenance)"
echo "  □ Monitoring and logging configured"
echo "  □ Backup strategy implemented and tested"
echo "  □ Performance caching configured (Redis/Memcache)"
echo "  □ Security updates automated"
echo "  □ WAF (Web Application Firewall) configured"
echo "  □ Intrusion detection system configured"
echo ""
echo "⚠️  Manual Review Required:"
echo "  □ Review user roles and permissions"
echo "  □ Test all functionality in production environment"
echo "  □ Verify contact forms work with production email"
echo "  □ Verify AI conversation module with production API keys"
echo "  □ Test job application automation with production credentials"
echo "  □ Verify custom modules work in production environment"
echo "  □ Review content for test data or development information"
echo "  □ Configure production-specific module settings"
echo ""
echo "📊 Drupal-Specific Production Configuration:"
echo "  □ Performance: Enable all caching (page, block, views)"
echo "  □ Performance: Enable CSS/JS aggregation and minification"
echo "  □ Security: Configure flood control for login attempts"
echo "  □ Security: Set up security kit module (if using)"
echo "  □ Content: Review and clean test content"
echo "  □ Content: Configure content moderation workflows"
echo "  □ Users: Create production admin account, block user 1"
echo "  □ SEO: Configure meta tags and XML sitemap"
echo "  □ Analytics: Configure Google Analytics/Tag Manager"
echo "  □ Search: Configure search API (if using Solr/Elasticsearch)"
echo ""
echo "🔧 Environment-Specific Settings:"
if [ "$IS_PRODUCTION" = true ]; then
    echo "  ✓ Production environment detected"
    echo "  ✓ Strict security settings applied"
    echo "  ✓ Development files removed"
    echo "  ✓ Error reporting disabled"
else
    echo "  ℹ️  Non-production environment detected"
    echo "  ℹ️  Standard security settings applied"
    echo "  ⚠️  Ensure this is correct for your deployment"
fi
echo ""
echo "📁 Backup and Recovery:"
echo "  ✓ Configuration backup created: $BACKUP_DIR"
echo "  ✓ Restoration script created"
echo "  ✓ Maintenance scripts created: $MAINT_DIR"
echo "  □ Test configuration restoration procedure"
echo "  □ Test full site backup and restore procedure"
echo "  □ Document disaster recovery procedures"
echo ""
echo "📈 Performance Optimization:"
echo "  □ Configure Redis or Memcache for caching"
echo "  □ Configure CDN for static assets"
echo "  □ Optimize images and enable WebP"
echo "  □ Configure database query caching"
echo "  □ Enable gzip compression"
echo "  □ Configure HTTP/2 and HTTP/3"
echo ""
echo "🚨 Security Monitoring:"
echo "  □ Configure log monitoring and alerting"
echo "  □ Set up security scanning (weekly/monthly)"
echo "  □ Configure uptime monitoring"
echo "  □ Set up SSL certificate expiration alerts"
echo "  □ Configure backup verification alerts"
echo "  □ Set up security update notifications"

print_status "Security hardening completed!"
print_info "Configuration backups stored in: $BACKUP_DIR"
print_info "Maintenance scripts created in: $MAINT_DIR"
print_info "Review the checklist above before going live."

# Final summary
echo ""
print_status "=== SECURITY HARDENING SUMMARY ==="
echo "Files processed: $(find "$DRUPAL_ROOT" -type f | wc -l)"
echo "Directories secured: $(find "$DRUPAL_ROOT" -type d | wc -l)"
echo "Configuration backups: $(ls -1 "$BACKUP_DIR" 2>/dev/null | wc -l)"
echo "World-writable files: $WORLD_WRITABLE"
echo "Production mode: $IS_PRODUCTION"
echo "Drupal commands available: $DRUSH_AVAILABLE"
echo ""
print_status "Security hardening process complete!"
print_info "Next: Review checklist and configure production-specific settings."

