#!/bin/bash

# Pure SQL AmISafe Database Export Script
# Uses direct MySQL commands for reliable export

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/DB Backup"
DATABASE_NAME="theoryofconspiracies_dev"
MYSQL_USER="drupal_user"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
MYSQL_HOST="127.0.0.1"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "🚀 AmISafe Database Export (Pure SQL) Starting..."
echo "📂 Backup Directory: $BACKUP_DIR"
echo "🗄️ Database: $DATABASE_NAME"
echo "⏰ Timestamp: $TIMESTAMP"

if [ -z "${MYSQL_PASSWORD}" ]; then
    echo "ERROR: MYSQL_PASSWORD must be set in the environment." >&2
    exit 1
fi

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

echo ""
echo "🔍 Testing database connection..."

# Test database connection
echo "   Testing connection as $MYSQL_USER@$MYSQL_HOST"
if ! mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -e "USE $DATABASE_NAME;" 2>/dev/null; then
    echo "❌ Cannot connect to database $DATABASE_NAME"
    echo "   Make sure MySQL is running and credentials are correct"
    exit 1
fi

echo "✅ Database connection successful"

echo ""
echo "📊 Getting table information..."

# AmISafe tables to export
TABLES=("amisafe_raw_incidents" "amisafe_clean_incidents" "amisafe_h3_aggregated")

# Check tables exist and get record counts
for table in "${TABLES[@]}"; do
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -e "SELECT 1 FROM $DATABASE_NAME.$table LIMIT 1;" &>/dev/null; then
        record_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -se "SELECT COUNT(*) FROM $DATABASE_NAME.$table;")
        echo "📊 $table: $record_count records"
    else
        echo "❌ Table $table not found"
        exit 1
    fi
done

echo ""
echo "🗜️ Exporting tables using mysqldump..."

# Export each table
for table in "${TABLES[@]}"; do
    echo "📤 Exporting $table..."
    
    # Use mysqldump for reliable export (basic options for limited privileges)
    mysqldump -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" \
        --single-transaction \
        --add-drop-table \
        --extended-insert \
        --lock-tables=false \
        --no-tablespaces \
        "$DATABASE_NAME" "$table" > "$BACKUP_DIR/${table}_complete.sql"
    
    if [ -f "$BACKUP_DIR/${table}_complete.sql" ] && [ -s "$BACKUP_DIR/${table}_complete.sql" ]; then
        file_size=$(du -h "$BACKUP_DIR/${table}_complete.sql" | cut -f1)
        record_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -se "SELECT COUNT(*) FROM $DATABASE_NAME.$table;")
        echo "  ✅ $table exported: $record_count records ($file_size)"
    else
        echo "  ❌ $table export failed"
        exit 1
    fi
done

# Create database information file
echo ""
echo "📋 Creating database information file..."

cat > "$BACKUP_DIR/database_info.txt" << EOF
AmISafe Database Export Information (Pure SQL)
==============================================
Export Date: $(date)
Database: $DATABASE_NAME
MySQL User: $MYSQL_USER
Export Location: $BACKUP_DIR
Export Method: mysqldump

Tables Exported:
================
EOF

# Add detailed table information
for table in "${TABLES[@]}"; do
    if [ -f "$BACKUP_DIR/${table}_complete.sql" ]; then
        record_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -se "SELECT COUNT(*) FROM $DATABASE_NAME.$table;")
        file_size=$(du -h "$BACKUP_DIR/${table}_complete.sql" | cut -f1)
        
        echo "$table:" >> "$BACKUP_DIR/database_info.txt"
        echo "  Records: $record_count" >> "$BACKUP_DIR/database_info.txt"
        echo "  File Size: $file_size" >> "$BACKUP_DIR/database_info.txt"
        echo "  File: ${table}_complete.sql" >> "$BACKUP_DIR/database_info.txt"
        echo "" >> "$BACKUP_DIR/database_info.txt"
    fi
done

cat >> "$BACKUP_DIR/database_info.txt" << EOF
File Structure:
==============
{table}_complete.sql - Complete mysqldump (DROP TABLE + CREATE TABLE + INSERT statements)

Import Instructions:
===================
1. Using the import script:
   ./scripts/database/import_amisafe_pure.sh

2. Manual import (each table):
   mysql -u$MYSQL_USER $DATABASE_NAME < {table}_complete.sql

3. Import all tables at once:
   for file in *.sql; do mysql -u$MYSQL_USER $DATABASE_NAME < "\$file"; done

Database Schema Commands:
========================
# Create database if needed:
mysql -u$MYSQL_USER -e "CREATE DATABASE IF NOT EXISTS $DATABASE_NAME;"

# List imported tables:
mysql -u$MYSQL_USER -e "SHOW TABLES;" $DATABASE_NAME

# Verify record counts:
mysql -u$MYSQL_USER -e "SELECT 'amisafe_raw_incidents' as table_name, COUNT(*) as records FROM amisafe_raw_incidents 
UNION SELECT 'amisafe_clean_incidents', COUNT(*) FROM amisafe_clean_incidents 
UNION SELECT 'amisafe_h3_aggregated', COUNT(*) FROM amisafe_h3_aggregated;" $DATABASE_NAME

Files Created:
=============
EOF

# List all SQL files with details
ls -la "$BACKUP_DIR"/*.sql >> "$BACKUP_DIR/database_info.txt" 2>/dev/null || echo "No SQL files found" >> "$BACKUP_DIR/database_info.txt"

# Calculate total backup size
total_size=$(du -sh "$BACKUP_DIR" | cut -f1)

echo ""
echo "🎉 AmISafe Database Export Complete!"
echo "📁 Total backup size: $total_size"
echo "📂 Files created in: $BACKUP_DIR"
echo ""

# Show final file listing
ls -la "$BACKUP_DIR"

echo ""
echo "📋 Summary:"
echo "   • Database: $DATABASE_NAME"
echo "   • Tables: ${#TABLES[@]} exported"
echo "   • Total size: $total_size"
echo "   • Info file: database_info.txt"
echo ""
echo "🔄 To import this data later:"
echo "   ./scripts/database/import_amisafe_pure.sh"
echo ""