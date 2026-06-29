#!/bin/bash

# St. Louis Integration - Production Configuration Management
# This script manages production-specific Drupal configurations

set -e  # Exit on any error

echo "=== St. Louis Integration - Production Configuration Manager ==="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() { echo -e "${GREEN}[CONFIG]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }
print_info() { echo -e "${BLUE}[INFO]${NC} $1"; }

# Configuration
DRUPAL_ROOT="${1:-$(pwd)/drupal/web}"
if [ ! -d "$DRUPAL_ROOT" ]; then
    DRUPAL_ROOT="/var/www/html/drupal/web"
fi

if [ ! -f "$DRUPAL_ROOT/index.php" ]; then
    print_error "Invalid Drupal root: $DRUPAL_ROOT"
    exit 1
fi

cd "$DRUPAL_ROOT"

# Check for Drush
if [ -f "../vendor/bin/drush" ]; then
    DRUSH="../vendor/bin/drush"
elif command -v drush &> /dev/null; then
    DRUSH="drush"
else
    print_error "Drush not found!"
    exit 1
fi

print_info "Using Drupal root: $DRUPAL_ROOT"
print_info "Using Drush: $DRUSH"

# Main menu
show_menu() {
    echo ""
    echo "Production Configuration Management"
    echo "=================================="
    echo "1. Export Production Configuration"
    echo "2. Import Development Configuration"
    echo "3. Backup Current Configuration"
    echo "4. Apply Production Security Settings"
    echo "5. Restore from Backup"
    echo "6. Show Configuration Status"
    echo "7. Exit"
    echo ""
}

# Export production configuration
export_production_config() {
    print_status "Exporting production configuration..."
    
    EXPORT_DIR="../config/production/$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$EXPORT_DIR"
    
    # Export all configuration
    $DRUSH config:export --destination="$EXPORT_DIR" --yes
    
    # Export specific production settings separately
    PROD_CONFIGS=(
        "system.site"
        "system.mail"  
        "system.performance"
        "user.settings"
        "user.mail"
        "contact.form.feedback"
        "system.logging"
        "automated_cron.settings"
        "google_analytics.settings"
        "metatag.metatag_defaults.global"
    )
    
    mkdir -p "$EXPORT_DIR/production_specific"
    
    for config in "${PROD_CONFIGS[@]}"; do
        if $DRUSH config:get "$config" >/dev/null 2>&1; then
            print_info "Exporting: $config"
            $DRUSH config:get "$config" --format=yaml > "$EXPORT_DIR/production_specific/${config}.yml"
        fi
    done
    
    # Create metadata file
    cat > "$EXPORT_DIR/export_metadata.txt" << EOF
Export Date: $(date)
Drupal Version: $($DRUSH status drupal-version --format=string)
Environment: Production
Exported by: $(whoami)
Server: $(hostname)
EOF
    
    print_status "Configuration exported to: $EXPORT_DIR"
}

# Import development configuration with production preservation
import_development_config() {
    print_status "Importing development configuration (preserving production settings)..."
    
    # First, backup current production settings
    BACKUP_DIR="../config/backups/pre_dev_import_$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    
    PRESERVE_CONFIGS=(
        "system.site"
        "system.mail"
        "system.performance" 
        "user.settings"
        "system.logging"
        "contact.form.feedback"
        "google_analytics.settings"
        "metatag.metatag_defaults.global"
    )
    
    print_info "Backing up production-specific configurations..."
    for config in "${PRESERVE_CONFIGS[@]}"; do
        if $DRUSH config:get "$config" >/dev/null 2>&1; then
            $DRUSH config:get "$config" --format=yaml > "$BACKUP_DIR/${config}.yml"
        fi
    done
    
    # Import development configuration
    print_info "Importing development configuration..."
    if [ -d "../config/development" ]; then
        $DRUSH config:import --source="../config/development" --yes
    else
        print_error "Development configuration directory not found"
        return 1
    fi
    
    # Restore production-specific settings
    print_info "Restoring production-specific settings..."
    for config_file in "$BACKUP_DIR"/*.yml; do
        if [ -f "$config_file" ]; then
            config_name=$(basename "$config_file" .yml)
            print_info "Restoring: $config_name"
            $DRUSH config:set "$config_name" --input-format=yaml < "$config_file" --yes
        fi
    done
    
    # Apply production security settings
    apply_production_security
    
    # Clear caches
    $DRUSH cache:rebuild
    
    print_status "Development configuration imported with production settings preserved"
}

# Apply production security settings
apply_production_security() {
    print_status "Applying production security settings..."
    
    # Disable error display
    $DRUSH config:set system.logging error_level hide --yes
    
    # Restrict user registration
    $DRUSH config:set user.settings register admin_only --yes
    
    # Configure session settings for security
    $DRUSH config:set system.session cookie_lifetime 86400 --yes
    
    # Enable flood control
    $DRUSH config:set user.flood ip_limit 50 --yes
    $DRUSH config:set user.flood ip_window 3600 --yes
    $DRUSH config:set user.flood user_limit 5 --yes
    $DRUSH config:set user.flood user_window 21600 --yes
    
    # Performance settings for production
    $DRUSH config:set system.performance cache.page.max_age 3600 --yes
    $DRUSH config:set system.performance css.preprocess true --yes
    $DRUSH config:set system.performance js.preprocess true --yes
    
    # Disable development modules if enabled
    $DRUSH pm:uninstall devel devel_generate webprofiler kint --yes 2>/dev/null || true
    
    print_status "Production security settings applied"
}

# Backup current configuration
backup_current_config() {
    print_status "Creating configuration backup..."
    
    BACKUP_DIR="../config/backups/$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    
    # Export configuration
    $DRUSH config:export --destination="$BACKUP_DIR" --yes
    
    # Export database
    $DRUSH sql:dump --result-file="$BACKUP_DIR/database.sql"
    
    # Create restore script
    cat > "$BACKUP_DIR/restore.sh" << EOF
#!/bin/bash
# Configuration Restoration Script
# Created: $(date)

DRUSH="../vendor/bin/drush"
if [ ! -f "\$DRUSH" ]; then
    DRUSH="drush"
fi

echo "Restoring configuration from $(date)..."

# Import configuration
\$DRUSH config:import --source="\$(dirname "\$0")" --yes

# Import database (uncomment if needed)
# \$DRUSH sql:cli < "\$(dirname "\$0")/database.sql"

# Clear caches
\$DRUSH cache:rebuild

echo "Restoration complete!"
EOF
    
    chmod +x "$BACKUP_DIR/restore.sh"
    
    print_status "Backup created: $BACKUP_DIR"
}

# Show configuration status
show_config_status() {
    print_status "Configuration Status Report"
    echo "=========================="
    
    # Environment detection
    if [[ "$PWD" == *"/var/www"* ]] || [[ "$PWD" == *"production"* ]]; then
        echo "Environment: Production"
    else
        echo "Environment: Development"
    fi
    
    # Drupal status
    echo "Drupal Version: $($DRUSH status drupal-version --format=string 2>/dev/null || echo 'Unknown')"
    echo "Database Status: $($DRUSH status bootstrap --format=string 2>/dev/null || echo 'Unknown')"
    
    # Security settings
    echo ""
    echo "Security Configuration:"
    echo "======================"
    
    ERROR_LEVEL=$($DRUSH config:get system.logging error_level --format=string 2>/dev/null || echo "unknown")
    echo "Error Display: $ERROR_LEVEL"
    
    USER_REGISTER=$($DRUSH config:get user.settings register --format=string 2>/dev/null || echo "unknown")
    echo "User Registration: $USER_REGISTER"
    
    CSS_PREPROCESS=$($DRUSH config:get system.performance css.preprocess --format=string 2>/dev/null || echo "unknown")
    echo "CSS Preprocessing: $CSS_PREPROCESS"
    
    JS_PREPROCESS=$($DRUSH config:get system.performance js.preprocess --format=string 2>/dev/null || echo "unknown")
    echo "JS Preprocessing: $JS_PREPROCESS"
    
    # Module status
    echo ""
    echo "Development Modules Status:"
    echo "========================="
    
    if $DRUSH pm:list --status=enabled --format=csv 2>/dev/null | grep -q "devel"; then
        echo "❌ Devel modules ENABLED (not suitable for production)"
    else
        echo "✅ Devel modules disabled"
    fi
    
    # Content statistics
    echo ""
    echo "Content Statistics:"
    echo "=================="
    
    TOTAL_NODES=$($DRUSH sql:query "SELECT COUNT(*) FROM node" 2>/dev/null | tail -1)
    PUBLISHED_NODES=$($DRUSH sql:query "SELECT COUNT(*) FROM node_field_data WHERE status = 1" 2>/dev/null | tail -1)
    TOTAL_USERS=$($DRUSH sql:query "SELECT COUNT(*) FROM users" 2>/dev/null | tail -1)
    
    echo "Total Nodes: $TOTAL_NODES"
    echo "Published Nodes: $PUBLISHED_NODES"  
    echo "Total Users: $TOTAL_USERS"
}

# Restore from backup
restore_from_backup() {
    print_status "Available backups:"
    
    BACKUP_BASE="../config/backups"
    if [ ! -d "$BACKUP_BASE" ]; then
        print_error "No backups directory found"
        return 1
    fi
    
    # List available backups
    backups=($(ls -1t "$BACKUP_BASE" 2>/dev/null || echo ""))
    
    if [ ${#backups[@]} -eq 0 ]; then
        print_error "No backups available"
        return 1
    fi
    
    echo "Available backups:"
    for i in "${!backups[@]}"; do
        echo "$((i+1)). ${backups[i]}"
    done
    
    echo -n "Select backup to restore (1-${#backups[@]}): "
    read -r selection
    
    if [[ "$selection" =~ ^[0-9]+$ ]] && [ "$selection" -ge 1 ] && [ "$selection" -le "${#backups[@]}" ]; then
        backup_dir="${backups[$((selection-1))]}"
        full_path="$BACKUP_BASE/$backup_dir"
        
        print_warning "This will replace current configuration with backup from $backup_dir"
        echo -n "Are you sure? (y/N): "
        read -r confirm
        
        if [[ "$confirm" =~ ^[yY]$ ]]; then
            print_status "Restoring from backup: $backup_dir"
            
            if [ -f "$full_path/restore.sh" ]; then
                bash "$full_path/restore.sh"
            else
                $DRUSH config:import --source="$full_path" --yes
                $DRUSH cache:rebuild
            fi
            
            print_status "Restore completed"
        else
            print_info "Restore cancelled"
        fi
    else
        print_error "Invalid selection"
    fi
}

# Main execution
while true; do
    show_menu
    echo -n "Select option (1-7): "
    read -r choice
    
    case $choice in
        1) export_production_config ;;
        2) import_development_config ;;
        3) backup_current_config ;;
        4) apply_production_security ;;
        5) restore_from_backup ;;
        6) show_config_status ;;
        7) print_info "Goodbye!"; exit 0 ;;
        *) print_error "Invalid option. Please try again." ;;
    esac
    
    echo ""
    echo -n "Press Enter to continue..."
    read -r
done