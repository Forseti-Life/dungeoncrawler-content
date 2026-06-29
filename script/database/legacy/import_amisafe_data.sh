#!/bin/bash

# AmISafe Database Import Script  
# Imports AmISafe tables and data from backup files

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/DB Backup"
DRUPAL_ROOT="/workspaces/stlouisintegration.com/sites/theoryofconspiracies"
DATABASE_NAME="theoryofconspiracies_dev"

echo "🚀 AmISafe Database Import Starting..."
echo "📂 Backup Directory: $BACKUP_DIR"
echo "🗄️ Database: $DATABASE_NAME"

# Check if backup directory exists
if [ ! -d "$BACKUP_DIR" ]; then
    echo "❌ Backup directory not found: $BACKUP_DIR"
    echo "   Run export_amisafe_data.sh first to create backup files"
    exit 1
fi

# Check if database info file exists
if [ ! -f "$BACKUP_DIR/database_info.txt" ]; then
    echo "❌ Database info file not found"
    echo "   Backup directory may be incomplete"
    exit 1
fi

echo ""
echo "📋 Backup Information:"
head -n 10 "$BACKUP_DIR/database_info.txt"

# Change to Drupal root for drush commands
cd "$DRUPAL_ROOT"

echo ""
echo "🔍 Checking current database state..."

# Check if AmISafe tables already exist
existing_tables=$(./vendor/bin/drush sql:query "SHOW TABLES LIKE '%amisafe%';" | wc -l)
if [ "$existing_tables" -gt 1 ]; then
    echo "⚠️ AmISafe tables already exist in database"
    read -p "Do you want to DROP existing tables and recreate them? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "🗑️ Dropping existing AmISafe tables..."
        ./vendor/bin/drush sql:query "DROP TABLE IF EXISTS amisafe_raw_incidents;"
        ./vendor/bin/drush sql:query "DROP TABLE IF EXISTS amisafe_clean_incidents;"
        ./vendor/bin/drush sql:query "DROP TABLE IF EXISTS amisafe_h3_aggregated;"
        echo "✅ Existing tables dropped"
    else
        echo "❌ Import cancelled - existing tables preserved"
        exit 0
    fi
fi

echo ""
echo "🏗️ Creating table structures..."

# Import table structures
TABLES=("amisafe_raw_incidents" "amisafe_clean_incidents" "amisafe_h3_aggregated")

for table in "${TABLES[@]}"; do
    structure_file="$BACKUP_DIR/${table}_structure.sql"
    
    if [ -f "$structure_file" ]; then
        echo "📋 Creating $table..."
        ./vendor/bin/drush sql:query "$(cat "$structure_file")"
        echo "  ✅ $table structure created"
    else
        echo "  ❌ Structure file not found: $structure_file"
    fi
done

echo ""
echo "📦 Importing data..."

for table in "${TABLES[@]}"; do
    echo "📤 Importing data for $table..."
    
    # Check for different data file formats
    sql_data_file="$BACKUP_DIR/${table}_data.sql"
    csv_data_file="$BACKUP_DIR/${table}_data.csv"
    empty_marker="$BACKUP_DIR/${table}_data.empty"
    
    if [ -f "$empty_marker" ]; then
        echo "  ⚠️ Table $table was empty in backup, skipping data import"
    elif [ -f "$sql_data_file" ]; then
        echo "  📊 Importing from SQL file..."
        ./vendor/bin/drush sql:query "$(cat "$sql_data_file")"
        
        # Get imported record count
        record_count=$(./vendor/bin/drush sql:query "SELECT COUNT(*) as count FROM $table;" | tail -n 1)
        echo "  ✅ Imported $record_count records"
    elif [ -f "$csv_data_file" ]; then
        echo "  📊 CSV data file found, but SQL import method preferred"
        echo "  💡 Consider converting CSV to SQL format for automated import"
        echo "  📂 CSV file location: $csv_data_file"
        echo "  ⏭️ Skipping automatic import - manual import required"
    else
        echo "  ❌ No data file found for $table"
    fi
    echo ""
done

echo "🔍 Verifying import results..."
echo ""

# Verify tables and show record counts
echo "📊 Final table status:"
for table in "${TABLES[@]}"; do
    table_exists=$(./vendor/bin/drush sql:query "SHOW TABLES LIKE '$table';" | wc -l)
    
    if [ "$table_exists" -gt 1 ]; then
        record_count=$(./vendor/bin/drush sql:query "SELECT COUNT(*) as count FROM $table;" | tail -n 1)
        echo "  ✅ $table: $record_count records"
    else
        echo "  ❌ $table: NOT FOUND"
    fi
done

echo ""
echo "🎉 AmISafe Database Import Complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Verify data integrity with: ./vendor/bin/drush sql:query 'SELECT * FROM amisafe_h3_aggregated LIMIT 5;'"  
echo "   2. Test AmISafe module functionality"
echo "   3. Check crime map at: /amisafe/crime-map"
echo ""

# Create success marker
touch "$BACKUP_DIR/.import_completed_$(date +%Y%m%d_%H%M%S)"
echo "✅ Import completion marker created"