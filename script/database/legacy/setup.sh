#!/bin/bash

# H3 Geolocation Database Setup Script (Consolidated)
# Creates all database tables and configurations for the AmISafe H3 pipeline
# Includes support for H3:13 granular filtering with incident_ids column

set -e  # Exit on any error

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Database configuration
DB_NAME="theoryofconspiracies_dev"
DB_USER="drupal_user"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_HOST="127.0.0.1"

echo -e "${BLUE}===================================================${NC}"
echo -e "${BLUE}H3 Geolocation Database Setup (Consolidated)${NC}"
echo -e "${BLUE}===================================================${NC}"
echo -e "${YELLOW}Database: ${DB_NAME}${NC}"
echo -e "${YELLOW}Features: H3:13 Granular Filtering Support${NC}"
echo ""

if [ -z "${DB_PASSWORD}" ]; then
    echo -e "${RED}❌ DB_PASSWORD must be set in the environment${NC}"
    exit 1
fi

# Check if MySQL is running
echo -e "${YELLOW}Checking MySQL service status...${NC}"
if ! pgrep -x mysqld > /dev/null; then
    echo -e "${RED}❌ MySQL is not running. Please start MySQL first.${NC}"
    echo -e "${YELLOW}Run: sudo service mysql start${NC}"
    exit 1
fi
echo -e "${GREEN}✅ MySQL is running${NC}"

# Test database connection
echo -e "${YELLOW}Testing database connection...${NC}"
if ! mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -e "SELECT 1;" >/dev/null 2>&1; then
    echo -e "${RED}❌ Cannot connect to MySQL database${NC}"
    echo -e "${YELLOW}Please check your credentials:${NC}"
    echo -e "${YELLOW}  User: $DB_USER${NC}"
    echo -e "${YELLOW}  Host: $DB_HOST${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Database connection successful${NC}"

# Check if database exists, create if not
echo -e "${YELLOW}Checking database existence...${NC}"
if ! mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -e "USE $DB_NAME;" >/dev/null 2>&1; then
    echo -e "${YELLOW}📊 Creating database: $DB_NAME${NC}"
    mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -e "CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo -e "${GREEN}✅ Database created successfully${NC}"
else
    echo -e "${GREEN}✅ Database exists${NC}"
fi

# Execute the SQL setup script
echo ""
echo -e "${YELLOW}Creating H3 pipeline tables...${NC}"
SQL_FILE="$SCRIPT_DIR/setup_h3_pipeline.sql"

if [[ ! -f "$SQL_FILE" ]]; then
    echo -e "${RED}❌ SQL file not found: $SQL_FILE${NC}"
    exit 1
fi

echo -e "${YELLOW}📋 Executing SQL setup script...${NC}"
if mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" < "$SQL_FILE"; then
    echo -e "${GREEN}✅ Database tables created successfully${NC}"
else
    echo -e "${RED}❌ Failed to execute SQL setup script${NC}"
    exit 1
fi

# Verify table creation
echo ""
echo -e "${YELLOW}Verifying table creation...${NC}"
TABLES=(
    "amisafe_raw_incidents"
    "amisafe_clean_incidents" 
    "amisafe_h3_aggregated"
)

for table in "${TABLES[@]}"; do
    if mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" "$DB_NAME" -e "DESCRIBE $table;" >/dev/null 2>&1; then
        echo -e "${GREEN}✅ Table exists: $table${NC}"
    else
        echo -e "${RED}❌ Table missing: $table${NC}"
        exit 1
    fi
done

# Verify incident_ids column in amisafe_h3_aggregated table
echo ""
echo -e "${YELLOW}Verifying H3:13 granular filtering support...${NC}"
if mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" "$DB_NAME" -e "SHOW COLUMNS FROM amisafe_h3_aggregated LIKE 'incident_ids';" | grep -q "incident_ids"; then
    echo -e "${GREEN}✅ incident_ids column exists - H3:13 granular filtering enabled${NC}"
else
    echo -e "${RED}❌ incident_ids column missing - granular filtering not available${NC}"
    exit 1
fi

# Show table statistics
echo ""
echo -e "${YELLOW}Database setup complete! Table statistics:${NC}"
for table in "${TABLES[@]}"; do
    count=$(mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" "$DB_NAME" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null || echo "0")
    echo -e "${BLUE}📊 $table: $count records${NC}"
done

echo ""
echo -e "${GREEN}🎉 H3 Geolocation Database Setup Complete!${NC}"
echo -e "${GREEN}✅ All tables created with H3:13 granular filtering support${NC}"
echo -e "${GREEN}✅ incident_ids JSON column ready for room-level analysis${NC}"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo -e "${YELLOW}1. Run H3 data processing pipeline to populate tables${NC}"
echo -e "${YELLOW}2. Use backup script to create backups after data loading${NC}"
echo -e "${YELLOW}3. Test granular filtering API endpoints${NC}"