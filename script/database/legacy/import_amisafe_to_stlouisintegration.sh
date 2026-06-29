#!/bin/bash

# AmISafe Database Import Script to St. Louis Integration Database
# Imports AmISafe tables from backups into stlouisintegration_dev database
# Date: November 7, 2025

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 AmISafe Database Import to St. Louis Integration Starting...${NC}"

# Configuration for stlouisintegration database
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="stlouisintegration_dev"
DB_USER="drupal_user" 
DB_PASS="${DB_PASSWORD:-}"
BACKUP_DIR="/workspaces/stlouisintegration.com/scripts/database/DB Backup"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

if [ -z "$DB_PASS" ]; then
    echo -e "${RED}❌ DB_PASSWORD is not set${NC}"
    exit 1
fi

echo -e "${BLUE}📂 Backup Directory: ${BACKUP_DIR}${NC}"
echo -e "${BLUE}🗄️ Target Database: ${DB_NAME}${NC}"
echo -e "${BLUE}⏰ Import Started: ${TIMESTAMP}${NC}"
echo

# Check if backup directory exists
if [ ! -d "$BACKUP_DIR" ]; then
    echo -e "${RED}❌ Error: Backup directory not found: ${BACKUP_DIR}${NC}"
    echo -e "${YELLOW}💡 Have you run the export script first?${NC}"
    echo -e "${YELLOW}   ./scripts/database/export_amisafe_pure.sh${NC}"
    exit 1
fi

# Change to backup directory
cd "$BACKUP_DIR" || exit 1

# Test database connection
echo -e "${YELLOW}🔍 Testing database connection...${NC}"
echo "   Testing connection as ${DB_USER}@${DB_HOST} to ${DB_NAME}"
if mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1;" "$DB_NAME" >/dev/null 2>&1; then
    echo -e "${GREEN}✅ Database connection successful${NC}"
else
    echo -e "${RED}❌ Database connection failed${NC}"
    echo -e "${YELLOW}💡 Please check:${NC}"
    echo "   • Database server is running"
    echo "   • Credentials are correct: ${DB_USER}"
    echo "   • Database exists: ${DB_NAME}"
    exit 1
fi
echo

# Check for required SQL files
REQUIRED_FILES=(
    "amisafe_raw_incidents_complete.sql"
    "amisafe_clean_incidents_complete.sql"
    "amisafe_h3_aggregated_complete.sql"
)

echo -e "${YELLOW}📋 Checking for required SQL files...${NC}"
MISSING_FILES=()
for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        size=$(du -h "$file" | cut -f1)
        echo -e "${GREEN}  ✅ $file ($size)${NC}"
    else
        echo -e "${RED}  ❌ $file (missing)${NC}"
        MISSING_FILES+=("$file")
    fi
done

if [ ${#MISSING_FILES[@]} -ne 0 ]; then
    echo -e "${RED}❌ Error: Missing required SQL files${NC}"
    echo -e "${YELLOW}💡 Run the export script first:${NC}"
    echo -e "${YELLOW}   ./scripts/database/export_amisafe_pure.sh${NC}"
    exit 1
fi
echo

# Ask for confirmation
echo -e "${YELLOW}⚠️  WARNING: This will CREATE AmISafe tables in ${DB_NAME}${NC}"
echo -e "${YELLOW}   The following tables will be created:${NC}"
for file in "${REQUIRED_FILES[@]}"; do
    table_name=$(echo "$file" | sed 's/_complete\.sql$//')
    echo "   • $table_name"
done
echo
echo -e "${BLUE}💡 This will migrate AmISafe data from theoryofconspiracies to stlouisintegration${NC}"
echo
read -p "Continue with import? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}🛑 Import cancelled by user${NC}"
    exit 0
fi
echo

# Import each table
echo -e "${YELLOW}📥 Importing tables using mysql...${NC}"
IMPORT_SUCCESS=()
IMPORT_FAILED=()

for file in "${REQUIRED_FILES[@]}"; do
    table_name=$(echo "$file" | sed 's/_complete\.sql$//')
    echo -e "${BLUE}📤 Importing ${table_name}...${NC}"
    
    # Import the SQL file
    if mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$file" 2>/dev/null; then
        # Get record count
        record_count=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM $table_name;" "$DB_NAME" 2>/dev/null)
        size=$(du -h "$file" | cut -f1)
        echo -e "${GREEN}  ✅ ${table_name} imported: ${record_count} records (${size})${NC}"
        IMPORT_SUCCESS+=("$table_name")
    else
        echo -e "${RED}  ❌ ${table_name} import failed${NC}"
        IMPORT_FAILED+=("$table_name")
    fi
done
echo

# Create import information file
INFO_FILE="stlouisintegration_import_info_${TIMESTAMP}.txt"
echo -e "${YELLOW}📝 Creating import information file...${NC}"
cat > "$INFO_FILE" << EOF
AmISafe Database Import to St. Louis Integration (Pure SQL)
===========================================================
Import Date: $(date)
Source Database: theoryofconspiracies_dev
Target Database: ${DB_NAME}
MySQL User: ${DB_USER}
Import Location: ${BACKUP_DIR}
Import Method: mysql

Tables Imported:
================
EOF

for table in "${IMPORT_SUCCESS[@]}"; do
    record_count=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM $table;" "$DB_NAME" 2>/dev/null)
    echo "${table}: ${record_count} records" >> "$INFO_FILE"
done

if [ ${#IMPORT_FAILED[@]} -ne 0 ]; then
    echo "" >> "$INFO_FILE"
    echo "Failed Imports:" >> "$INFO_FILE"
    echo "===============" >> "$INFO_FILE"
    for table in "${IMPORT_FAILED[@]}"; do
        echo "${table}: FAILED" >> "$INFO_FILE"
    done
fi

# Final summary
echo -e "${GREEN}🎉 AmISafe Database Import to St. Louis Integration Complete!${NC}"
echo -e "${BLUE}📁 Successfully imported: ${#IMPORT_SUCCESS[@]} tables${NC}"
if [ ${#IMPORT_FAILED[@]} -ne 0 ]; then
    echo -e "${RED}❌ Failed imports: ${#IMPORT_FAILED[@]} tables${NC}"
fi
echo -e "${BLUE}📂 Import info saved: ${INFO_FILE}${NC}"
echo

echo -e "${BLUE}📋 Summary:${NC}"
echo "   • Target Database: ${DB_NAME}"
echo "   • Tables imported: ${#IMPORT_SUCCESS[@]}"
if [ ${#IMPORT_FAILED[@]} -ne 0 ]; then
    echo "   • Failed imports: ${#IMPORT_FAILED[@]}"
fi
echo "   • Info file: ${INFO_FILE}"
echo

# Verification queries
echo -e "${YELLOW}🔍 Quick verification:${NC}"
echo "   1. Check table structure:"
echo "      mysql -u${DB_USER} -p${DB_PASS} -e \"SHOW TABLES LIKE 'amisafe%';\" ${DB_NAME}"
echo
echo "   2. Verify record counts:"
echo "      mysql -u${DB_USER} -p${DB_PASS} -e \"SELECT 'raw' as table_name, COUNT(*) as records FROM amisafe_raw_incidents UNION SELECT 'clean', COUNT(*) FROM amisafe_clean_incidents UNION SELECT 'h3', COUNT(*) FROM amisafe_h3_aggregated;\" ${DB_NAME}"
echo
echo "   3. Test AmISafe module functionality:"
echo "      Visit: http://localhost/amisafe/crime-map"
echo
echo -e "${YELLOW}📝 Next Steps:${NC}"
echo "   1. Update AmISafe module database configuration to use local tables"
echo "   2. Remove cross-database 'amisafe' connection from settings.php"
echo "   3. Test crime map functionality"
echo

# Create success marker
touch "$BACKUP_DIR/.stlouisintegration_import_completed_$(date +%Y%m%d_%H%M%S)"
echo -e "${GREEN}✅ Import completion marker created${NC}"
echo -e "${BLUE}📁 Check $BACKUP_DIR for completion status${NC}"