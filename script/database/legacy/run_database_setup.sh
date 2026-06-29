#!/bin/bash

# Quick Database Setup - Run only the H3 pipeline database setup
# Use this if you just need to set up the database without running the full setup

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}H3 Geolocation Pipeline - Database Setup Only${NC}"
echo -e "${BLUE}=============================================${NC}"

# Ensure MySQL is running
echo -e "${YELLOW}Starting MySQL service if needed...${NC}"
sudo systemctl start mysql 2>/dev/null || true
sleep 2

# Run the database setup
DATABASE_SETUP_SCRIPT="$SCRIPT_DIR/database/setup_database.sh"

if [ -f "$DATABASE_SETUP_SCRIPT" ]; then
    echo -e "${GREEN}Running H3 database setup...${NC}"
    bash "$DATABASE_SETUP_SCRIPT"
    echo -e "${GREEN}Database setup completed!${NC}"
else
    echo -e "${RED}Database setup script not found: $DATABASE_SETUP_SCRIPT${NC}"
    exit 1
fi

echo -e "${BLUE}=============================================${NC}"
echo -e "${GREEN}H3 Database Setup Complete!${NC}"
echo -e "${YELLOW}Next steps:${NC}"
echo -e "  cd h3-geolocation/database"
echo -e "  python amisafe_processor.py      # Run Raw Layer"
echo -e "  python amisafe_transform_processor.py  # Run Transform Layer"