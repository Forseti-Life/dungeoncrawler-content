#!/bin/bash
# Import backup configurations into Drupal

set -e

DRUPAL_ROOT="/workspaces/stlouisintegration.com/drupal"
DRUSH="$DRUPAL_ROOT/vendor/bin/drush"

cd "$DRUPAL_ROOT"

echo "=== Importing Backup and Migrate Configurations ==="

# Import the configuration changes
echo "Importing new backup configurations..."
$DRUSH config:import -y

# Clear caches
echo "Clearing caches..."
$DRUSH cache:rebuild

# Show the imported configurations
echo ""
echo "✅ Backup configurations imported successfully!"
echo ""
echo "=== Backup Schedules ==="
$DRUSH config:get backup_migrate.backup_migrate_schedule.daily_backup --format=yaml || echo "Daily backup config not found"
echo ""
$DRUSH config:get backup_migrate.backup_migrate_schedule.weekly_backup --format=yaml || echo "Weekly backup config not found"
echo ""
echo "=== Backup Destinations ==="
$DRUSH config:get backup_migrate.backup_migrate_destination.daily_local_backup --format=yaml || echo "Daily destination config not found"
echo ""
$DRUSH config:get backup_migrate.backup_migrate_destination.weekly_local_backup --format=yaml || echo "Weekly destination config not found"
echo ""
echo "🌐 Check the Backup & Migrate schedules at:"
echo "   https://curly-space-winner-67wvv944pr2rqj-80.app.github.dev/admin/config/development/backup_migrate/schedule"