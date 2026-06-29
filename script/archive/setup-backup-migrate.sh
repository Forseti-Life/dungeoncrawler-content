#!/bin/bash
# Configure Backup and Migrate module with proper destinations and schedules

set -e

DRUPAL_ROOT="/workspaces/stlouisintegration.com/drupal"
DRUSH="$DRUPAL_ROOT/vendor/bin/drush"

cd "$DRUPAL_ROOT"

echo "=== Configuring Backup and Migrate Module ==="

# Ensure module is enabled
echo "Ensuring Backup and Migrate module is enabled..."
$DRUSH en backup_migrate -y

# Create backup directories (development paths for testing)
echo "Creating backup directories..."
mkdir -p /tmp/backups/stlouisintegration/daily
mkdir -p /tmp/backups/stlouisintegration/weekly

# Configure backup destinations via drush
echo "Creating backup destinations..."

# Daily backup destination
$DRUSH config:set backup_migrate.backup_migrate_destination.daily_local_backup id daily_local_backup --yes
$DRUSH config:set backup_migrate.backup_migrate_destination.daily_local_backup label "Daily Local Backups" --yes
$DRUSH config:set backup_migrate.backup_migrate_destination.daily_local_backup type Directory --yes
$DRUSH config:set backup_migrate.backup_migrate_destination.daily_local_backup settings.directory "/tmp/backups/stlouisintegration/daily" --yes

# Weekly backup destination  
$DRUSH config:set backup_migrate.backup_migrate_destination.weekly_local_backup id weekly_local_backup --yes
$DRUSH config:set backup_migrate.backup_migrate_destination.weekly_local_backup label "Weekly Local Backups" --yes
$DRUSH config:set backup_migrate.backup_migrate_destination.weekly_local_backup type Directory --yes
$DRUSH config:set backup_migrate.backup_migrate_destination.weekly_local_backup settings.directory "/tmp/backups/stlouisintegration/weekly" --yes

# Create backup schedules
echo "Creating backup schedules..."

# Daily database backup schedule
$DRUSH config:set backup_migrate.backup_migrate_schedule.daily_backup id daily_backup --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.daily_backup label "Daily Database Backup" --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.daily_backup enabled true --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.daily_backup keep 7 --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.daily_backup period "86400" --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.daily_backup source_id default_db --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.daily_backup destination_id daily_local_backup --yes

# Weekly full site backup schedule
$DRUSH config:set backup_migrate.backup_migrate_schedule.weekly_backup id weekly_backup --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.weekly_backup label "Weekly Full Site Backup" --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.weekly_backup enabled true --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.weekly_backup keep 20 --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.weekly_backup period "604800" --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.weekly_backup source_id entire_site --yes
$DRUSH config:set backup_migrate.backup_migrate_schedule.weekly_backup destination_id weekly_local_backup --yes

# Clear caches to ensure configurations are loaded
echo "Clearing caches..."
$DRUSH cache:rebuild

# Export the configuration so it gets committed to the repository
echo "Exporting configuration..."
$DRUSH config:export -y

echo "✅ Backup and Migrate module configured successfully!"
echo ""
echo "=== Configuration Summary ==="
echo "Daily Backup Schedule:"
echo "  - Source: Default Database"
echo "  - Destination: /tmp/backups/stlouisintegration/daily"
echo "  - Retention: 7 days"
echo "  - Period: Daily (86400 seconds)"
echo ""
echo "Weekly Backup Schedule:"
echo "  - Source: Entire Site"
echo "  - Destination: /tmp/backups/stlouisintegration/weekly"
echo "  - Retention: 20 weeks"
echo "  - Period: Weekly (604800 seconds)"
echo ""
echo "🌐 View schedules at: /admin/config/development/backup_migrate/schedule"
echo "🌐 View destinations at: /admin/config/development/backup_migrate/destination"