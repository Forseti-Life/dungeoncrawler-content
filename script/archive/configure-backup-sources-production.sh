#!/bin/bash

# Production Backup Sources Configuration Script
# This configures the backup sources that are missing from production

set -e

echo "=== CONFIGURING BACKUP SOURCES FOR PRODUCTION ==="

DRUPAL_ROOT="/var/www/html/stlouisintegration"
SITE_URI="stlouisintegration.com"

# Change to Drupal root
cd "$DRUPAL_ROOT"

echo "1. Configuring Default Drupal Database source..."
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db uuid null -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db langcode en -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db status true -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db id default_db -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db label "Default Drupal Database" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db type DefaultDB -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.default_db config.name "Default Drupal Database" -y || true

echo "2. Configuring Entire Site source..."
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site uuid null -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site langcode en -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site status true -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site id entire_site -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site label "Entire Site (do not use)" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site type EntireSite -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.entire_site config.name "Entire Site (do not use)" -y || true

echo "3. Configuring Public Files Directory source..."
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files uuid null -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files langcode en -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files status true -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files id public_files -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files label "Public Files Directory" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files type DrupalFiles -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files config.name "Public Files Directory" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.public_files config.directory "public://" -y || true

echo "4. Configuring Private Files Directory source..."
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files uuid null -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files langcode en -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files status true -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files id private_files -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files label "Private Files Directory" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files type DrupalFiles -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files config.name "Private Files Directory" -y || true
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" config:set backup_migrate.backup_migrate_source.private_files config.directory "private://" -y || true

echo "5. Clearing cache..."
sudo -u www-data ./vendor/bin/drush --uri="$SITE_URI" cache:rebuild

echo ""
echo "=== BACKUP SOURCES CONFIGURATION COMPLETE ==="
echo "✅ Default Drupal Database - for database backups"
echo "✅ Entire Site - for full site backups (DB + files)"
echo "✅ Public Files Directory - for public files backup"
echo "✅ Private Files Directory - for private files backup"
echo ""
echo "🌐 Test at: https://stlouisintegration.com/admin/config/development/backup_migrate/advanced"
echo "The 'Backup Source' dropdown should now show all 4 options."