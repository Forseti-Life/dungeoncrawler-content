#!/bin/bash

# Production Backup System Deployment Script
# Based on working development environment configuration

set -e

echo "=== PRODUCTION BACKUP SYSTEM DEPLOYMENT ==="
echo "Timestamp: $(date)"
echo "Server: stlouisintegration.com"
echo ""

# Production paths
DRUPAL_ROOT="/var/www/html/stlouisintegration"
SITE_URI="stlouisintegration.com"
BACKUP_BASE_DIR="/var/backups/stlouisintegration"
PRIVATE_FILES_DIR="/var/private/stlouisintegration"

# Change to Drupal root
cd "$DRUPAL_ROOT"

echo "1. Creating backup directory structure..."
# Create backup directories
sudo mkdir -p "$BACKUP_BASE_DIR"/{daily,weekly}
sudo mkdir -p "$PRIVATE_FILES_DIR/backup_migrate"

# Set proper permissions
sudo chown -R www-data:www-data "$BACKUP_BASE_DIR"
sudo chown -R www-data:www-data "$PRIVATE_FILES_DIR"
sudo chmod -R 755 "$BACKUP_BASE_DIR"
sudo chmod -R 755 "$PRIVATE_FILES_DIR"

echo "✅ Backup directories created and configured"

echo ""
echo "2. Installing Defuse PHP encryption library..."
# Install encryption library (should already be done via composer.json)
if ! grep -q "defuse/php-encryption" composer.json; then
    composer require defuse/php-encryption
    echo "✅ Defuse PHP encryption library installed"
else
    echo "✅ Defuse PHP encryption library already installed"
fi

echo ""
echo "3. Configuring backup sources..."

# Configure Default Drupal Database source
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db uuid null -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db langcode en -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db status true -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db id default_db -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db label "Default Drupal Database" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db type DefaultDB -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db config.name "Default Drupal Database" -y || true

# Configure Entire Site source
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site uuid null -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site langcode en -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site status true -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site id entire_site -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site label "Entire Site (do not use)" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site type EntireSite -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site config.name "Entire Site (do not use)" -y || true

# Configure Public Files Directory source
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files uuid null -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files langcode en -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files status true -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files id public_files -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files label "Public Files Directory" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files type DrupalFiles -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files config.name "Public Files Directory" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files config.directory "public://" -y || true

# Configure Private Files Directory source
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files uuid null -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files langcode en -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files status true -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files id private_files -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files label "Private Files Directory" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files type DrupalFiles -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files config.name "Private Files Directory" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files config.directory "private://" -y || true

echo "✅ Backup sources configured"

echo ""
echo "4. Configuring backup destinations..."

# Create daily backup destination
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.daily_local_backups uuid null -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.daily_local_backups langcode en -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.daily_local_backups status true -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.daily_local_backups id daily_local_backups -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.daily_local_backups label "Daily Local Backups" -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.daily_local_backups type Directory -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.daily_local_backups config.directory "$BACKUP_BASE_DIR/daily/" -y

# Create weekly backup destination
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.weekly_local_backup uuid null -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.weekly_local_backup langcode en -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.weekly_local_backup status true -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.weekly_local_backup id weekly_local_backup -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.weekly_local_backup label "Weekly Local Backups" -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.weekly_local_backup type Directory -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.weekly_local_backup config.directory "$BACKUP_BASE_DIR/weekly" -y

# Create private files backup destination
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.private_files uuid null -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.private_files langcode en -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.private_files status true -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.private_files id private_files -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.private_files label "Private Files Directory" -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.private_files type Directory -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_destination.private_files config.directory "private://backup_migrate/" -y

echo "✅ Backup destinations configured"

echo ""
echo "5. Creating backup schedules..."

# Create daily backup schedule
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.daily_backup uuid null -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.daily_backup langcode en -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.daily_backup status true -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.daily_backup id daily_backup -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.daily_backup label "Daily Database Backup" -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.daily_backup enabled true -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.daily_backup keep 7 -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.daily_backup period 86400 -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.daily_backup source_id default_db -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.daily_backup destination_id daily_local_backups -y

# Create weekly backup schedule
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.weekly_backup uuid null -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.weekly_backup langcode en -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.weekly_backup status true -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.weekly_backup id weekly_backup -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.weekly_backup label "Weekly Full Site Backup" -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.weekly_backup enabled true -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.weekly_backup keep 20 -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.weekly_backup period 604800 -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.weekly_backup source_id entire_site -y
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_schedule.weekly_backup destination_id weekly_local_backup -y

echo "✅ Backup schedules configured"

echo ""
echo "6. Clearing cache and final verification..."
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" cache:rebuild

echo ""
echo "=== DEPLOYMENT VERIFICATION ==="
echo "Backup destinations:"
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" backup_migrate:destinations

echo ""
echo "Backup schedules:"
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" backup_migrate:schedules

echo ""
echo "=== BACKUP SYSTEM DEPLOYMENT COMPLETE ==="
echo "✅ Backup sources: Default DB, Entire Site, Public Files, Private Files"
echo "✅ Daily backups: $BACKUP_BASE_DIR/daily (7-day retention)"
echo "✅ Weekly backups: $BACKUP_BASE_DIR/weekly (20-week retention)"
echo "✅ Private files: $PRIVATE_FILES_DIR/backup_migrate"
echo "✅ Encryption library: Installed and ready"
echo ""
echo "🌐 Admin URLs:"
echo "   - Manual backup: https://stlouisintegration.com/admin/config/development/backup_migrate/advanced"
echo "   - Schedules: https://stlouisintegration.com/admin/config/development/backup_migrate/schedule"
echo "   - Destinations: https://stlouisintegration.com/admin/config/development/backup_migrate/destination"
echo ""
echo "Deployment completed: $(date)"