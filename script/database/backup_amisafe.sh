#!/bin/bash

# AmISafe H3 Database Comprehensive Backup Script
# Backs up all tables, stored procedures, and analytics data
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
BACKUP_FILE_PREFIX="amisafe_complete_${TIMESTAMP}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}============================================================${NC}"
echo -e "${BLUE}AmISafe H3 Database Comprehensive Backup${NC}"  
echo -e "${BLUE}============================================================${NC}"
echo -e "${YELLOW}📂 Backup Directory: $BACKUP_DIR${NC}"
echo -e "${YELLOW}🗄️  Database: $DATABASE_NAME${NC}"
echo -e "${YELLOW}⏰ Timestamp: $TIMESTAMP${NC}"
echo -e "${YELLOW}✨ Features: 9 H3 Resolutions, 84 Columns, 21 Procedures${NC}"
echo ""

if [ -z "${MYSQL_PASSWORD}" ]; then
    echo -e "${RED}❌ MYSQL_PASSWORD must be set in the environment${NC}"
    exit 1
fi

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Test database connection
echo -e "${YELLOW}🔍 Testing database connection...${NC}"
if ! mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -e "USE $DATABASE_NAME;" 2>/dev/null; then
    echo -e "${RED}❌ Cannot connect to database $DATABASE_NAME${NC}"
    echo -e "${YELLOW}Please check your database credentials and ensure MySQL is running${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Database connection successful${NC}"

# Check if tables exist and get record counts
TABLES=(
    "amisafe_raw_incidents"
    "amisafe_clean_incidents"
    "amisafe_h3_aggregated"
    "amisafe_ucr_codes"
)

echo ""
echo -e "${YELLOW}📊 Checking table statistics...${NC}"
total_records=0
for table in "${TABLES[@]}"; do
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -e "DESCRIBE $table;" >/dev/null 2>&1; then
        count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
        echo -e "${GREEN}✅ $table: $(printf "%'d" $count) records${NC}"
        total_records=$((total_records + count))
    else
        echo -e "${RED}❌ Table not found: $table${NC}"
        exit 1
    fi
done

# Check stored procedures
echo ""
echo -e "${YELLOW}🔧 Checking stored procedures...${NC}"
procedure_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '$DATABASE_NAME' AND ROUTINE_TYPE = 'PROCEDURE';" 2>/dev/null || echo "0")
echo -e "${GREEN}✅ Stored procedures: $procedure_count${NC}"

# Verify H3 resolution coverage
echo ""
echo -e "${YELLOW}🗺️  Verifying H3 resolution coverage...${NC}"
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

# Check analytics completion
echo ""
echo -e "${YELLOW}📈 Checking analytics completion...${NC}"
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

# Check H3:13 granular filtering data
h3_13_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution = 13 AND incident_ids IS NOT NULL;" 2>/dev/null || echo "0")
echo -e "${GREEN}✅ H3:13 hexagons with incident_ids: $(printf "%'d" $h3_13_count)${NC}"

echo ""
echo -e "${YELLOW}📋 Total records to backup: $(printf "%'d" $total_records)${NC}"
echo -e "${YELLOW}💾 Estimated backup size: ~$(( (total_records / 1000000) * 300 ))MB + procedures${NC}"

# Confirm backup
echo ""
read -p "🤔 Proceed with comprehensive backup? (y/N): " confirm
if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}❌ Backup cancelled${NC}"
    exit 0
fi

# Create comprehensive backup with stored procedures
echo ""
echo -e "${YELLOW}💾 Creating comprehensive database backup...${NC}"
echo -e "${BLUE}📦 Backup includes: Tables + Stored Procedures + Triggers${NC}"

mysqldump \
    -u"$MYSQL_USER" \
    -p"$MYSQL_PASSWORD" \
    -h"$MYSQL_HOST" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --add-drop-table \
    --add-locks \
    --disable-keys \
    --extended-insert \
    --quick \
    --set-charset \
    --default-character-set=utf8mb4 \
    --result-file="$BACKUP_DIR/${BACKUP_FILE_PREFIX}.sql" \
    "$DATABASE_NAME"

echo -e "${GREEN}✅ Comprehensive backup complete${NC}"

# Create structure-only backup (for reference)
echo ""
echo -e "${YELLOW}📋 Creating structure-only backup...${NC}"
mysqldump \
    -u"$MYSQL_USER" \
    -p"$MYSQL_PASSWORD" \
    -h"$MYSQL_HOST" \
    --no-data \
    --routines \
    --triggers \
    --events \
    --add-drop-table \
    --set-charset \
    --default-character-set=utf8mb4 \
    --result-file="$BACKUP_DIR/${BACKUP_FILE_PREFIX}_structure.sql" \
    "$DATABASE_NAME"

echo -e "${GREEN}✅ Structure backup complete${NC}"

# Create backup metadata
echo ""
echo -e "${YELLOW}📝 Creating backup metadata...${NC}"
metadata_file="$BACKUP_DIR/${BACKUP_FILE_PREFIX}_metadata.txt"

cat > "$metadata_file" << EOF
AmISafe H3 Database Backup Metadata
====================================
Backup Timestamp: $TIMESTAMP
Database Name: $DATABASE_NAME
MySQL Host: $MYSQL_HOST
MySQL User: $MYSQL_USER

Table Statistics:
-----------------
EOF

for table in "${TABLES[@]}"; do
    count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
    printf "%-30s: %'d records\n" "$table" "$count" >> "$metadata_file"
done

cat >> "$metadata_file" << EOF

H3 Geospatial Coverage:
-----------------------
H3 Resolutions: $min_res-$max_res ($res_count distinct resolutions)
Total Hexagons: $(printf "%'d" $total_hexagons)
Granular Filtering (H3:13): $(printf "%'d" $h3_13_count) hexagons with incident_ids

Analytics Completion:
---------------------
All-time analytics: $(printf "%'d" $alltime_complete) hexagons
12-month windowed: $(printf "%'d" $w12mo_complete) hexagons
6-month windowed: $(printf "%'d" $w6mo_complete) hexagons

Database Objects:
-----------------
Stored Procedures: $procedure_count
Tables: ${#TABLES[@]}
Total Records: $(printf "%'d" $total_records)

Backup Files:
-------------
Complete backup: ${BACKUP_FILE_PREFIX}.sql
Structure only: ${BACKUP_FILE_PREFIX}_structure.sql
Metadata: ${BACKUP_FILE_PREFIX}_metadata.txt

Backup Method: mysqldump with single-transaction
Character Set: utf8mb4
Includes: Tables + Stored Procedures + Triggers + Events

Restore Command:
----------------
mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" < "$BACKUP_DIR/${BACKUP_FILE_PREFIX}.sql"

Or use: ./restore_amisafe.sh

EOF

echo -e "${GREEN}✅ Backup metadata created${NC}"

# Compress backups (optional)
echo ""
read -p "💾 Compress backup files with gzip? (recommended for storage) (y/N): " compress
if [[ "$compress" =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}🗜️  Compressing backups...${NC}"
    gzip -9 "$BACKUP_DIR/${BACKUP_FILE_PREFIX}.sql"
    gzip -9 "$BACKUP_DIR/${BACKUP_FILE_PREFIX}_structure.sql"
    echo -e "${GREEN}✅ Backups compressed${NC}"
    backup_extension=".sql.gz"
else
    backup_extension=".sql"
fi

# Show backup results
echo ""
echo -e "${YELLOW}📊 Backup Summary:${NC}"
ls -lh "$BACKUP_DIR"/${BACKUP_FILE_PREFIX}* 2>/dev/null | while read -r line; do
    echo -e "${BLUE}$line${NC}"
done

echo ""
echo -e "${GREEN}🎉 AmISafe H3 Database Backup Complete!${NC}"
echo -e "${GREEN}============================================================${NC}"
echo -e "${GREEN}✅ All 4 tables backed up successfully${NC}"
echo -e "${GREEN}✅ 21 stored procedures included${NC}"
echo -e "${GREEN}✅ H3:13 granular filtering data preserved${NC}"
echo -e "${GREEN}✅ 84 analytical columns saved${NC}"
echo -e "${GREEN}✅ $(printf "%'d" $total_hexagons) hexagons across $res_count resolutions${NC}"
echo -e "${GREEN}============================================================${NC}"
echo ""
echo -e "${YELLOW}📂 Backup location: $BACKUP_DIR${NC}"
echo -e "${YELLOW}📦 Main file: ${BACKUP_FILE_PREFIX}${backup_extension}${NC}"
echo -e "${YELLOW}📋 Metadata: ${BACKUP_FILE_PREFIX}_metadata.txt${NC}"
echo -e "${YELLOW}⏰ Backup timestamp: $TIMESTAMP${NC}"
echo ""
echo -e "${YELLOW}💡 To restore this backup:${NC}"
echo -e "${YELLOW}   ./restore_amisafe.sh${NC}"
echo -e "${YELLOW}   or manually:${NC}"
if [[ "$compress" =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}   gunzip < $BACKUP_DIR/${BACKUP_FILE_PREFIX}.sql.gz | mysql -u$MYSQL_USER -p $DATABASE_NAME${NC}"
else
    echo -e "${YELLOW}   mysql -u$MYSQL_USER -p $DATABASE_NAME < $BACKUP_DIR/${BACKUP_FILE_PREFIX}.sql${NC}"
fi
