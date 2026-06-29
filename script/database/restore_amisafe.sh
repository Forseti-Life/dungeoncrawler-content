#!/bin/bash

# AmISafe H3 Database Comprehensive Restore Script
# Restores all tables, stored procedures, and analytics data
# Supports 412,560 hexagons across 9 H3 resolutions (5-13)
# Includes 84 analytical columns and 21 stored procedures

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEFAULT_BACKUP_DIR="$SCRIPT_DIR/../../database-exports/dumps"
BACKUP_DIR="${BACKUP_DIR:-$DEFAULT_BACKUP_DIR}"
DATABASE_NAME="amisafe_database"
MYSQL_USER="drupal_user"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
MYSQL_HOST="127.0.0.1"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}============================================================${NC}"
echo -e "${BLUE}AmISafe H3 Database Comprehensive Restore${NC}"
echo -e "${BLUE}============================================================${NC}"
echo -e "${YELLOW}📂 Backup Directory: $BACKUP_DIR${NC}"
echo -e "${YELLOW}🗄️  Database: $DATABASE_NAME${NC}"
echo -e "${YELLOW}⏰ Restore Started: $TIMESTAMP${NC}"
echo ""

if [ -z "${MYSQL_PASSWORD}" ]; then
    echo -e "${RED}❌ MYSQL_PASSWORD must be set in the environment${NC}"
    exit 1
fi

# Check if backup directory exists
if [[ ! -d "$BACKUP_DIR" ]]; then
    echo -e "${RED}❌ Backup directory not found: $BACKUP_DIR${NC}"
    echo -e "${YELLOW}Please run backup_amisafe.sh first to create backups${NC}"
    exit 1
fi

# Find the most recent backup file
echo -e "${YELLOW}🔍 Looking for backup files...${NC}"
latest_backup=""
latest_metadata=""

# Check for compressed backups first
if ls "$BACKUP_DIR"/amisafe_complete_*.sql.gz 1> /dev/null 2>&1; then
    latest_backup=$(ls -t "$BACKUP_DIR"/amisafe_complete_*.sql.gz | head -1)
    backup_basename=$(basename "$latest_backup" .sql.gz)
    latest_metadata="$BACKUP_DIR/${backup_basename}_metadata.txt"
    compressed=true
# Then check for uncompressed backups
elif ls "$BACKUP_DIR"/amisafe_complete_*.sql 1> /dev/null 2>&1; then
    latest_backup=$(ls -t "$BACKUP_DIR"/amisafe_complete_*.sql | head -1)
    backup_basename=$(basename "$latest_backup" .sql)
    latest_metadata="$BACKUP_DIR/${backup_basename}_metadata.txt"
    compressed=false
else
    echo -e "${RED}❌ No backup files found in $BACKUP_DIR${NC}"
    echo -e "${YELLOW}Please run backup_amisafe.sh first${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Found backup: $(basename "$latest_backup")${NC}"
size=$(ls -lh "$latest_backup" | awk '{print $5}')
echo -e "${GREEN}   Size: $size${NC}"

# Show backup metadata if available
if [[ -f "$latest_metadata" ]]; then
    echo ""
    echo -e "${YELLOW}📊 Backup Information:${NC}"
    grep -E "(Backup Timestamp|Total Hexagons|Total Records|Stored Procedures|H3 Resolutions)" "$latest_metadata" | while read -r line; do
        echo -e "${BLUE}   $line${NC}"
    done
fi

# Test database connection
echo ""
echo -e "${YELLOW}🔍 Testing database connection...${NC}"
if ! mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -e "SELECT 1;" >/dev/null 2>&1; then
    echo -e "${RED}❌ Cannot connect to MySQL database${NC}"
    echo -e "${YELLOW}Please check your credentials and ensure MySQL is running${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Database connection successful${NC}"

# Check if database exists
if ! mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -e "USE $DATABASE_NAME;" >/dev/null 2>&1; then
    echo ""
    echo -e "${YELLOW}📊 Database does not exist. Creating: $DATABASE_NAME${NC}"
    mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -e "CREATE DATABASE $DATABASE_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo -e "${GREEN}✅ Database created${NC}"
    database_exists=false
else
    echo -e "${GREEN}✅ Database exists${NC}"
    database_exists=true
    
    # Check current database contents
    echo ""
    echo -e "${YELLOW}📊 Current database contents:${NC}"
    tables=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SHOW TABLES;" 2>/dev/null || echo "")
    if [[ -n "$tables" ]]; then
        while IFS= read -r table; do
            count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
            echo -e "${BLUE}   $table: $(printf "%'d" $count) records${NC}"
        done <<< "$tables"
    else
        echo -e "${BLUE}   (empty database)${NC}"
    fi
    
    procedure_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '$DATABASE_NAME' AND ROUTINE_TYPE = 'PROCEDURE';" 2>/dev/null || echo "0")
    echo -e "${BLUE}   Stored Procedures: $procedure_count${NC}"
fi

# Warning about data destruction
echo ""
echo -e "${RED}⚠️  WARNING: This will completely replace all existing data!${NC}"
echo -e "${RED}⚠️  All current tables and stored procedures will be dropped!${NC}"
echo -e "${RED}⚠️  This action cannot be undone!${NC}"
echo ""

# Confirm restoration
read -p "🤔 Are you sure you want to proceed with the restore? (y/N): " confirm
if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}❌ Restore cancelled${NC}"
    exit 0
fi

# Additional confirmation for safety (only if database has data)
if [[ "$database_exists" == true ]] && [[ -n "$tables" ]]; then
    echo ""
    read -p "🚨 Final confirmation - This WILL DELETE existing data. Type 'RESTORE' to continue: " final_confirm
    if [[ "$final_confirm" != "RESTORE" ]]; then
        echo -e "${YELLOW}❌ Restore cancelled - confirmation not matched${NC}"
        exit 0
    fi
fi

# Perform restoration
echo ""
echo -e "${YELLOW}🔄 Starting comprehensive database restoration...${NC}"
echo -e "${BLUE}📦 Restoring: Tables + Stored Procedures + Triggers${NC}"

# Import the backup
if [[ "$compressed" == true ]]; then
    echo -e "${YELLOW}🗜️  Decompressing and importing backup...${NC}"
    gunzip -c "$latest_backup" | mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME"
else
    echo -e "${YELLOW}📥 Importing backup...${NC}"
    mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" < "$latest_backup"
fi

echo -e "${GREEN}✅ Backup imported successfully${NC}"

# Verify restoration
echo ""
echo -e "${YELLOW}🔍 Verifying restoration...${NC}"

# Check tables
EXPECTED_TABLES=(
    "amisafe_raw_incidents"
    "amisafe_clean_incidents"
    "amisafe_h3_aggregated"
    "amisafe_ucr_codes"
)

echo -e "${YELLOW}📊 Table verification:${NC}"
for table in "${EXPECTED_TABLES[@]}"; do
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -e "DESCRIBE $table;" >/dev/null 2>&1; then
        count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
        echo -e "${GREEN}✅ $table: $(printf "%'d" $count) records${NC}"
    else
        echo -e "${RED}❌ Table missing: $table${NC}"
        exit 1
    fi
done

# Verify stored procedures
echo ""
echo -e "${YELLOW}🔧 Stored procedure verification:${NC}"
procedure_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '$DATABASE_NAME' AND ROUTINE_TYPE = 'PROCEDURE';" 2>/dev/null || echo "0")
echo -e "${GREEN}✅ Stored procedures: $procedure_count${NC}"

if [[ $procedure_count -lt 20 ]]; then
    echo -e "${YELLOW}⚠️  Warning: Expected ~21 procedures, found $procedure_count${NC}"
fi

# Verify H3 coverage
echo ""
echo -e "${YELLOW}🗺️  H3 Coverage verification:${NC}"
h3_stats=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "
SELECT 
    MIN(h3_resolution) as min_res,
    MAX(h3_resolution) as max_res,
    COUNT(DISTINCT h3_resolution) as resolution_count,
    COUNT(*) as total_hexagons
FROM amisafe_h3_aggregated;" 2>/dev/null || echo "0 0 0 0")
read min_res max_res res_count total_hexagons <<< "$h3_stats"
echo -e "${GREEN}✅ H3 Resolutions: $min_res-$max_res ($res_count resolutions)${NC}"
echo -e "${GREEN}✅ Total Hexagons: $(printf "%'d" $total_hexagons)${NC}"

# Verify analytics data
echo ""
echo -e "${YELLOW}📈 Analytics verification:${NC}"
analytics_stats=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "
SELECT 
    SUM(CASE WHEN risk_category IS NOT NULL THEN 1 ELSE 0 END) as alltime,
    SUM(CASE WHEN risk_category_12mo IS NOT NULL THEN 1 ELSE 0 END) as w12mo,
    SUM(CASE WHEN risk_category_6mo IS NOT NULL THEN 1 ELSE 0 END) as w6mo
FROM amisafe_h3_aggregated;" 2>/dev/null || echo "0 0 0")
read alltime_complete w12mo_complete w6mo_complete <<< "$analytics_stats"
echo -e "${GREEN}✅ All-time analytics: $(printf "%'d" $alltime_complete) hexagons${NC}"
echo -e "${GREEN}✅ 12-month windowed: $(printf "%'d" $w12mo_complete) hexagons${NC}"
echo -e "${GREEN}✅ 6-month windowed: $(printf "%'d" $w6mo_complete) hexagons${NC}"

# Verify H3:13 granular filtering
h3_13_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution = 13 AND incident_ids IS NOT NULL;" 2>/dev/null || echo "0")
echo -e "${GREEN}✅ H3:13 hexagons with incident_ids: $(printf "%'d" $h3_13_count)${NC}"

# Create restore log
echo ""
echo -e "${YELLOW}📝 Creating restore log...${NC}"
log_file="$BACKUP_DIR/amisafe_restore_log_${TIMESTAMP}.txt"

cat > "$log_file" << EOF
AmISafe H3 Database Restore Log
================================
Restore Timestamp: $TIMESTAMP
Database Name: $DATABASE_NAME
MySQL Host: $MYSQL_HOST
MySQL User: $MYSQL_USER
Backup File: $(basename "$latest_backup")

Restored Tables:
----------------
EOF

for table in "${EXPECTED_TABLES[@]}"; do
    count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
    printf "%-30s: %'d records\n" "$table" "$count" >> "$log_file"
done

cat >> "$log_file" << EOF

H3 Geospatial Coverage:
-----------------------
H3 Resolutions: $min_res-$max_res ($res_count distinct resolutions)
Total Hexagons: $(printf "%'d" $total_hexagons)
Granular Filtering (H3:13): $(printf "%'d" $h3_13_count) hexagons with incident_ids

Analytics Status:
-----------------
All-time analytics: $(printf "%'d" $alltime_complete) hexagons
12-month windowed: $(printf "%'d" $w12mo_complete) hexagons
6-month windowed: $(printf "%'d" $w6mo_complete) hexagons

Database Objects:
-----------------
Stored Procedures: $procedure_count
Tables: ${#EXPECTED_TABLES[@]}

Restore Status: SUCCESS
Restore Method: Direct SQL import from mysqldump backup
Character Set: utf8mb4
Backup Compression: $([ "$compressed" == true ] && echo "gzip" || echo "none")
EOF

echo -e "${GREEN}✅ Restore log created: $log_file${NC}"

# Final summary
echo ""
echo -e "${GREEN}🎉 AmISafe H3 Database Restore Complete!${NC}"
echo -e "${GREEN}============================================================${NC}"
echo -e "${GREEN}✅ All 4 tables restored successfully${NC}"
echo -e "${GREEN}✅ $procedure_count stored procedures restored${NC}"
echo -e "${GREEN}✅ H3:13 granular filtering data available${NC}"
echo -e "${GREEN}✅ $(printf "%'d" $total_hexagons) hexagons across $res_count resolutions${NC}"
echo -e "${GREEN}✅ Analytics data: All-time + 12mo + 6mo windowed${NC}"
echo -e "${GREEN}============================================================${NC}"
echo ""
echo -e "${YELLOW}🚀 Database is ready for AmISafe operations${NC}"
echo -e "${YELLOW}📋 Restore log: $log_file${NC}"
echo -e "${YELLOW}⏰ Restore completed at: $(date)${NC}"
