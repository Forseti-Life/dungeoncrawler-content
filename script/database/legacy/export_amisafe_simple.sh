#!/bin/bash

# Simplified AmISafe Database Export Script
# Uses mysqldump for reliable table and data export

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/DB Backup"
DRUPAL_ROOT="/workspaces/stlouisintegration.com/sites/theoryofconspiracies"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "🚀 AmISafe Database Export (Simplified) Starting..."
echo "📂 Backup Directory: $BACKUP_DIR"
echo "⏰ Timestamp: $TIMESTAMP"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Change to Drupal root for drush commands
cd "$DRUPAL_ROOT"

echo ""
echo "📊 Getting database connection info..."

# Use drush to get database credentials and create mysqldump commands
echo "🔍 AmISafe tables and record counts:"

# Get record counts first
echo "📊 amisafe_raw_incidents: $(./vendor/bin/drush sql:query "SELECT COUNT(*) FROM amisafe_raw_incidents;" | tail -n 1) records"
echo "📊 amisafe_clean_incidents: $(./vendor/bin/drush sql:query "SELECT COUNT(*) FROM amisafe_clean_incidents;" | tail -n 1) records"  
echo "📊 amisafe_h3_aggregated: $(./vendor/bin/drush sql:query "SELECT COUNT(*) FROM amisafe_h3_aggregated;" | tail -n 1) records"

echo ""
echo "🗜️ Exporting tables using drush sql:dump..."

# Export each table separately using drush sql:dump
TABLES=("amisafe_raw_incidents" "amisafe_clean_incidents" "amisafe_h3_aggregated")

for table in "${TABLES[@]}"; do
    echo "📤 Exporting $table..."
    
    # Export table structure and data
    ./vendor/bin/drush sql:dump --tables-list="$table" > "$BACKUP_DIR/${table}_complete.sql"
    
    if [ -f "$BACKUP_DIR/${table}_complete.sql" ] && [ -s "$BACKUP_DIR/${table}_complete.sql" ]; then
        echo "  ✅ $table exported successfully"
        
        # Get file size for verification
        file_size=$(du -h "$BACKUP_DIR/${table}_complete.sql" | cut -f1)
        echo "  📊 File size: $file_size"
    else
        echo "  ❌ $table export failed"
    fi
    echo ""
done

# Create a comprehensive database info file
echo "📋 Creating database information file..."
cat > "$BACKUP_DIR/database_info.txt" << EOF
AmISafe Database Export Information (Simplified)
===============================================
Export Date: $(date)
Database: theoryofconspiracies_dev
Drupal Site: theoryofconspiracies
Export Location: $BACKUP_DIR
Export Method: drush sql:dump

Tables Exported:
================
EOF

# Add record counts and file info
for table in "${TABLES[@]}"; do
    if [ -f "$BACKUP_DIR/${table}_complete.sql" ]; then
        record_count=$(./vendor/bin/drush sql:query "SELECT COUNT(*) FROM $table;" | tail -n 1)
        file_size=$(du -h "$BACKUP_DIR/${table}_complete.sql" | cut -f1)
        echo "$table: $record_count records (${file_size} file)" >> "$BACKUP_DIR/database_info.txt"
    fi
done

cat >> "$BACKUP_DIR/database_info.txt" << EOF

File Structure:
==============
{table}_complete.sql - Complete table dump (structure + data)

Import Instructions:
===================
Use the import_amisafe_data.sh script:
./scripts/database/import_amisafe_data.sh

Or manually import each table:
mysql theoryofconspiracies_dev < {table}_complete.sql

Files Created:
=============
EOF

# List all created files
ls -la "$BACKUP_DIR"/*.sql >> "$BACKUP_DIR/database_info.txt" 2>/dev/null || echo "No SQL files found" >> "$BACKUP_DIR/database_info.txt"

echo "🎉 AmISafe Database Export Complete!"
echo ""
echo "📁 Files created in: $BACKUP_DIR"
ls -la "$BACKUP_DIR"
echo ""
echo "📋 Check database_info.txt for detailed information"
echo "🔄 To import this data later, use: ./scripts/database/import_amisafe_data.sh"
echo ""