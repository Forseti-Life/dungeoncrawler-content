#!/bin/bash

# Import Backup & Migrate Configuration - Production Deployment
# This script imports backup system configurations on production server

echo "=== BACKUP & MIGRATE CONFIGURATION IMPORT ==="
echo "Production server: stlouisintegration.com"
echo "Timestamp: $(date)"
echo ""

# Set production paths
DRUPAL_ROOT="/var/www/html/stlouisintegration"
SITE_URI="stlouisintegration.com"

# Change to production Drupal directory
cd $DRUPAL_ROOT

echo "1. Checking current configuration status..."
./vendor/bin/drush --uri=$SITE_URI config:status | grep backup_migrate || echo "No backup_migrate configurations found in active config"

echo ""
echo "2. Importing backup configurations from sync directory..."
./vendor/bin/drush --uri=$SITE_URI config:import --partial --source=config/sync

echo ""
echo "3. Clearing cache to refresh backup system..."
./vendor/bin/drush --uri=$SITE_URI cache:rebuild

echo ""
echo "4. Verifying backup destinations..."
./vendor/bin/drush --uri=$SITE_URI backup_migrate:destinations || echo "Error: Backup migrate commands not available"

echo ""
echo "5. Verifying backup schedules..."
./vendor/bin/drush --uri=$SITE_URI backup_migrate:schedules || echo "Error: Backup migrate commands not available"

echo ""
echo "6. Creating backup directories if they don't exist..."
sudo mkdir -p /var/backups/stlouisintegration/{daily,weekly}
sudo mkdir -p /var/private/stlouisintegration/backup_migrate
sudo chown -R www-data:www-data /var/backups/stlouisintegration
sudo chown -R www-data:www-data /var/private/stlouisintegration
sudo chmod -R 755 /var/backups/stlouisintegration
sudo chmod -R 755 /var/private/stlouisintegration

echo ""
echo "=== BACKUP CONFIGURATION IMPORT COMPLETE ==="
echo "Check production admin interface: /admin/config/development/backup_migrate"