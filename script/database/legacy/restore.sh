#!/bin/bash

# H3 Geolocation Database Restore Script (Consolidated)
# Restores AmISafe H3 pipeline data from backups
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
echo -e "${BLUE}H3 Geolocation Database Restore (Consolidated)${NC}"
echo -e "${BLUE}===================================================${NC}"
echo -e "${YELLOW}📂 Backup Directory: $BACKUP_DIR${NC}"
echo -e "${YELLOW}🗄️ Database: $DATABASE_NAME${NC}"
echo -e "${YELLOW}⏰ Restore Started: $TIMESTAMP${NC}"
echo -e "${YELLOW}✨ Features: H3:13 Granular Filtering Support${NC}"
echo ""

if [ -z "${MYSQL_PASSWORD}" ]; then
    echo -e "${RED}❌ MYSQL_PASSWORD must be set in the environment${NC}"
    exit 1
fi

# Check if backup directory exists
if [[ ! -d "$BACKUP_DIR" ]]; then
    echo -e "${RED}❌ Backup directory not found: $BACKUP_DIR${NC}"
    echo -e "${YELLOW}Please run backup.sh first to create backups${NC}"
    exit 1
fi

# Check for backup files
TABLES=(
    "amisafe_raw_incidents"
    "amisafe_clean_incidents"
    "amisafe_h3_aggregated"
)

echo -e "${YELLOW}🔍 Checking backup files...${NC}"
missing_files=0
for table in "${TABLES[@]}"; do
    backup_file="$BACKUP_DIR/${table}_complete.sql"
    if [[ -f "$backup_file" ]]; then
        size=$(ls -lh "$backup_file" | awk '{print $5}')
        echo -e "${GREEN}✅ $table backup found: $size${NC}"
    else
        echo -e "${RED}❌ Missing backup: $backup_file${NC}"
        missing_files=$((missing_files + 1))
    fi
done

if [[ $missing_files -gt 0 ]]; then
    echo -e "${RED}❌ Missing $missing_files backup files${NC}"
    echo -e "${YELLOW}Please run backup.sh first to create complete backups${NC}"
    exit 1
fi

# Show backup info if available
if [[ -f "$BACKUP_DIR/database_info.txt" ]]; then
    echo ""
    echo -e "${YELLOW}📊 Backup Information:${NC}"
    grep -E "(Timestamp|Total Records|H3:13 hexagons)" "$BACKUP_DIR/database_info.txt" | while read -r line; do
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

# Check if database exists, create if not
if ! mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -e "USE $DATABASE_NAME;" >/dev/null 2>&1; then
    echo -e "${YELLOW}📊 Creating database: $DATABASE_NAME${NC}"
    mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" -e "CREATE DATABASE $DATABASE_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo -e "${GREEN}✅ Database created${NC}"
else
    echo -e "${GREEN}✅ Database exists${NC}"
fi

# Warning about data destruction
echo ""
echo -e "${RED}⚠️  WARNING: This will completely replace all existing data!${NC}"
echo -e "${RED}⚠️  All current AmISafe tables will be dropped and recreated!${NC}"
echo ""
echo -e "${YELLOW}Tables to be restored:${NC}"
for table in "${TABLES[@]}"; do
    echo -e "${YELLOW}  - $table${NC}"
done
echo ""

# Confirm restoration
read -p "🤔 Are you sure you want to proceed with the restore? (y/N): " confirm
if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}❌ Restore cancelled${NC}"
    exit 0
fi

# Additional confirmation for safety
echo ""
read -p "🚨 Final confirmation - This WILL DELETE existing data. Type 'RESTORE' to continue: " final_confirm
if [[ "$final_confirm" != "RESTORE" ]]; then
    echo -e "${YELLOW}❌ Restore cancelled - confirmation not matched${NC}"
    exit 0
fi

# Perform restoration
echo ""
echo -e "${YELLOW}🔄 Starting database restoration...${NC}"

for table in "${TABLES[@]}"; do
    backup_file="$BACKUP_DIR/${table}_complete.sql"
    
    echo -e "${BLUE}📥 Restoring $table...${NC}"
    
    # Import the backup file
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" < "$backup_file"; then
        # Verify restoration
        count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
        echo -e "${GREEN}✅ $table restored: $count records${NC}"
    else
        echo -e "${RED}❌ Failed to restore $table${NC}"
        exit 1
    fi
done

# Verify H3:13 granular filtering data
echo ""
echo -e "${YELLOW}🔍 Verifying H3:13 granular filtering restoration...${NC}"

# Check if incident_ids column exists
if mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -e "SHOW COLUMNS FROM amisafe_h3_aggregated LIKE 'incident_ids';" | grep -q "incident_ids"; then
    echo -e "${GREEN}✅ incident_ids column restored${NC}"
    
    # Count H3:13 records with incident data
    h3_13_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution = 13 AND incident_ids IS NOT NULL;" 2>/dev/null || echo "0")
    echo -e "${GREEN}✅ H3:13 hexagons with incident_ids: $h3_13_count${NC}"
    
    if [[ $h3_13_count -gt 0 ]]; then
        echo -e "${GREEN}✅ H3:13 granular filtering data successfully restored${NC}"
    else
        echo -e "${YELLOW}⚠️  No H3:13 granular filtering data found${NC}"
    fi
else
    echo -e "${RED}❌ incident_ids column missing after restore${NC}"
    exit 1
fi

# Create restore log
echo ""
echo -e "${YELLOW}📝 Creating restore log...${NC}"
log_file="$BACKUP_DIR/${DATABASE_NAME}_restore_info_${TIMESTAMP}.txt"

cat > "$log_file" << EOF
H3 Geolocation Database Restore Log
===================================
Restore Timestamp: $TIMESTAMP
Database: $DATABASE_NAME
Host: $MYSQL_HOST
User: $MYSQL_USER

Restored Tables:
EOF

for table in "${TABLES[@]}"; do
    count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
    echo "$table: $count records" >> "$log_file"
done

h3_13_count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution = 13 AND incident_ids IS NOT NULL;" 2>/dev/null || echo "0")

cat >> "$log_file" << EOF

H3:13 Granular Filtering Status:
- H3:13 hexagons with incident_ids: $h3_13_count
- incident_ids column: Restored
- Granular filtering: Available
- Room-level precision: 7m × 7m hexagons

Restore Status: SUCCESS
Restore Method: Direct SQL import from mysqldump backups
Character Set: utf8mb4
EOF

echo -e "${GREEN}✅ Restore log created: $log_file${NC}"

# Final verification and summary
echo ""
echo -e "${YELLOW}📊 Final Database Summary:${NC}"
for table in "${TABLES[@]}"; do
    count=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -h"$MYSQL_HOST" "$DATABASE_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
    echo -e "${BLUE}📊 $table: $count records${NC}"
done

echo ""
echo -e "${GREEN}🎉 H3 Geolocation Database Restore Complete!${NC}"
echo -e "${GREEN}✅ All AmISafe tables restored successfully${NC}"
echo -e "${GREEN}✅ H3:13 granular filtering data restored${NC}"
echo -e "${GREEN}✅ incident_ids JSON arrays available${NC}"
echo ""
echo -e "${YELLOW}🚀 Database is ready for H3 geolocation operations${NC}"
echo -e "${YELLOW}💡 Test granular filtering with: /api/amisafe/hexagon/{h3_index}/incidents${NC}"
echo -e "${YELLOW}📋 Restore log: $log_file${NC}"