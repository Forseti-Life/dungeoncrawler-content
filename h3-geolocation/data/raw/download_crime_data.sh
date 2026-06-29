#!/bin/bash
# Download Philadelphia Crime Incidents Data (2006-2024)
# Usage: Run this script from anywhere, it will use its own location

set -e

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# Base URL for OpenDataPhilly Carto API
BASE_URL="https://phl.carto.com/api/v2/sql"

echo "Working directory: $SCRIPT_DIR"

echo "================================================================"
echo "Downloading Philadelphia Crime Incidents (2006-2025)"
echo "================================================================"

# Function to download data for a specific year
download_year() {
    local year=$1
    local next_year=$((year + 1))
    local filename="crime${year}.csv"
    
    echo ""
    echo ">>> Downloading ${year} crime data..."
    
    # Build the URL with proper encoding
    local url="${BASE_URL}?filename=incidents_part1_part2&format=csv&q=SELECT%20*%20,%20ST_Y(the_geom)%20AS%20lat,%20ST_X(the_geom)%20AS%20lng%20FROM%20incidents_part1_part2%20WHERE%20dispatch_date_time%20%3E=%20%27${year}-01-01%27%20AND%20dispatch_date_time%20%3C%20%27${next_year}-01-01%27"
    
    # Download with wget
    wget -O "$filename" "$url"
    
    # Verify download
    if [[ -f "$filename" ]]; then
        local lines=$(wc -l < "$filename")
        local size=$(du -h "$filename" | cut -f1)
        echo "✅ ${year}: ${lines} lines, ${size}"
    else
        echo "❌ Failed to download ${year}"
    fi
}

# Download data for each year from 2006 to 2025
for year in {2006..2025}; do
    download_year $year
    sleep 2  # Be nice to the API
done

echo ""
echo "================================================================"
echo "Download Complete!"
echo "================================================================"
ls -lh *.csv | awk '{print $9, $5}'
echo ""
echo "Total files: $(ls -1 *.csv | wc -l)"
echo "Total size: $(du -sh . | cut -f1)"
echo ""
echo "Next steps:"
echo "  1. Set ownership: chown -R www-data:www-data $SCRIPT_DIR"
echo "  2. Run ETL pipeline: cd ${SCRIPT_DIR}/../../database/etl && python3 amisafe_processor.py"
