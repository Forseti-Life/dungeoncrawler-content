#!/bin/bash

# AmISafe Crime Map Filter Validation Script
# Comprehensive testing of all filter controls with positive and negative test cases

# Configuration
BASE_URL="http://localhost"
CRIME_MAP_URL="${BASE_URL}/amisafe/crime-map"
API_BASE="${BASE_URL}/api/amisafe"
REPORT_DIR="/workspaces/stlouisintegration.com/testing/amisafe"
REPORT_FILE="${REPORT_DIR}/filter_validation_report_$(date +%Y%m%d_%H%M%S).md"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🧪 AmISafe Crime Map Filter Validation${NC}"
echo "=============================================="
echo "Report: ${REPORT_FILE}"
echo

# Initialize report
cat > "$REPORT_FILE" << EOF
# AmISafe Crime Map Filter Validation Report
**Generated**: $(date)  
**Environment**: Development (localhost)  
**Database**: stlouisintegration_dev  

## Test Results Summary

EOF

# Test counter
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Helper function to log test result
log_test() {
    local test_name="$1"
    local status="$2"
    local details="$3"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✅ PASS${NC}: $test_name"
        PASSED_TESTS=$((PASSED_TESTS + 1))
        echo "- ✅ **PASS**: $test_name" >> "$REPORT_FILE"
    else
        echo -e "${RED}❌ FAIL${NC}: $test_name"
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo "- ❌ **FAIL**: $test_name" >> "$REPORT_FILE"
    fi
    
    if [ -n "$details" ]; then
        echo "  $details"
        echo "  - Details: $details" >> "$REPORT_FILE"
    fi
    echo >> "$REPORT_FILE"
}

# Test API endpoint availability
echo -e "${BLUE}📡 Testing API Endpoint Availability${NC}"
echo "---"

test_api_endpoint() {
    local endpoint="$1"
    local description="$2"
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "$endpoint")
    
    if [ "$response" = "200" ]; then
        log_test "API Endpoint: $description" "PASS" "HTTP $response"
    else
        log_test "API Endpoint: $description" "FAIL" "HTTP $response"
    fi
}

# Test core API endpoints
test_api_endpoint "${API_BASE}/aggregated" "H3 Aggregated Data"
test_api_endpoint "${API_BASE}/incidents" "Crime Incidents"
test_api_endpoint "${API_BASE}/crime-types" "Crime Types"
test_api_endpoint "${API_BASE}/districts" "Police Districts"
test_api_endpoint "${API_BASE}/system-stats" "System Statistics"

echo
echo -e "${BLUE}🎛️ Testing Filter API Parameters${NC}"
echo "---"

# Test 1: Crime Type Filter
echo -e "${YELLOW}Testing Crime Type Filter...${NC}"

# Positive test: Valid crime type
response=$(curl -s "${API_BASE}/aggregated?crime_types=1400,300")
if echo "$response" | jq -e '.hexagons' > /dev/null 2>&1; then
    count=$(echo "$response" | jq '.hexagons | length')
    log_test "Crime Type Filter - Valid Types" "PASS" "Returned $count hexagons for UCR codes 1400,300"
else
    log_test "Crime Type Filter - Valid Types" "FAIL" "Invalid JSON response or missing hexagons array"
fi

# Negative test: Invalid crime type
response=$(curl -s "${API_BASE}/aggregated?crime_types=99999")
if echo "$response" | jq -e '.hexagons' > /dev/null 2>&1; then
    count=$(echo "$response" | jq '.hexagons | length')
    if [ "$count" = "0" ]; then
        log_test "Crime Type Filter - Invalid Type" "PASS" "Correctly returned 0 results for invalid UCR code"
    else
        log_test "Crime Type Filter - Invalid Type" "FAIL" "Should return 0 results for invalid UCR code, got $count"
    fi
else
    log_test "Crime Type Filter - Invalid Type" "FAIL" "Invalid JSON response"
fi

# Test 2: District Filter
echo -e "${YELLOW}Testing District Filter...${NC}"

# Positive test: Valid district
response=$(curl -s "${API_BASE}/aggregated?districts=15,12")
if echo "$response" | jq -e '.hexagons' > /dev/null 2>&1; then
    count=$(echo "$response" | jq '.hexagons | length')
    log_test "District Filter - Valid Districts" "PASS" "Returned $count hexagons for districts 15,12"
else
    log_test "District Filter - Valid Districts" "FAIL" "Invalid JSON response"
fi

# Negative test: Invalid district
response=$(curl -s "${API_BASE}/aggregated?districts=999")
if echo "$response" | jq -e '.hexagons' > /dev/null 2>&1; then
    count=$(echo "$response" | jq '.hexagons | length')
    if [ "$count" = "0" ]; then
        log_test "District Filter - Invalid District" "PASS" "Correctly returned 0 results for invalid district"
    else
        log_test "District Filter - Invalid District" "FAIL" "Should return 0 results for invalid district, got $count"
    fi
else
    log_test "District Filter - Invalid District" "FAIL" "Invalid JSON response"
fi

# Test 3: Date Range Filter
echo -e "${YELLOW}Testing Date Range Filter...${NC}"

# Positive test: Valid date range (2024 data)
response=$(curl -s "${API_BASE}/aggregated?start_date=2024-01-01&end_date=2024-03-31")
if echo "$response" | jq -e '.hexagons' > /dev/null 2>&1; then
    count=$(echo "$response" | jq '.hexagons | length')
    log_test "Date Range Filter - Q1 2024" "PASS" "Returned $count hexagons for Q1 2024"
else
    log_test "Date Range Filter - Q1 2024" "FAIL" "Invalid JSON response"
fi

# Negative test: Invalid date range (end before start)
response=$(curl -s "${API_BASE}/aggregated?start_date=2024-12-31&end_date=2024-01-01")
if echo "$response" | jq -e '.hexagons' > /dev/null 2>&1; then
    count=$(echo "$response" | jq '.hexagons | length')
    log_test "Date Range Filter - Invalid Range" "PASS" "Handled invalid date range, returned $count hexagons"
else
    log_test "Date Range Filter - Invalid Range" "FAIL" "Invalid JSON response"
fi

# Test 4: Time Period Filter
echo -e "${YELLOW}Testing Time Period Filter...${NC}"

# Positive test: Morning hours
response=$(curl -s "${API_BASE}/aggregated?time_periods=morning")
if echo "$response" | jq -e '.hexagons' > /dev/null 2>&1; then
    count=$(echo "$response" | jq '.hexagons | length')
    log_test "Time Period Filter - Morning Hours" "PASS" "Returned $count hexagons for morning period"
else
    log_test "Time Period Filter - Morning Hours" "FAIL" "Invalid JSON response"
fi

# Test 5: Resolution Parameter
echo -e "${YELLOW}Testing H3 Resolution Parameter...${NC}"

# Test different resolutions
for resolution in 6 9 13; do
    response=$(curl -s "${API_BASE}/aggregated?resolution=$resolution")
    if echo "$response" | jq -e '.hexagons' > /dev/null 2>&1; then
        count=$(echo "$response" | jq '.hexagons | length')
        reported_res=$(echo "$response" | jq -r '.meta.resolution // "null"')
        
        if [ "$reported_res" = "$resolution" ]; then
            log_test "H3 Resolution $resolution" "PASS" "Returned $count hexagons at resolution $resolution"
        else
            log_test "H3 Resolution $resolution" "FAIL" "Expected resolution $resolution, got $reported_res"
        fi
    else
        log_test "H3 Resolution $resolution" "FAIL" "Invalid JSON response"
    fi
done

# Test 6: Combined Filters
echo -e "${YELLOW}Testing Combined Filters...${NC}"

# Positive test: Multiple filters combined
response=$(curl -s "${API_BASE}/aggregated?resolution=9&crime_types=1400&districts=15&start_date=2024-06-01&end_date=2024-08-31")
if echo "$response" | jq -e '.hexagons' > /dev/null 2>&1; then
    count=$(echo "$response" | jq '.hexagons | length')
    log_test "Combined Filters - Multiple Parameters" "PASS" "Returned $count hexagons with multiple filters"
else
    log_test "Combined Filters - Multiple Parameters" "FAIL" "Invalid JSON response"
fi

# Test 7: Performance with High Resolution
echo -e "${YELLOW}Testing Performance...${NC}"

start_time=$(date +%s.%N)
response=$(curl -s "${API_BASE}/aggregated?resolution=13&limit=1000")
end_time=$(date +%s.%N)
duration=$(echo "$end_time - $start_time" | bc)

if echo "$response" | jq -e '.hexagons' > /dev/null 2>&1; then
    count=$(echo "$response" | jq '.hexagons | length')
    if (( $(echo "$duration < 5.0" | bc -l) )); then
        log_test "Performance - High Resolution Query" "PASS" "Completed in ${duration}s, returned $count hexagons"
    else
        log_test "Performance - High Resolution Query" "FAIL" "Too slow: ${duration}s for $count hexagons"
    fi
else
    log_test "Performance - High Resolution Query" "FAIL" "Invalid JSON response"
fi

# Test 8: Error Handling
echo -e "${YELLOW}Testing Error Handling...${NC}"

# Test malformed parameters
response=$(curl -s "${API_BASE}/aggregated?resolution=invalid&crime_types=not_a_number")
http_code=$(curl -s -o /dev/null -w "%{http_code}" "${API_BASE}/aggregated?resolution=invalid")

if [ "$http_code" = "200" ] || [ "$http_code" = "400" ]; then
    if echo "$response" | jq -e '.error // .hexagons' > /dev/null 2>&1; then
        log_test "Error Handling - Invalid Parameters" "PASS" "Handled invalid parameters gracefully (HTTP $http_code)"
    else
        log_test "Error Handling - Invalid Parameters" "FAIL" "No proper error response or fallback"
    fi
else
    log_test "Error Handling - Invalid Parameters" "FAIL" "Unexpected HTTP code: $http_code"
fi

echo
echo -e "${BLUE}📊 Frontend Filter Controls Test${NC}"
echo "---"

# Test that the crime map page loads
if curl -s "$CRIME_MAP_URL" | grep -q "crime-map-container"; then
    log_test "Crime Map Page Load" "PASS" "Page contains required map container element"
else
    log_test "Crime Map Page Load" "FAIL" "Page missing map container or failed to load"
fi

# Test that required filter elements exist
if curl -s "$CRIME_MAP_URL" | grep -q 'id="crime-type-selector"'; then
    log_test "Crime Type Selector Element" "PASS" "Element found in page HTML"
else
    log_test "Crime Type Selector Element" "FAIL" "Element missing from page"
fi

if curl -s "$CRIME_MAP_URL" | grep -q 'id="district-selector"'; then
    log_test "District Selector Element" "PASS" "Element found in page HTML"
else
    log_test "District Selector Element" "FAIL" "Element missing from page"
fi

if curl -s "$CRIME_MAP_URL" | grep -q 'id="start-date"'; then
    log_test "Start Date Picker Element" "PASS" "Date input found in page HTML"
else
    log_test "Start Date Picker Element" "FAIL" "Date input missing from page"
fi

if curl -s "$CRIME_MAP_URL" | grep -q 'id="end-date"'; then
    log_test "End Date Picker Element" "PASS" "Date input found in page HTML"
else
    log_test "End Date Picker Element" "FAIL" "Date input missing from page"
fi

if curl -s "$CRIME_MAP_URL" | grep -q 'id="apply-filters"'; then
    log_test "Apply Filters Button" "PASS" "Button found in page HTML"
else
    log_test "Apply Filters Button" "FAIL" "Button missing from page"
fi

# Finalize report
echo >> "$REPORT_FILE"
echo "## Test Summary" >> "$REPORT_FILE"
echo "- **Total Tests**: $TOTAL_TESTS" >> "$REPORT_FILE"
echo "- **Passed**: $PASSED_TESTS" >> "$REPORT_FILE"
echo "- **Failed**: $FAILED_TESTS" >> "$REPORT_FILE"
echo "- **Success Rate**: $(echo "scale=1; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc)%" >> "$REPORT_FILE"

# Print final summary
echo
echo "=============================================="
echo -e "${BLUE}📋 Test Summary${NC}"
echo "Total Tests: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$FAILED_TESTS${NC}"
echo -e "Success Rate: ${YELLOW}$(echo "scale=1; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc)%${NC}"
echo
echo "Detailed report saved to: $REPORT_FILE"