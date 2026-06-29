#!/bin/bash

# H3 Geolocation Database Backup Script (Consolidated)
# Creates complete backups of AmISafe H3 pipeline data
# Supports H3:13 granular filtering data including incident_ids column

set -e  # Exit on any error

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/DB Backup"
DATABASE_NAME="theoryofconspiracies_dev"
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

echo -e "${BLUE}===================================================${NC}"
echo -e "${BLUE}H3 Geolocation Database Backup (Consolidated)${NC}"  
echo -e "${BLUE}===================================================${NC}"
echo -e "${YELLOW}📂 Backup Directory: $BACKUP_DIR${NC}"
echo -e "${YELLOW}🗄️ Database: $DATABASE_NAME${NC}"
echo -e "${YELLOW}⏰ Timestamp: $TIMESTAMP${NC}"
echo -e "${YELLOW}✨ Features: H3:13 Granular Filtering Support${NC}"
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
)

echo ""
echo -e "${YELLOW}📊 Checking table statistics...${NC}"
total_records=0
for table in "${TABLES[@]}"; do
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -e "DESCRIBE $table;" >/dev/null 2>&1; then
        count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
        echo -e "${GREEN}✅ $table: $count records${NC}"
        total_records=$((total_records + count))
    else
        echo -e "${RED}❌ Table not found: $table${NC}"
        exit 1
    fi
done

# Verify H3:13 granular filtering data
echo ""
echo -e "${YELLOW}🔍 Verifying H3:13 granular filtering data...${NC}"
h3_13_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution = 13 AND incident_ids IS NOT NULL;" 2>/dev/null || echo "0")
echo -e "${GREEN}✅ H3:13 hexagons with incident_ids: $h3_13_count${NC}"

echo ""
echo -e "${YELLOW}📋 Total records to backup: $total_records${NC}"
echo -e "${YELLOW}💾 Estimated backup size: ~$(( (total_records / 1000000) * 300 ))MB${NC}"

# Confirm backup
echo ""
read -p "🤔 Proceed with backup? This will overwrite existing backup files. (y/N): " confirm
if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}❌ Backup cancelled${NC}"
    exit 0
fi

# Clear old backup files
echo ""
echo -e "${YELLOW}🧹 Clearing old backup files...${NC}"
rm -f "$BACKUP_DIR"/*.sql
rm -f "$BACKUP_DIR"/*.txt
rm -f "$BACKUP_DIR"/*.empty
echo -e "${GREEN}✅ Old backups cleared${NC}"

# Create backups for each table
echo ""
echo -e "${YELLOW}💾 Creating database backups...${NC}"

for table in "${TABLES[@]}"; do
    echo -e "${BLUE}📤 Backing up $table...${NC}"
    
    # Full backup with data
    mysqldump \
        -u"$MYSQL_USER" \
        -p"$MYSQL_PASSWORD" \
        -h"$MYSQL_HOST" \
        --single-transaction \
        --routines=false \
        --triggers=false \
        --add-drop-table \
        --add-locks \
        --disable-keys \
        --extended-insert \
        --quick \
        --set-charset \
        --default-character-set=utf8mb4 \
        "$DATABASE_NAME" "$table" > "$BACKUP_DIR/${table}_complete.sql"
    
    # Structure only backup
    mysqldump \
        -u"$MYSQL_USER" \
        -p"$MYSQL_PASSWORD" \
        -h"$MYSQL_HOST" \
        --no-data \
        --routines=false \
        --triggers=false \
        --add-drop-table \
        --set-charset \
        --default-character-set=utf8mb4 \
        "$DATABASE_NAME" "$table" > "$BACKUP_DIR/${table}_structure.sql"
    
    # Create empty data file marker  
    touch "$BACKUP_DIR/${table}_data.empty"
    
    echo -e "${GREEN}✅ $table backup complete${NC}"
done

# Create backup metadata
echo ""
echo -e "${YELLOW}📝 Creating backup metadata...${NC}"
cat > "$BACKUP_DIR/database_info.txt" << EOF
H3 Geolocation Database Backup Information
==========================================
Timestamp: $TIMESTAMP
Database: $DATABASE_NAME
Host: $MYSQL_HOST
User: $MYSQL_USER

Table Statistics:
EOF

for table in "${TABLES[@]}"; do
    count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
    echo "$table: $count records" >> "$BACKUP_DIR/database_info.txt"
done

cat >> "$BACKUP_DIR/database_info.txt" << EOF

H3:13 Granular Filtering:
- H3:13 hexagons with incident_ids: $h3_13_count
- incident_ids column: JSON array of incident IDs
- Granular filtering: Enabled
- Room-level precision: 7m × 7m hexagons

Backup Files:
- *_complete.sql: Full table with data
- *_structure.sql: Table structure only  
- *_data.empty: Marker for data files
- database_info.txt: This metadata file

Total Records: $total_records
Backup Method: mysqldump with single-transaction
Character Set: utf8mb4
EOF

# Show backup results
echo -e "${GREEN}✅ Backup metadata created${NC}"
echo ""
echo -e "${YELLOW}📊 Backup Summary:${NC}"
ls -lh "$BACKUP_DIR"/*.sql | while read -r line; do
    echo -e "${BLUE}$line${NC}"
done

echo ""
echo -e "${GREEN}🎉 H3 Geolocation Database Backup Complete!${NC}"
echo -e "${GREEN}✅ All AmISafe tables backed up successfully${NC}"
echo -e "${GREEN}✅ H3:13 granular filtering data preserved${NC}"
echo -e "${GREEN}✅ incident_ids JSON arrays included${NC}"
echo ""
echo -e "${YELLOW}📂 Backup location: $BACKUP_DIR${NC}"
echo -e "${YELLOW}⏰ Backup timestamp: $TIMESTAMP${NC}"
echo -e "${YELLOW}💾 Use restore.sh to restore from these backups${NC}"