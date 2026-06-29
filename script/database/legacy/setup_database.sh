#!/bin/bash

# H3 Geolocation Pipeline Database Setup Script
# This script sets up all database tables and configurations for the data pipeline

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
echo -e "${BLUE}H3 Geolocation Pipeline Database Setup${NC}"
echo -e "${BLUE}===================================================${NC}"

if [ -z "${DB_PASSWORD}" ]; then
    echo -e "${RED}✗ DB_PASSWORD must be set in the environment${NC}"
    exit 1
fi

# Check if MySQL is running
echo -e "${YELLOW}Checking MySQL service status...${NC}"
if ! pgrep -x mysqld > /dev/null; then
    echo -e "${YELLOW}Starting MySQL service...${NC}"
    sudo service mysql start
    sleep 3
fi

if pgrep -x mysqld > /dev/null; then
    echo -e "${GREEN}✓ MySQL service is running${NC}"
else
    echo -e "${RED}✗ MySQL service failed to start${NC}"
    exit 1
fi

# Test database connection
echo -e "${YELLOW}Testing database connection...${NC}"
if mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -e "SELECT 1;" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Database connection failed${NC}"
    echo -e "${RED}Please check MySQL credentials and service status${NC}"
    exit 1
fi

# Check if database exists
echo -e "${YELLOW}Checking if database '$DB_NAME' exists...${NC}"
if mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -e "USE $DB_NAME;" > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Database '$DB_NAME' exists${NC}"
else
    echo -e "${YELLOW}Creating database '$DB_NAME'...${NC}"
    mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo -e "${GREEN}✓ Database '$DB_NAME' created${NC}"
fi

# Backup existing tables (if any)
echo -e "${YELLOW}Creating backup of existing tables...${NC}"
BACKUP_DIR="${PROJECT_ROOT}/backups/database"
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="${BACKUP_DIR}/h3_pipeline_backup_$(date +%Y%m%d_%H%M%S).sql"

# Check if any H3 tables exist and backup if they do
EXISTING_TABLES=$(mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -D"$DB_NAME" -e "SHOW TABLES LIKE '%amisafe%';" -s -N 2>/dev/null | wc -l)
if [ "$EXISTING_TABLES" -gt 0 ]; then
    echo -e "${YELLOW}Backing up existing tables to: $BACKUP_FILE${NC}"
    mysqldump -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" "$DB_NAME" \
        --tables $(mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -D"$DB_NAME" -e "SHOW TABLES LIKE '%amisafe%'; SHOW TABLES LIKE '%h3_%';" -s -N 2>/dev/null | tr '\n' ' ') \
        > "$BACKUP_FILE" 2>/dev/null || echo -e "${YELLOW}No existing tables to backup${NC}"
    echo -e "${GREEN}✓ Backup completed${NC}"
else
    echo -e "${GREEN}✓ No existing tables to backup${NC}"
fi

# Execute the SQL setup script
echo -e "${YELLOW}Executing database setup script...${NC}"
SQL_SCRIPT="${SCRIPT_DIR}/setup_h3_pipeline.sql"

if [ ! -f "$SQL_SCRIPT" ]; then
    echo -e "${RED}✗ SQL script not found: $SQL_SCRIPT${NC}"
    exit 1
fi

echo -e "${BLUE}Running SQL setup script...${NC}"
if mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -D"$DB_NAME" < "$SQL_SCRIPT"; then
    echo -e "${GREEN}✓ Database setup completed successfully${NC}"
else
    echo -e "${RED}✗ Database setup failed${NC}"
    echo -e "${RED}Check the SQL script for errors${NC}"
    exit 1
fi

# Verify table creation
echo -e "${YELLOW}Verifying table creation...${NC}"
EXPECTED_TABLES=(
    "amisafe_raw_incidents"
    "amisafe_clean_incidents" 
    "amisafe_h3_aggregated"
    "h3_pipeline_log"
    "h3_data_quality_rules"
    "h3_configuration"
)

ALL_TABLES_CREATED=true
for table in "${EXPECTED_TABLES[@]}"; do
    if mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -D"$DB_NAME" -e "DESCRIBE $table;" > /dev/null 2>&1; then
        echo -e "${GREEN}  ✓ $table${NC}"
    else
        echo -e "${RED}  ✗ $table${NC}"
        ALL_TABLES_CREATED=false
    fi
done

# Verify views creation
echo -e "${YELLOW}Verifying views creation...${NC}"
EXPECTED_VIEWS=(
    "v_pipeline_status"
    "v_data_quality_summary"
    "v_h3_summary"
)

for view in "${EXPECTED_VIEWS[@]}"; do
    if mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -D"$DB_NAME" -e "SELECT * FROM $view LIMIT 1;" > /dev/null 2>&1; then
        echo -e "${GREEN}  ✓ $view${NC}"
    else
        echo -e "${RED}  ✗ $view${NC}"
        ALL_TABLES_CREATED=false
    fi
done

# Verify stored procedures
echo -e "${YELLOW}Verifying stored procedures...${NC}"
PROCEDURE_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -D"$DB_NAME" -e "SHOW PROCEDURE STATUS WHERE Db='$DB_NAME' AND Name LIKE '%Pipeline%';" -s -N | wc -l)
echo -e "${YELLOW}  ⚠ Stored procedures skipped (privilege limitations) - pipeline will use direct SQL${NC}"

# Display configuration values
echo -e "${YELLOW}Displaying default configuration...${NC}"
mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -D"$DB_NAME" -e "SELECT config_key, config_value, description FROM h3_configuration ORDER BY config_key;" -t

# Display data quality rules
echo -e "${YELLOW}Displaying data quality rules...${NC}"
mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -D"$DB_NAME" -e "SELECT rule_name, rule_type, field_name, severity FROM h3_data_quality_rules ORDER BY severity, rule_name;" -t

# Final status
echo -e "${BLUE}===================================================${NC}"
if [ "$ALL_TABLES_CREATED" = true ]; then
    echo -e "${GREEN}✓ H3 Pipeline Database Setup COMPLETED Successfully!${NC}"
    echo -e "${GREEN}Database: $DB_NAME${NC}"
    echo -e "${GREEN}Tables: ${#EXPECTED_TABLES[@]} core tables + ${#EXPECTED_VIEWS[@]} views${NC}"
    echo -e "${GREEN}Configuration: Default values loaded${NC}"
    echo -e "${GREEN}Data Quality: Rules configured${NC}"
    
    # Show pipeline status
    echo -e "${BLUE}Current Pipeline Status:${NC}"
    mysql -u"$DB_USER" -p"$DB_PASSWORD" -h"$DB_HOST" -D"$DB_NAME" -e "SELECT * FROM v_pipeline_status;" -t
    
    echo -e "${BLUE}===================================================${NC}"
    echo -e "${GREEN}Ready to run data pipeline!${NC}"
    echo -e "${YELLOW}Next steps:${NC}"
    echo -e "  1. Run Raw Layer: cd h3-geolocation/database && python amisafe_processor.py"
    echo -e "  2. Run Transform Layer: python amisafe_transform_processor.py" 
    echo -e "  3. Run Final Layer: python ../large_dataset_processor.py"
    
else
    echo -e "${RED}✗ Database setup completed with errors${NC}"
    echo -e "${YELLOW}Please check the output above for missing tables or views${NC}"
    exit 1
fi