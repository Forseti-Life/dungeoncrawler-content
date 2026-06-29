#!/bin/bash

# Fix Private Files Path Configuration in Production
# This script sets the private files path via drush to enable private:// stream wrapper

set -e

echo "=== FIXING PRIVATE FILES PATH CONFIGURATION ==="

DRUPAL_ROOT="/var/www/html/stlouisintegration"
SITE_URI="stlouisintegration.com"
PRIVATE_FILES_PATH="/var/private/stlouisintegration"

# Change to Drupal root
cd "$DRUPAL_ROOT"

echo "1. Checking current private files path configuration..."
current_path=$(sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:get system.file private_path --format=string 2>/dev/null || echo "Not set")
echo "Current private files path: $current_path"

echo ""
echo "2. Setting private files path to: $PRIVATE_FILES_PATH"
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set system.file private_path "$PRIVATE_FILES_PATH" -y

echo ""
echo "3. Ensuring private files directory exists with proper permissions..."
sudo mkdir -p "$PRIVATE_FILES_PATH"
sudo mkdir -p "$PRIVATE_FILES_PATH/backup_migrate"
sudo chown -R www-data:www-data "$PRIVATE_FILES_PATH"
sudo chmod -R 755 "$PRIVATE_FILES_PATH"

echo ""
echo "4. Clearing cache to activate private:// stream wrapper..."
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" cache:rebuild

echo ""
echo "5. Verifying private files path configuration..."
new_path=$(sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:get system.file private_path --format=string)
echo "New private files path: $new_path"

echo ""
echo "6. Testing private:// stream wrapper..."
if sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" eval "echo file_exists('private://') ? 'Private files stream wrapper is working' : 'Private files stream wrapper not working';" | grep -q "working"; then
    echo "✅ Private files stream wrapper is working"
else
    echo "❌ Private files stream wrapper is not working"
fi

echo ""
echo "=== PRIVATE FILES PATH CONFIGURATION COMPLETE ==="
echo "✅ Private files path: $PRIVATE_FILES_PATH"
echo "✅ Backup directory: $PRIVATE_FILES_PATH/backup_migrate"
echo "✅ Stream wrapper: private:// should now work"
echo ""
echo "🌐 Test manual backup at: https://stlouisintegration.com/admin/config/development/backup_migrate/advanced"
echo "Select 'Private Files Directory' as destination to test the fix."