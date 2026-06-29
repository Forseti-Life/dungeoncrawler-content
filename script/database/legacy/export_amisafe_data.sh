#!/bin/bash

# AmISafe Database Export Script
# Exports all AmISafe tables and their data for backup/import purposes

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/DB Backup"
DRUPAL_ROOT="/workspaces/stlouisintegration.com/sites/theoryofconspiracies"
DATABASE_NAME="theoryofconspiracies_dev"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "🚀 AmISafe Database Export Starting..."
echo "📂 Backup Directory: $BACKUP_DIR"
echo "🗄️ Database: $DATABASE_NAME"
echo "⏰ Timestamp: $TIMESTAMP"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Change to Drupal root for drush commands
cd "$DRUPAL_ROOT"

echo ""
echo "📊 Checking AmISafe table status..."

# Get table information
echo "🔍 AmISafe tables found:"
./vendor/bin/drush sql:query "SHOW TABLES LIKE '%amisafe%';" | while read table; do
    if [[ "$table" != "Tables_in_${DATABASE_NAME}_%amisafe%" ]]; then
        count=$(./vendor/bin/drush sql:query "SELECT COUNT(*) as count FROM $table;" | tail -n 1)
        echo "  ✅ $table ($count records)"
    fi
done

echo ""
echo "🗜️ Exporting table structures and data..."

# Export each AmISafe table
TABLES=("amisafe_raw_incidents" "amisafe_clean_incidents" "amisafe_h3_aggregated")

for table in "${TABLES[@]}"; do
    echo "📤 Exporting $table..."
    
    # Check if table exists
    table_exists=$(./vendor/bin/drush sql:query "SHOW TABLES LIKE '$table';" | wc -l)
    
    if [ "$table_exists" -gt 1 ]; then
        # Get record count
        record_count=$(./vendor/bin/drush sql:query "SELECT COUNT(*) FROM $table;" | tail -n 1)
        echo "  📊 Records: $record_count"
        
        # Export structure (CREATE TABLE statement)
        echo "  🏗️ Exporting structure..."
        ./vendor/bin/drush sql:query "SHOW CREATE TABLE $table;" | tail -n +2 | awk -F'\t' '{print $2}' > "$BACKUP_DIR/${table}_structure.sql"
        echo ";" >> "$BACKUP_DIR/${table}_structure.sql"
        
        # Export data (if table has records)
        if [ "$record_count" -gt 0 ]; then
            echo "  📦 Exporting data..."
            
            # For large tables, use mysqldump for better performance
            if [ "$record_count" -gt 100000 ]; then
                echo "  ⚡ Large table detected, using optimized export..."
                ./vendor/bin/drush sql:dump --tables="$table" --data-only > "$BACKUP_DIR/${table}_data.sql"
            else
                # Use INSERT statements for smaller tables
                ./vendor/bin/drush sql:query "SELECT * FROM $table;" --result-file="$BACKUP_DIR/${table}_data.csv"
                
                # Generate INSERT statements from CSV (if needed for SQL format)
                # This is a placeholder - we'll use CSV for simplicity
                echo "  💾 Data exported to CSV format"
            fi
        else
            echo "  ⚠️ Table is empty, skipping data export"
            touch "$BACKUP_DIR/${table}_data.empty"
        fi
        
        echo "  ✅ $table export complete"
    else
        echo "  ❌ Table $table not found"
    fi
    echo ""
done

# Create a comprehensive database info file
echo "📋 Creating database information file..."
cat > "$BACKUP_DIR/database_info.txt" << EOF
AmISafe Database Export Information
==================================
Export Date: $(date)
Database: $DATABASE_NAME
Drupal Site: theoryofconspiracies
Export Location: $BACKUP_DIR

Tables Exported:
================
EOF

for table in "${TABLES[@]}"; do
    if [ -f "$BACKUP_DIR/${table}_structure.sql" ]; then
        record_count=$(./vendor/bin/drush sql:query "SELECT COUNT(*) FROM $table;" | tail -n 1 2>/dev/null || echo "0")
        echo "$table: $record_count records" >> "$BACKUP_DIR/database_info.txt"
    fi
done

cat >> "$BACKUP_DIR/database_info.txt" << EOF

File Structure:
==============
{table}_structure.sql - CREATE TABLE statements
{table}_data.sql - INSERT statements (for large tables)
{table}_data.csv - CSV data export (for smaller tables)
{table}_data.empty - Marker for empty tables

Import Instructions:
===================
Use the setup_database.sh script to import this data:
./scripts/database/setup_database.sh

Or manually import each table:
1. Run structure files first: mysql < {table}_structure.sql
2. Run data files second: mysql < {table}_data.sql
EOF

# Create file listing
echo "" >> "$BACKUP_DIR/database_info.txt"
echo "Exported Files:" >> "$BACKUP_DIR/database_info.txt"
echo "==============" >> "$BACKUP_DIR/database_info.txt"
ls -la "$BACKUP_DIR" >> "$BACKUP_DIR/database_info.txt"

echo "🎉 AmISafe Database Export Complete!"
echo ""
echo "📁 Files created in: $BACKUP_DIR"
echo "📋 Check database_info.txt for detailed information"
echo ""
echo "🔄 To import this data later, use:"
echo "   ./scripts/database/setup_database.sh"
echo ""