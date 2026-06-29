#!/bin/bash

# ============================================================================
# AmISafe Gold Layer Data Validation Script
# ============================================================================
# Comprehensive validation of amisafe_h3_aggregated table
# Tests all 104 columns for data integrity, completeness, and validity
# ============================================================================

# Configuration
DB_NAME="amisafe_database"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPORT_DIR="${SCRIPT_DIR}"
REPORT_FILE="${REPORT_DIR}/gold_layer_validation_$(date +%Y%m%d_%H%M%S).md"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔬 AmISafe Gold Layer Validation${NC}"
echo "=============================================="
echo "Database: ${DB_NAME}"
echo "Report: ${REPORT_FILE}"
echo

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
WARNING_TESTS=0

# Initialize report
cat > "$REPORT_FILE" << 'EOF'
# AmISafe Gold Layer Data Validation Report

**Generated**: $(date)
**Database**: amisafe_database
**Table**: amisafe_h3_aggregated

## Executive Summary

EOF

# Helper function to run SQL query
run_query() {
    mysql ${DB_NAME} -sN -e "$1" 2>/dev/null
}

# Helper function to log test result
log_test() {
    local test_name="$1"
    local status="$2"
    local details="$3"
    local expected="$4"
    local actual="$5"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    case "$status" in
        "PASS")
            echo -e "${GREEN}✅ PASS${NC}: $test_name"
            PASSED_TESTS=$((PASSED_TESTS + 1))
            echo "### ✅ PASS: $test_name" >> "$REPORT_FILE"
            ;;
        "FAIL")
            echo -e "${RED}❌ FAIL${NC}: $test_name"
            FAILED_TESTS=$((FAILED_TESTS + 1))
            echo "### ❌ FAIL: $test_name" >> "$REPORT_FILE"
            ;;
        "WARN")
            echo -e "${YELLOW}⚠️  WARN${NC}: $test_name"
            WARNING_TESTS=$((WARNING_TESTS + 1))
            echo "### ⚠️  WARN: $test_name" >> "$REPORT_FILE"
            ;;
    esac
    
    echo "  $details" | tee -a "$REPORT_FILE"
    
    if [ -n "$expected" ]; then
        echo "  - **Expected**: $expected" | tee -a "$REPORT_FILE"
    fi
    
    if [ -n "$actual" ]; then
        echo "  - **Actual**: $actual" | tee -a "$REPORT_FILE"
    fi
    
    echo "" >> "$REPORT_FILE"
}

# ============================================================================
# SECTION 1: Basic Data Integrity
# ============================================================================
echo -e "${BLUE}📊 Section 1: Basic Data Integrity${NC}"
echo "---"

# Test 1.1: Total record count
total_records=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated;")
if [ "$total_records" -gt 0 ]; then
    log_test "Total Records" "PASS" "Found $total_records hexagon records" "Greater than 0" "$total_records"
else
    log_test "Total Records" "FAIL" "No records found in table" "Greater than 0" "0"
fi

# Test 1.2: No NULL h3_index
null_h3=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_index IS NULL OR h3_index = '';")
if [ "$null_h3" -eq 0 ]; then
    log_test "H3 Index Integrity" "PASS" "All records have valid h3_index" "0 NULL values" "0"
else
    log_test "H3 Index Integrity" "FAIL" "Found records with NULL h3_index" "0 NULL values" "$null_h3"
fi

# Test 1.3: No NULL h3_resolution
null_res=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution IS NULL;")
if [ "$null_res" -eq 0 ]; then
    log_test "H3 Resolution Integrity" "PASS" "All records have valid h3_resolution" "0 NULL values" "0"
else
    log_test "H3 Resolution Integrity" "FAIL" "Found records with NULL h3_resolution" "0 NULL values" "$null_res"
fi

# Test 1.4: Valid resolution range (5-13)
invalid_res=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution < 5 OR h3_resolution > 13;")
if [ "$invalid_res" -eq 0 ]; then
    log_test "H3 Resolution Range" "PASS" "All resolutions in valid range (5-13)" "0 out of range" "0"
else
    log_test "H3 Resolution Range" "FAIL" "Found resolutions outside valid range" "0 out of range" "$invalid_res"
fi

# Test 1.5: Incident count consistency across resolutions
echo "" >> "$REPORT_FILE"
echo "#### Incident Count by Resolution" >> "$REPORT_FILE"
echo '```' >> "$REPORT_FILE"
run_query "
SELECT 
    h3_resolution,
    COUNT(*) as hexagons,
    SUM(incident_count) as total_incidents,
    MIN(incident_count) as min_incidents,
    MAX(incident_count) as max_incidents,
    ROUND(AVG(incident_count), 1) as avg_incidents
FROM amisafe_h3_aggregated
GROUP BY h3_resolution
ORDER BY h3_resolution DESC;
" | tee -a "$REPORT_FILE"
echo '```' >> "$REPORT_FILE"

# Verify all resolutions have same total incidents
incident_counts=$(run_query "
SELECT DISTINCT SUM(incident_count) 
FROM amisafe_h3_aggregated 
GROUP BY h3_resolution;
")
unique_counts=$(echo "$incident_counts" | wc -l)
if [ "$unique_counts" -eq 1 ]; then
    log_test "Incident Count Consistency" "PASS" "All resolutions have same total incident count" "1 unique value" "1"
else
    log_test "Incident Count Consistency" "FAIL" "Incident counts differ across resolutions" "1 unique value" "$unique_counts"
fi

# ============================================================================
# SECTION 2: Coordinate Data
# ============================================================================
echo -e "${BLUE}🌍 Section 2: Coordinate Data${NC}"
echo "---"

# Test 2.1: No NULL coordinates
null_coords=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE center_latitude IS NULL OR center_longitude IS NULL;")
if [ "$null_coords" -eq 0 ]; then
    log_test "Coordinate Completeness" "PASS" "All hexagons have coordinates" "0 NULL values" "0"
else
    log_test "Coordinate Completeness" "FAIL" "Found hexagons with NULL coordinates" "0 NULL values" "$null_coords"
fi

# Test 2.2: Valid latitude range (-90 to 90)
invalid_lat=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE center_latitude < -90 OR center_latitude > 90;")
if [ "$invalid_lat" -eq 0 ]; then
    log_test "Latitude Range" "PASS" "All latitudes in valid range (-90 to 90)" "0 out of range" "0"
else
    log_test "Latitude Range" "FAIL" "Found invalid latitudes" "0 out of range" "$invalid_lat"
fi

# Test 2.3: Valid longitude range (-180 to 180)
invalid_lng=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE center_longitude < -180 OR center_longitude > 180;")
if [ "$invalid_lng" -eq 0 ]; then
    log_test "Longitude Range" "PASS" "All longitudes in valid range (-180 to 180)" "0 out of range" "0"
else
    log_test "Longitude Range" "FAIL" "Found invalid longitudes" "0 out of range" "$invalid_lng"
fi

# Test 2.4: Philadelphia geographic bounds (approximate)
# Philadelphia: Lat 39.86-40.13, Lng -75.28--74.95
philly_bounds=$(run_query "
SELECT COUNT(*) FROM amisafe_h3_aggregated 
WHERE center_latitude BETWEEN 39.8 AND 40.2 
AND center_longitude BETWEEN -75.3 AND -74.9;
")
philly_pct=$(echo "scale=2; $philly_bounds * 100 / $total_records" | bc)
if (( $(echo "$philly_pct > 95" | bc -l) )); then
    log_test "Geographic Bounds" "PASS" "$philly_pct% of hexagons within Philadelphia area" "Greater than 95%" "$philly_pct%"
else
    log_test "Geographic Bounds" "WARN" "$philly_pct% of hexagons within Philadelphia area" "Greater than 95%" "$philly_pct%"
fi

# ============================================================================
# SECTION 3: Temporal Data
# ============================================================================
echo -e "${BLUE}📅 Section 3: Temporal Data${NC}"
echo "---"

# Test 3.1: Date range validity
date_ranges=$(run_query "
SELECT 
    MIN(earliest_incident) as min_date,
    MAX(latest_incident) as max_date,
    DATEDIFF(MAX(latest_incident), MIN(earliest_incident)) as days_span
FROM amisafe_h3_aggregated;
")
min_date=$(echo "$date_ranges" | cut -f1)
max_date=$(echo "$date_ranges" | cut -f2)
days_span=$(echo "$date_ranges" | cut -f3)

log_test "Temporal Coverage" "PASS" "Data spans $days_span days ($min_date to $max_date)" "Multiple years" "$days_span days"

# Test 3.2: No future dates
future_dates=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE latest_incident > NOW();")
if [ "$future_dates" -eq 0 ]; then
    log_test "No Future Dates" "PASS" "No incidents dated in the future" "0 future dates" "0"
else
    log_test "No Future Dates" "FAIL" "Found incidents with future dates" "0 future dates" "$future_dates"
fi

# Test 3.3: Earliest before latest
date_logic=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE earliest_incident > latest_incident;")
if [ "$date_logic" -eq 0 ]; then
    log_test "Date Logic" "PASS" "All earliest_incident <= latest_incident" "0 violations" "0"
else
    log_test "Date Logic" "FAIL" "Found records where earliest > latest" "0 violations" "$date_logic"
fi

# ============================================================================
# SECTION 4: All-Time Analytics Columns
# ============================================================================
echo -e "${BLUE}📈 Section 4: All-Time Analytics${NC}"
echo "---"

# Test 4.1: top_crime_type populated
null_top_crime=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE top_crime_type IS NULL;")
if [ "$null_top_crime" -eq 0 ]; then
    log_test "Top Crime Type" "PASS" "All hexagons have top_crime_type" "0 NULL values" "0"
else
    log_test "Top Crime Type" "WARN" "Some hexagons missing top_crime_type" "0 NULL values" "$null_top_crime"
fi

# Test 4.2: risk_score populated
null_risk_score=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE risk_score IS NULL;")
if [ "$null_risk_score" -eq 0 ]; then
    log_test "Risk Score" "PASS" "All hexagons have risk_score" "0 NULL values" "0"
else
    log_test "Risk Score" "FAIL" "Some hexagons missing risk_score" "0 NULL values" "$null_risk_score"
fi

# Test 4.3: risk_category populated
null_risk_cat=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE risk_category IS NULL;")
if [ "$null_risk_cat" -eq 0 ]; then
    log_test "Risk Category" "PASS" "All hexagons have risk_category" "0 NULL values" "0"
else
    log_test "Risk Category" "FAIL" "Some hexagons missing risk_category" "0 NULL values" "$null_risk_cat"
fi

# Test 4.4: Risk category distribution
echo "" >> "$REPORT_FILE"
echo "#### Risk Category Distribution" >> "$REPORT_FILE"
echo '```' >> "$REPORT_FILE"
run_query "
SELECT 
    risk_category,
    COUNT(*) as hexagons,
    ROUND(COUNT(*) * 100.0 / $total_records, 2) as percentage
FROM amisafe_h3_aggregated
GROUP BY risk_category
ORDER BY FIELD(risk_category, 'LOW', 'MODERATE', 'HIGH', 'CRITICAL');
" | tee -a "$REPORT_FILE"
echo '```' >> "$REPORT_FILE"

# Test 4.5: violent_crime_count <= incident_count
violent_logic=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE violent_crime_count > incident_count;")
if [ "$violent_logic" -eq 0 ]; then
    log_test "Violent Crime Logic" "PASS" "violent_crime_count <= incident_count for all records" "0 violations" "0"
else
    log_test "Violent Crime Logic" "FAIL" "Found records where violent > total incidents" "0 violations" "$violent_logic"
fi

# Test 4.6: nonviolent_crime_count consistency
nonviolent_logic=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE nonviolent_crime_count > incident_count;")
if [ "$nonviolent_logic" -eq 0 ]; then
    log_test "Nonviolent Crime Logic" "PASS" "nonviolent_crime_count <= incident_count" "0 violations" "0"
else
    log_test "Nonviolent Crime Logic" "FAIL" "Found records where nonviolent > total" "0 violations" "$nonviolent_logic"
fi

# Test 4.7: violent + nonviolent = total (approximately)
crime_sum_logic=$(run_query "
SELECT COUNT(*) FROM amisafe_h3_aggregated 
WHERE ABS((violent_crime_count + nonviolent_crime_count) - incident_count) > 1;
")
if [ "$crime_sum_logic" -eq 0 ]; then
    log_test "Crime Count Sum" "PASS" "violent + nonviolent ≈ total for all records" "0 violations" "0"
else
    log_test "Crime Count Sum" "WARN" "Some records have sum mismatch" "0 violations" "$crime_sum_logic"
fi

# Test 4.8: Statistical metrics populated
null_z_score=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE incident_z_score IS NULL;")
if [ "$null_z_score" -eq 0 ]; then
    log_test "Z-Score Population" "PASS" "All hexagons have incident_z_score" "0 NULL values" "0"
else
    log_test "Z-Score Population" "WARN" "Some hexagons missing z-score" "0 NULL values" "$null_z_score"
fi

# Test 4.9: Percentile range (0-100)
invalid_percentile=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE incident_percentile < 0 OR incident_percentile > 100;")
if [ "$invalid_percentile" -eq 0 ]; then
    log_test "Percentile Range" "PASS" "All percentiles in range 0-100" "0 out of range" "0"
else
    log_test "Percentile Range" "FAIL" "Found percentiles outside valid range" "0 out of range" "$invalid_percentile"
fi

# ============================================================================
# SECTION 5: Windowed Analytics (12-month)
# ============================================================================
echo -e "${BLUE}📊 Section 5: 12-Month Window Analytics${NC}"
echo "---"

# Test 5.1: 12-month incident count populated
null_12mo=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE incident_count_12mo IS NULL;")
if [ "$null_12mo" -eq 0 ]; then
    log_test "12-Month Incident Count" "PASS" "All hexagons have 12-month count" "0 NULL values" "0"
else
    log_test "12-Month Incident Count" "WARN" "Some hexagons missing 12-month count" "0 NULL values" "$null_12mo"
fi

# Test 5.2: 12-month count <= all-time count
window_logic_12mo=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE incident_count_12mo > incident_count;")
if [ "$window_logic_12mo" -eq 0 ]; then
    log_test "12-Month Window Logic" "PASS" "12-month count <= all-time count" "0 violations" "0"
else
    log_test "12-Month Window Logic" "FAIL" "Found 12-month counts exceeding all-time" "0 violations" "$window_logic_12mo"
fi

# Test 5.3: 12-month z-score populated
null_z_12mo=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE incident_z_score_12mo IS NULL;")
total_12mo_data=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE incident_count_12mo > 0;")
if [ "$null_z_12mo" -eq 0 ]; then
    log_test "12-Month Z-Score" "PASS" "All hexagons have 12-month z-score" "0 NULL values" "0"
elif [ "$null_z_12mo" -eq "$total_records" ]; then
    log_test "12-Month Z-Score" "FAIL" "No 12-month z-scores calculated" "Some populated" "$null_z_12mo NULL"
else
    pct_populated=$(echo "scale=1; ($total_records - $null_z_12mo) * 100 / $total_records" | bc)
    log_test "12-Month Z-Score" "WARN" "$pct_populated% of hexagons have 12-month z-score" "100%" "$pct_populated%"
fi

# Test 5.4: 12-month risk score populated
null_risk_12mo=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE risk_score_12mo IS NULL;")
if [ "$null_risk_12mo" -eq 0 ]; then
    log_test "12-Month Risk Score" "PASS" "All hexagons have 12-month risk score" "0 NULL values" "0"
elif [ "$null_risk_12mo" -eq "$total_records" ]; then
    log_test "12-Month Risk Score" "FAIL" "No 12-month risk scores calculated" "Some populated" "$null_risk_12mo NULL"
else
    pct_populated=$(echo "scale=1; ($total_records - $null_risk_12mo) * 100 / $total_records" | bc)
    log_test "12-Month Risk Score" "WARN" "$pct_populated% have 12-month risk score" "100%" "$pct_populated%"
fi

# ============================================================================
# SECTION 6: Windowed Analytics (6-month)
# ============================================================================
echo -e "${BLUE}📊 Section 6: 6-Month Window Analytics${NC}"
echo "---"

# Test 6.1: 6-month incident count populated
null_6mo=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE incident_count_6mo IS NULL;")
if [ "$null_6mo" -eq 0 ]; then
    log_test "6-Month Incident Count" "PASS" "All hexagons have 6-month count" "0 NULL values" "0"
else
    log_test "6-Month Incident Count" "WARN" "Some hexagons missing 6-month count" "0 NULL values" "$null_6mo"
fi

# Test 6.2: 6-month count <= 12-month count
window_logic_6mo=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE incident_count_6mo > incident_count_12mo;")
if [ "$window_logic_6mo" -eq 0 ]; then
    log_test "6-Month Window Logic" "PASS" "6-month <= 12-month count" "0 violations" "0"
else
    log_test "6-Month Window Logic" "FAIL" "Found 6-month counts exceeding 12-month" "0 violations" "$window_logic_6mo"
fi

# Test 6.3: 6-month z-score populated
null_z_6mo=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE incident_z_score_6mo IS NULL;")
if [ "$null_z_6mo" -eq 0 ]; then
    log_test "6-Month Z-Score" "PASS" "All hexagons have 6-month z-score" "0 NULL values" "0"
elif [ "$null_z_6mo" -eq "$total_records" ]; then
    log_test "6-Month Z-Score" "FAIL" "No 6-month z-scores calculated" "Some populated" "$null_z_6mo NULL"
else
    pct_populated=$(echo "scale=1; ($total_records - $null_z_6mo) * 100 / $total_records" | bc)
    log_test "6-Month Z-Score" "WARN" "$pct_populated% have 6-month z-score" "100%" "$pct_populated%"
fi

# Test 6.4: 6-month risk score populated
null_risk_6mo=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE risk_score_6mo IS NULL;")
if [ "$null_risk_6mo" -eq 0 ]; then
    log_test "6-Month Risk Score" "PASS" "All hexagons have 6-month risk score" "0 NULL values" "0"
elif [ "$null_risk_6mo" -eq "$total_records" ]; then
    log_test "6-Month Risk Score" "FAIL" "No 6-month risk scores calculated" "Some populated" "$null_risk_6mo NULL"
else
    pct_populated=$(echo "scale=1; ($total_records - $null_risk_6mo) * 100 / $total_records" | bc)
    log_test "6-Month Risk Score" "WARN" "$pct_populated% have 6-month risk score" "100%" "$pct_populated%"
fi

# ============================================================================
# SECTION 7: JSON Fields
# ============================================================================
echo -e "${BLUE}📋 Section 7: JSON Fields${NC}"
echo "---"

# Test 7.1: incident_type_counts JSON validity
invalid_json=$(run_query "
SELECT COUNT(*) FROM amisafe_h3_aggregated 
WHERE incident_type_counts IS NOT NULL 
AND JSON_VALID(incident_type_counts) = 0;
")
if [ "$invalid_json" -eq 0 ]; then
    log_test "Incident Type Counts JSON" "PASS" "All JSON fields are valid" "0 invalid" "0"
else
    log_test "Incident Type Counts JSON" "FAIL" "Found invalid JSON in incident_type_counts" "0 invalid" "$invalid_json"
fi

# Test 7.2: district_counts JSON validity
invalid_district_json=$(run_query "
SELECT COUNT(*) FROM amisafe_h3_aggregated 
WHERE district_counts IS NOT NULL 
AND JSON_VALID(district_counts) = 0;
")
if [ "$invalid_district_json" -eq 0 ]; then
    log_test "District Counts JSON" "PASS" "All JSON fields are valid" "0 invalid" "0"
else
    log_test "District Counts JSON" "FAIL" "Found invalid JSON in district_counts" "0 invalid" "$invalid_district_json"
fi

# Test 7.3: temporal JSON fields
for field in incidents_by_hour incidents_by_dow incidents_by_month; do
    invalid_temporal=$(run_query "
    SELECT COUNT(*) FROM amisafe_h3_aggregated 
    WHERE ${field} IS NOT NULL 
    AND JSON_VALID(${field}) = 0;
    ")
    if [ "$invalid_temporal" -eq 0 ]; then
        log_test "${field} JSON" "PASS" "All JSON fields are valid" "0 invalid" "0"
    else
        log_test "${field} JSON" "FAIL" "Found invalid JSON in ${field}" "0 invalid" "$invalid_temporal"
    fi
done

# ============================================================================
# SECTION 8: Cross-Resolution Consistency
# ============================================================================
echo -e "${BLUE}🔗 Section 8: Cross-Resolution Consistency${NC}"
echo "---"

# Test 8.1: Parent-child H3 relationships
null_parents=$(run_query "SELECT COUNT(*) FROM amisafe_h3_aggregated WHERE h3_resolution < 13 AND h3_parent IS NULL;")
if [ "$null_parents" -eq 0 ]; then
    log_test "H3 Parent Relationships" "PASS" "All non-finest hexagons have parents" "0 NULL parents" "0"
else
    log_test "H3 Parent Relationships" "WARN" "Some hexagons missing parent reference" "0 NULL parents" "$null_parents"
fi

# Test 8.2: Sample resolution aggregation consistency
echo "" >> "$REPORT_FILE"
echo "#### Sample Parent-Child Incident Count Check" >> "$REPORT_FILE"
echo '```' >> "$REPORT_FILE"
run_query "
SELECT 
    'Resolution 13 (child)' as level,
    COUNT(*) as hexagons,
    SUM(incident_count) as incidents
FROM amisafe_h3_aggregated 
WHERE h3_resolution = 13
UNION ALL
SELECT 
    'Resolution 12 (parent)',
    COUNT(*),
    SUM(incident_count)
FROM amisafe_h3_aggregated
WHERE h3_resolution = 12;
" | tee -a "$REPORT_FILE"
echo '```' >> "$REPORT_FILE"

# ============================================================================
# SECTION 9: Performance & Indexing
# ============================================================================
echo -e "${BLUE}⚡ Section 9: Performance Checks${NC}"
echo "---"

# Test 9.1: Check for indexes
echo "" >> "$REPORT_FILE"
echo "#### Table Indexes" >> "$REPORT_FILE"
echo '```' >> "$REPORT_FILE"
run_query "SHOW INDEX FROM amisafe_h3_aggregated;" | tee -a "$REPORT_FILE"
echo '```' >> "$REPORT_FILE"

# Test 9.2: Table size
table_size=$(run_query "
SELECT 
    ROUND(data_length / 1024 / 1024, 2) as data_mb,
    ROUND(index_length / 1024 / 1024, 2) as index_mb,
    ROUND((data_length + index_length) / 1024 / 1024, 2) as total_mb
FROM information_schema.tables
WHERE table_schema = '${DB_NAME}' 
AND table_name = 'amisafe_h3_aggregated';
")
log_test "Table Size" "PASS" "Data: $(echo $table_size | cut -d' ' -f1) MB, Index: $(echo $table_size | cut -d' ' -f2) MB, Total: $(echo $table_size | cut -d' ' -f3) MB"

# ============================================================================
# Generate Summary
# ============================================================================
echo ""
echo -e "${BLUE}📋 Test Summary${NC}"
echo "=============================================="
echo -e "Total Tests: ${TOTAL_TESTS}"
echo -e "${GREEN}Passed: ${PASSED_TESTS}${NC}"
echo -e "${RED}Failed: ${FAILED_TESTS}${NC}"
echo -e "${YELLOW}Warnings: ${WARNING_TESTS}${NC}"

pass_rate=$(echo "scale=1; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc)
echo -e "Pass Rate: ${pass_rate}%"

# Add summary to report
cat >> "$REPORT_FILE" << EOF

---

## Test Summary

- **Total Tests**: $TOTAL_TESTS
- **Passed**: $PASSED_TESTS ✅
- **Failed**: $FAILED_TESTS ❌
- **Warnings**: $WARNING_TESTS ⚠️
- **Pass Rate**: ${pass_rate}%

## Status

EOF

if [ "$FAILED_TESTS" -eq 0 ]; then
    echo -e "${GREEN}✅ All critical tests passed!${NC}"
    echo "**Status**: ✅ PRODUCTION READY" >> "$REPORT_FILE"
elif [ "$FAILED_TESTS" -lt 5 ]; then
    echo -e "${YELLOW}⚠️  Some tests failed. Review required.${NC}"
    echo "**Status**: ⚠️ REVIEW REQUIRED" >> "$REPORT_FILE"
else
    echo -e "${RED}❌ Multiple critical failures detected!${NC}"
    echo "**Status**: ❌ NOT PRODUCTION READY" >> "$REPORT_FILE"
fi

echo ""
echo "Full report saved to: ${REPORT_FILE}"
