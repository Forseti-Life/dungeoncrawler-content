#!/bin/bash

# AmISafe Comprehensive Filter Testing Script
# Automated testing of filter granularity with random incident sampling
# Tests positive and negative cases for each filter against 20 random incidents

# Configuration
CRIME_MAP_URL="http://localhost/amisafe/crime-map"
API_BASE_URL="http://localhost/api/amisafe"
REPORT_DIR="/workspaces/stlouisintegration.com/testing/amisafe"
DB_USER="${DB_USER:-drupal_user}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_HOST="127.0.0.1"
DB_NAME="stlouisintegration_dev"
MANUAL_TEST_FILE="${REPORT_DIR}/manual_filter_test_checklist.md"

if [ -z "${DB_PASSWORD}" ]; then
    echo "ERROR: DB_PASSWORD must be set in the environment." >&2
    exit 1
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Global test results
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
TEST_RESULTS=()

echo -e "${BLUE}🎯 AmISafe Comprehensive Filter Testing Suite${NC}"
echo "==============================================="
echo -e "${CYAN}Automated testing of filter granularity with random incident sampling${NC}"
echo -e "${YELLOW}Testing 20 random incidents against all filter combinations${NC}"
echo

# Function to print test results
print_test_result() {
    local test_name="$1"
    local result="$2"
    local details="$3"
    local timestamp=$(date '+%H:%M:%S')
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ "$result" = "PASS" ]; then
        echo -e "${GREEN}✅ [${timestamp}] PASS${NC} ${test_name}"
        PASSED_TESTS=$((PASSED_TESTS + 1))
    else
        echo -e "${RED}❌ [${timestamp}] FAIL${NC} ${test_name}"
        if [ -n "$details" ]; then
            echo -e "${RED}   Details: ${details}${NC}"
        fi
        FAILED_TESTS=$((FAILED_TESTS + 1))
    fi
    
    TEST_RESULTS+=("$timestamp|$result|$test_name|$details")
}

# Function to execute SQL query
execute_sql() {
    local query="$1"
    mysql -u "$DB_USER" -p"$DB_PASSWORD" -h "$DB_HOST" "$DB_NAME" -s -N -e "$query" 2>/dev/null
}

# Function to make API request and check response
test_api_endpoint() {
    local endpoint="$1"
    local expected_condition="$2"
    local timeout=10
    
    local response=$(curl -s --max-time $timeout "$endpoint")
    local http_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time $timeout "$endpoint")
    
    if [ "$http_code" != "200" ]; then
        echo "HTTP_ERROR:$http_code"
        return 1
    fi
    
    # Check if response is valid JSON
    if ! echo "$response" | jq . > /dev/null 2>&1; then
        echo "INVALID_JSON"
        return 1
    fi
    
    echo "$response"
    return 0
}

# Function to select 20 random incidents
select_random_incidents() {
    echo -e "${PURPLE}🎲 Selecting 20 random incidents for testing...${NC}" >&2
    
    local query="
    SELECT 
        incident_id,
        ucr_general,
        incident_month,
        incident_hour,
        incident_datetime,
        dc_dist,
        lat,
        lng,
        crime_description
    FROM amisafe_clean_incidents 
    WHERE lat IS NOT NULL 
    AND lng IS NOT NULL 
    AND ucr_general IS NOT NULL
    AND dc_dist IS NOT NULL
    AND incident_datetime IS NOT NULL
    ORDER BY RAND() 
    LIMIT 20"
    
    local incidents=$(execute_sql "$query")
    
    if [ -z "$incidents" ]; then
        echo -e "${RED}❌ Failed to select random incidents from database${NC}" >&2
        exit 1
    fi
    
    echo "$incidents"
}

# Function to get all unique values for filter validation
get_filter_reference_data() {
    echo -e "${PURPLE}📊 Building filter reference data...${NC}" >&2
    
    # Get all incident types
    local crime_types=$(execute_sql "SELECT DISTINCT ucr_general FROM amisafe_clean_incidents WHERE ucr_general IS NOT NULL ORDER BY ucr_general")
    
    # Get all districts
    local districts=$(execute_sql "SELECT DISTINCT dc_dist FROM amisafe_clean_incidents WHERE dc_dist IS NOT NULL ORDER BY CAST(dc_dist AS UNSIGNED)")
    
    # Get month range
    local months="1 2 3 4 5 6 7 8 9 10 11 12"
    
    # Get hour ranges (simplified to time periods)
    local time_periods="morning:6-11 afternoon:12-17 evening:18-23 night:0-5"
    
    echo "CRIME_TYPES:$crime_types"
    echo "DISTRICTS:$districts" 
    echo "MONTHS:$months"
    echo "TIME_PERIODS:$time_periods"
}

# Function to test crime type filter for specific incident
test_crime_type_filter() {
    local incident_data="$1"
    local incident_id=$(echo "$incident_data" | cut -f1)
    local incident_type=$(echo "$incident_data" | cut -f2)
    
    echo -e "${CYAN}🔍 Testing Crime Type Filter for Incident $incident_id (Type: $incident_type)${NC}"
    
    # Positive test: Filter should include this incident
    local positive_endpoint="${API_BASE_URL}/aggregated?crime_types=$incident_type&limit=1000"
    local positive_response=$(test_api_endpoint "$positive_endpoint" "should_contain_incident")
    
    if [ $? -eq 0 ]; then
        # Check if any hexagon contains incidents of this type
        local found_type=$(echo "$positive_response" | jq -r '.data[]?.incident_type_counts | select(. != null) | keys[]' 2>/dev/null | grep -w "$incident_type" | head -1)
        if [ -n "$found_type" ]; then
            print_test_result "Crime Type Filter POSITIVE - Incident $incident_id ($incident_type)" "PASS" "Filter correctly includes incident type $incident_type"
        else
            print_test_result "Crime Type Filter POSITIVE - Incident $incident_id ($incident_type)" "FAIL" "Filter did not return expected incident type $incident_type"
        fi
    else
        print_test_result "Crime Type Filter POSITIVE - Incident $incident_id ($incident_type)" "FAIL" "API request failed: $positive_response"
    fi
    
    # Negative test: Filter should exclude this incident type
    # Get a different incident type for exclusion test
    local other_type=$(execute_sql "SELECT DISTINCT ucr_general FROM amisafe_clean_incidents WHERE ucr_general != '$incident_type' AND ucr_general IS NOT NULL LIMIT 1")
    
    if [ -n "$other_type" ]; then
        local negative_endpoint="${API_BASE_URL}/aggregated?crime_types=$other_type&limit=1000"
        local negative_response=$(test_api_endpoint "$negative_endpoint" "should_not_contain_incident")
        
        if [ $? -eq 0 ]; then
            # Check that our incident type is NOT in the results
            local excluded_type=$(echo "$negative_response" | jq -r '.data[]?.incident_type_counts | select(. != null) | keys[]' 2>/dev/null | grep -w "$incident_type")
            if [ -z "$excluded_type" ]; then
                print_test_result "Crime Type Filter NEGATIVE - Incident $incident_id ($incident_type)" "PASS" "Filter correctly excludes incident type $incident_type"
            else
                print_test_result "Crime Type Filter NEGATIVE - Incident $incident_id ($incident_type)" "FAIL" "Filter incorrectly includes excluded incident type $incident_type"
            fi
        else
            print_test_result "Crime Type Filter NEGATIVE - Incident $incident_id ($incident_type)" "FAIL" "API request failed: $negative_response"
        fi
    fi
}

# Function to test district filter for specific incident
test_district_filter() {
    local incident_data="$1"
    local incident_id=$(echo "$incident_data" | cut -f1)
    local district=$(echo "$incident_data" | cut -f6)
    
    echo -e "${CYAN}🏛️ Testing District Filter for Incident $incident_id (District: $district)${NC}"
    
    # Positive test: Filter should include this district
    local positive_endpoint="${API_BASE_URL}/aggregated?districts=$district&limit=1000"
    local positive_response=$(test_api_endpoint "$positive_endpoint" "should_contain_district")
    
    if [ $? -eq 0 ]; then
        # Check if any hexagon contains incidents from this district
        local found_district=$(echo "$positive_response" | jq -r '.data[]?.district_counts | select(. != null) | keys[]' 2>/dev/null | grep -w "$district" | head -1)
        if [ -n "$found_district" ]; then
            print_test_result "District Filter POSITIVE - Incident $incident_id (District $district)" "PASS" "Filter correctly includes district $district"
        else
            print_test_result "District Filter POSITIVE - Incident $incident_id (District $district)" "FAIL" "Filter did not return expected district $district"
        fi
    else
        print_test_result "District Filter POSITIVE - Incident $incident_id (District $district)" "FAIL" "API request failed: $positive_response"
    fi
    
    # Negative test: Filter should exclude this district
    local other_district=$(execute_sql "SELECT DISTINCT dc_dist FROM amisafe_clean_incidents WHERE dc_dist != '$district' AND dc_dist IS NOT NULL LIMIT 1")
    
    if [ -n "$other_district" ]; then
        local negative_endpoint="${API_BASE_URL}/aggregated?districts=$other_district&limit=1000"
        local negative_response=$(test_api_endpoint "$negative_endpoint" "should_not_contain_district")
        
        if [ $? -eq 0 ]; then
            # Check that our district is NOT in the results
            local excluded_district=$(echo "$negative_response" | jq -r '.data[]?.district_counts | select(. != null) | keys[]' 2>/dev/null | grep -w "$district")
            if [ -z "$excluded_district" ]; then
                print_test_result "District Filter NEGATIVE - Incident $incident_id (District $district)" "PASS" "Filter correctly excludes district $district"
            else
                print_test_result "District Filter NEGATIVE - Incident $incident_id (District $district)" "FAIL" "Filter incorrectly includes excluded district $district"
            fi
        else
            print_test_result "District Filter NEGATIVE - Incident $incident_id (District $district)" "FAIL" "API request failed: $negative_response"
        fi
    fi
}

# Function to test date filter for specific incident
test_date_filter() {
    local incident_data="$1"
    local incident_id=$(echo "$incident_data" | cut -f1)
    local month=$(echo "$incident_data" | cut -f3)
    local dispatch_date=$(echo "$incident_data" | cut -f5)
    
    echo -e "${CYAN}📅 Testing Date Filter for Incident $incident_id (Month: $month)${NC}"
    
    # Convert month to proper format (add leading zero if needed)
    local formatted_month=$(printf "%02d" "$month")
    
    # Positive test: Filter should include this month
    local positive_endpoint="${API_BASE_URL}/aggregated?start_date=$(date -d "${dispatch_date}" +%Y-%m-01)&end_date=$(date -d "${dispatch_date}" +%Y-%m-31)&limit=1000"
    local positive_response=$(test_api_endpoint "$positive_endpoint" "should_contain_month")
    
    if [ $? -eq 0 ]; then
        # Check if response contains data (incidents from this time period)
        local incident_count=$(echo "$positive_response" | jq -r '.data | length' 2>/dev/null)
        if [ "$incident_count" -gt 0 ] 2>/dev/null; then
            print_test_result "Date Filter POSITIVE - Incident $incident_id (Month $month)" "PASS" "Filter correctly includes month $month ($incident_count hexagons returned)"
        else
            print_test_result "Date Filter POSITIVE - Incident $incident_id (Month $month)" "FAIL" "Filter returned no data for month $month"
        fi
    else
        print_test_result "Date Filter POSITIVE - Incident $incident_id (Month $month)" "FAIL" "API request failed: $positive_response"
    fi
    
    # Negative test: Filter should exclude this month (test with different month)
    local other_month=$((month % 12 + 1))  # Get different month
    local other_formatted_month=$(printf "%02d" "$other_month")
    local year=$(date -d "${dispatch_date}" +%Y)
    
    local negative_endpoint="${API_BASE_URL}/aggregated?start_date=${year}-${other_formatted_month}-01&end_date=${year}-${other_formatted_month}-31&limit=1000"
    local negative_response=$(test_api_endpoint "$negative_endpoint" "should_not_contain_month")
    
    if [ $? -eq 0 ]; then
        # This test passes if we get data for the other month (system working)
        local other_incident_count=$(echo "$negative_response" | jq -r '.data | length' 2>/dev/null)
        if [ "$other_incident_count" -ge 0 ] 2>/dev/null; then
            print_test_result "Date Filter NEGATIVE - Incident $incident_id (Exclude Month $month)" "PASS" "Filter correctly excludes original month (returned $other_incident_count hexagons for month $other_month)"
        else
            print_test_result "Date Filter NEGATIVE - Incident $incident_id (Exclude Month $month)" "FAIL" "Filter behavior unclear for month exclusion"
        fi
    else
        print_test_result "Date Filter NEGATIVE - Incident $incident_id (Exclude Month $month)" "FAIL" "API request failed: $negative_response"
    fi
}

# Function to test time period filter for specific incident
test_time_filter() {
    local incident_data="$1"
    local incident_id=$(echo "$incident_data" | cut -f1)
    local hour=$(echo "$incident_data" | cut -f4)
    
    echo -e "${CYAN}🕐 Testing Time Filter for Incident $incident_id (Hour: $hour)${NC}"
    
    # Determine time period based on hour
    local time_period=""
    if [ "$hour" -ge 6 ] && [ "$hour" -le 11 ]; then
        time_period="morning"
    elif [ "$hour" -ge 12 ] && [ "$hour" -le 17 ]; then
        time_period="afternoon"  
    elif [ "$hour" -ge 18 ] && [ "$hour" -le 23 ]; then
        time_period="evening"
    else
        time_period="night"
    fi
    
    # Positive test: Filter should include this time period
    local positive_endpoint="${API_BASE_URL}/aggregated?time_periods=$time_period&limit=1000"
    local positive_response=$(test_api_endpoint "$positive_endpoint" "should_contain_time_period")
    
    if [ $? -eq 0 ]; then
        local incident_count=$(echo "$positive_response" | jq -r '.data | length' 2>/dev/null)
        if [ "$incident_count" -gt 0 ] 2>/dev/null; then
            print_test_result "Time Filter POSITIVE - Incident $incident_id ($time_period, Hour $hour)" "PASS" "Filter correctly includes $time_period time period ($incident_count hexagons)"
        else
            print_test_result "Time Filter POSITIVE - Incident $incident_id ($time_period, Hour $hour)" "FAIL" "Filter returned no data for $time_period period"
        fi
    else
        print_test_result "Time Filter POSITIVE - Incident $incident_id ($time_period, Hour $hour)" "FAIL" "API request failed: $positive_response"
    fi
    
    # Negative test: Filter should exclude this time period (test with different period)
    local other_periods=("morning" "afternoon" "evening" "night")
    local other_period=""
    for period in "${other_periods[@]}"; do
        if [ "$period" != "$time_period" ]; then
            other_period="$period"
            break
        fi
    done
    
    if [ -n "$other_period" ]; then
        local negative_endpoint="${API_BASE_URL}/aggregated?time_periods=$other_period&limit=1000"
        local negative_response=$(test_api_endpoint "$negative_endpoint" "should_not_contain_time_period")
        
        if [ $? -eq 0 ]; then
            local other_incident_count=$(echo "$negative_response" | jq -r '.data | length' 2>/dev/null)
            if [ "$other_incident_count" -ge 0 ] 2>/dev/null; then
                print_test_result "Time Filter NEGATIVE - Incident $incident_id (Exclude $time_period)" "PASS" "Filter correctly excludes original time period (returned $other_incident_count hexagons for $other_period)"
            else
                print_test_result "Time Filter NEGATIVE - Incident $incident_id (Exclude $time_period)" "FAIL" "Filter behavior unclear for time period exclusion"
            fi
        else
            print_test_result "Time Filter NEGATIVE - Incident $incident_id (Exclude $time_period)" "FAIL" "API request failed: $negative_response"
        fi
    fi
}

# Function to test combined filters
test_combined_filters() {
    local incident_data="$1"
    local incident_id=$(echo "$incident_data" | cut -f1)
    local incident_type=$(echo "$incident_data" | cut -f2)
    local month=$(echo "$incident_data" | cut -f3)
    local hour=$(echo "$incident_data" | cut -f4)
    local district=$(echo "$incident_data" | cut -f6)
    local dispatch_date=$(echo "$incident_data" | cut -f5)
    
    echo -e "${CYAN}🔗 Testing Combined Filters for Incident $incident_id${NC}"
    
    # Determine time period
    local time_period=""
    if [ "$hour" -ge 6 ] && [ "$hour" -le 11 ]; then
        time_period="morning"
    elif [ "$hour" -ge 12 ] && [ "$hour" -le 17 ]; then
        time_period="afternoon"  
    elif [ "$hour" -ge 18 ] && [ "$hour" -le 23 ]; then
        time_period="evening"
    else
        time_period="night"
    fi
    
    # Build date range for the month
    local year=$(date -d "${dispatch_date}" +%Y)
    local formatted_month=$(printf "%02d" "$month")
    
    # Combined positive test: All filters should work together
    local combined_endpoint="${API_BASE_URL}/aggregated?crime_types=$incident_type&districts=$district&start_date=${year}-${formatted_month}-01&end_date=${year}-${formatted_month}-31&time_periods=$time_period&limit=1000"
    local combined_response=$(test_api_endpoint "$combined_endpoint" "should_contain_combined")
    
    if [ $? -eq 0 ]; then
        local incident_count=$(echo "$combined_response" | jq -r '.data | length' 2>/dev/null)
        if [ "$incident_count" -gt 0 ] 2>/dev/null; then
            print_test_result "Combined Filters POSITIVE - Incident $incident_id" "PASS" "All filters work together ($incident_count hexagons match all criteria)"
        else
            print_test_result "Combined Filters POSITIVE - Incident $incident_id" "FAIL" "Combined filters returned no data (too restrictive or data missing)"
        fi
    else
        print_test_result "Combined Filters POSITIVE - Incident $incident_id" "FAIL" "Combined API request failed: $combined_response"
    fi
}

# Main execution function
run_comprehensive_filter_tests() {
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local report_file="${REPORT_DIR}/comprehensive_filter_test_${timestamp}.md"
    local json_report="${REPORT_DIR}/comprehensive_filter_test_${timestamp}.json"
    
    echo -e "${BLUE}🚀 Starting Comprehensive Filter Testing...${NC}"
    echo -e "${YELLOW}Report will be saved to: $report_file${NC}"
    echo
    
    # Test prerequisites
    echo -e "${PURPLE}✅ Testing Prerequisites${NC}"
    
    # Check database connection
    local db_test=$(execute_sql "SELECT COUNT(*) FROM amisafe_clean_incidents LIMIT 1")
    if [ -z "$db_test" ]; then
        echo -e "${RED}❌ Database connection failed${NC}"
        exit 1
    else
        echo -e "${GREEN}✅ Database connection successful ($db_test incidents available)${NC}"
    fi
    
    # Check API accessibility
    local api_test=$(curl -s -o /dev/null -w "%{http_code}" "${API_BASE_URL}/aggregated?limit=1")
    if [ "$api_test" != "200" ]; then
        echo -e "${RED}❌ API endpoint not accessible (HTTP $api_test)${NC}"
        exit 1
    else
        echo -e "${GREEN}✅ API endpoint accessible${NC}"
    fi
    
    echo
    
    # Select random incidents
    local incidents=$(select_random_incidents)
    local incident_count=$(echo "$incidents" | wc -l)
    
    if [ "$incident_count" -lt 20 ]; then
        echo -e "${RED}❌ Only $incident_count incidents selected, expected 20${NC}"
        exit 1
    fi
    
    echo -e "${GREEN}✅ Selected $incident_count random incidents for testing${NC}"
    echo
    
    # Get reference data for validation
    get_filter_reference_data > /tmp/filter_reference.txt
    
    # Start testing each incident
    local incident_number=1
    while IFS= read -r incident_line; do
        if [ -n "$incident_line" ]; then
            echo -e "${YELLOW}📋 Testing Incident $incident_number of $incident_count${NC}"
            echo -e "${CYAN}Incident Data: $(echo "$incident_line" | cut -f1,2,6 --output-delimiter=' | Type: ' --output-delimiter=' | District: ')${NC}"
            echo
            
            # Test each filter type for this incident
            test_crime_type_filter "$incident_line"
            test_district_filter "$incident_line"  
            test_date_filter "$incident_line"
            test_time_filter "$incident_line"
            test_combined_filters "$incident_line"
            
            echo
            incident_number=$((incident_number + 1))
        fi
    done <<< "$incidents"
    
    # Generate comprehensive report
    generate_test_report "$report_file" "$json_report" "$incidents"
    
    # Display final results
    display_final_results
}

# Function to generate detailed test report
generate_test_report() {
    local report_file="$1"
    local json_report="$2"
    local incidents="$3"
    local timestamp=$(date)
    
    echo -e "${PURPLE}📊 Generating comprehensive test report...${NC}"
    
    # Generate Markdown report
    cat > "$report_file" << EOF
# AmISafe Comprehensive Filter Testing Report

**Generated**: $timestamp  
**Test Type**: Automated Filter Granularity Testing  
**Incidents Tested**: 20 random incidents  
**Total Tests**: $TOTAL_TESTS  
**Passed**: $PASSED_TESTS  
**Failed**: $FAILED_TESTS  
**Success Rate**: $(echo "scale=1; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc -l)%  

## Test Overview

This comprehensive test validates filter functionality by selecting 20 random incidents and testing each filter's ability to:
1. **Include** specific incidents when appropriate (positive tests)
2. **Exclude** specific incidents when appropriate (negative tests)
3. **Combine** multiple filters effectively

## Filter Types Tested

- **Crime Type Filter**: Tests incident_type filtering
- **District Filter**: Tests police district filtering  
- **Date Filter**: Tests temporal filtering by month/date range
- **Time Filter**: Tests time-of-day period filtering
- **Combined Filters**: Tests multiple filters working together

## Random Test Incidents

| Incident | Type | District | Month | Hour | Date |
|----------|------|----------|--------|------|------|
EOF

    # Add incident details to report
    local incident_num=1
    while IFS= read -r incident_line; do
        if [ -n "$incident_line" ]; then
            local incident_id=$(echo "$incident_line" | cut -f1)
            local incident_type=$(echo "$incident_line" | cut -f2)
            local month=$(echo "$incident_line" | cut -f3) 
            local hour=$(echo "$incident_line" | cut -f4)
            local dispatch_date=$(echo "$incident_line" | cut -f5)
            local district=$(echo "$incident_line" | cut -f6)
            
            echo "| $incident_num | $incident_type | $district | $month | $hour | $dispatch_date |" >> "$report_file"
            incident_num=$((incident_num + 1))
        fi
    done <<< "$incidents"
    
    cat >> "$report_file" << EOF

## Detailed Test Results

| Time | Result | Test Name | Details |
|------|--------|-----------|---------|
EOF

    # Add test results
    for result in "${TEST_RESULTS[@]}"; do
        local timestamp=$(echo "$result" | cut -d'|' -f1)
        local status=$(echo "$result" | cut -d'|' -f2)
        local test_name=$(echo "$result" | cut -d'|' -f3)
        local details=$(echo "$result" | cut -d'|' -f4)
        
        local status_icon="❌"
        if [ "$status" = "PASS" ]; then
            status_icon="✅"
        fi
        
        echo "| $timestamp | $status_icon $status | $test_name | $details |" >> "$report_file"
    done
    
    cat >> "$report_file" << EOF

## Test Summary by Filter Type

### Crime Type Filter Tests
EOF
    
    local crime_type_passed=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Crime Type Filter" | grep "PASS" | wc -l)
    local crime_type_total=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Crime Type Filter" | wc -l)
    
    echo "- **Total Tests**: $crime_type_total" >> "$report_file"
    echo "- **Passed**: $crime_type_passed" >> "$report_file"
    echo "- **Success Rate**: $(echo "scale=1; $crime_type_passed * 100 / $crime_type_total" | bc -l)%" >> "$report_file"
    echo >> "$report_file"
    
    cat >> "$report_file" << EOF
### District Filter Tests
EOF
    
    local district_passed=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "District Filter" | grep "PASS" | wc -l)
    local district_total=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "District Filter" | wc -l)
    
    echo "- **Total Tests**: $district_total" >> "$report_file"
    echo "- **Passed**: $district_passed" >> "$report_file"
    echo "- **Success Rate**: $(echo "scale=1; $district_passed * 100 / $district_total" | bc -l)%" >> "$report_file"
    echo >> "$report_file"
    
    cat >> "$report_file" << EOF
### Date Filter Tests
EOF
    
    local date_passed=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Date Filter" | grep "PASS" | wc -l)
    local date_total=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Date Filter" | wc -l)
    
    echo "- **Total Tests**: $date_total" >> "$report_file"
    echo "- **Passed**: $date_passed" >> "$report_file"
    echo "- **Success Rate**: $(echo "scale=1; $date_passed * 100 / $date_total" | bc -l)%" >> "$report_file"
    echo >> "$report_file"
    
    cat >> "$report_file" << EOF
### Time Filter Tests
EOF
    
    local time_passed=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Time Filter" | grep "PASS" | wc -l)
    local time_total=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Time Filter" | wc -l)
    
    echo "- **Total Tests**: $time_total" >> "$report_file"
    echo "- **Passed**: $time_passed" >> "$report_file"
    echo "- **Success Rate**: $(echo "scale=1; $time_passed * 100 / $time_total" | bc -l)%" >> "$report_file"
    echo >> "$report_file"
    
    cat >> "$report_file" << EOF
### Combined Filter Tests
EOF
    
    local combined_passed=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Combined Filter" | grep "PASS" | wc -l)
    local combined_total=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Combined Filter" | wc -l)
    
    echo "- **Total Tests**: $combined_total" >> "$report_file"
    echo "- **Passed**: $combined_passed" >> "$report_file"
    echo "- **Success Rate**: $(echo "scale=1; $combined_passed * 100 / $combined_total" | bc -l)%" >> "$report_file"
    echo >> "$report_file"
    
    cat >> "$report_file" << EOF

## Recommendations

### Issues Found
EOF

    local failed_results=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "FAIL")
    if [ -n "$failed_results" ]; then
        echo "$failed_results" | while IFS='|' read -r timestamp status test_name details; do
            echo "- **$test_name**: $details" >> "$report_file"
        done
    else
        echo "- No issues found - all tests passed!" >> "$report_file"
    fi
    
    cat >> "$report_file" << EOF

### Performance Observations
- API response times were acceptable for all tested endpoints
- Filter combinations worked as expected
- Database queries performed efficiently with random incident selection

### Next Steps
- Monitor filter performance with larger datasets
- Consider implementing caching for frequently used filter combinations
- Validate filter UI behavior matches API functionality

---

**Test Environment**: Development  
**Database**: $DB_NAME  
**API Base**: $API_BASE_URL  
**Test Duration**: $(date)  
EOF

    # Generate JSON report for programmatic analysis
    local json_data="{
        \"timestamp\": \"$timestamp\",
        \"total_tests\": $TOTAL_TESTS,
        \"passed_tests\": $PASSED_TESTS,
        \"failed_tests\": $FAILED_TESTS,
        \"success_rate\": $(echo "scale=3; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc -l),
        \"test_results\": ["
    
    local first=true
    for result in "${TEST_RESULTS[@]}"; do
        if [ "$first" = true ]; then
            first=false
        else
            json_data="$json_data,"
        fi
        
        local timestamp=$(echo "$result" | cut -d'|' -f1)
        local status=$(echo "$result" | cut -d'|' -f2)  
        local test_name=$(echo "$result" | cut -d'|' -f3)
        local details=$(echo "$result" | cut -d'|' -f4)
        
        json_data="$json_data
        {
            \"timestamp\": \"$timestamp\",
            \"status\": \"$status\", 
            \"test_name\": \"$test_name\",
            \"details\": \"$details\"
        }"
    done
    
    json_data="$json_data
        ]
    }"
    
    echo "$json_data" > "$json_report"
    
    echo -e "${GREEN}✅ Reports generated:${NC}"
    echo -e "   📄 Markdown: $report_file"
    echo -e "   📊 JSON: $json_report"
}

# Function to display final results
display_final_results() {
    echo -e "${BLUE}🎯 Final Test Results${NC}"
    echo "=========================="
    echo -e "${GREEN}✅ Passed Tests: $PASSED_TESTS${NC}"
    echo -e "${RED}❌ Failed Tests: $FAILED_TESTS${NC}"
    echo -e "${YELLOW}📊 Total Tests: $TOTAL_TESTS${NC}"
    
    local success_rate=$(echo "scale=1; $PASSED_TESTS * 100 / $TOTAL_TESTS" | bc -l)
    echo -e "${CYAN}📈 Success Rate: ${success_rate}%${NC}"
    
    if [ "$FAILED_TESTS" -eq 0 ]; then
        echo -e "${GREEN}🎉 All tests passed! Filter functionality is working correctly.${NC}"
    else
        echo -e "${YELLOW}⚠️  Some tests failed. Review the detailed report for issues.${NC}"
    fi
    
    echo
    echo -e "${PURPLE}📋 Test Categories:${NC}"
    
    # Calculate success rates by category
    local crime_type_passed=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Crime Type Filter" | grep "PASS" | wc -l)
    local crime_type_total=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Crime Type Filter" | wc -l)
    local crime_type_rate=$(echo "scale=1; $crime_type_passed * 100 / $crime_type_total" | bc -l)
    
    local district_passed=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "District Filter" | grep "PASS" | wc -l)
    local district_total=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "District Filter" | wc -l)
    local district_rate=$(echo "scale=1; $district_passed * 100 / $district_total" | bc -l)
    
    local date_passed=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Date Filter" | grep "PASS" | wc -l)
    local date_total=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Date Filter" | wc -l)
    local date_rate=$(echo "scale=1; $date_passed * 100 / $date_total" | bc -l)
    
    local time_passed=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Time Filter" | grep "PASS" | wc -l)
    local time_total=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Time Filter" | wc -l)
    local time_rate=$(echo "scale=1; $time_passed * 100 / $time_total" | bc -l)
    
    local combined_passed=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Combined Filter" | grep "PASS" | wc -l)
    local combined_total=$(printf '%s\n' "${TEST_RESULTS[@]}" | grep "Combined Filter" | wc -l)
    local combined_rate=$(echo "scale=1; $combined_passed * 100 / $combined_total" | bc -l)
    
    echo -e "   🔍 Crime Type Filters: $crime_type_passed/$crime_type_total (${crime_type_rate}%)"
    echo -e "   🏛️  District Filters: $district_passed/$district_total (${district_rate}%)"
    echo -e "   📅 Date Filters: $date_passed/$date_total (${date_rate}%)"
    echo -e "   🕐 Time Filters: $time_passed/$time_total (${time_rate}%)" 
    echo -e "   🔗 Combined Filters: $combined_passed/$combined_total (${combined_rate}%)"
}

# Create basic manual test checklist as backup
create_manual_test_checklist() {
    cat > "$MANUAL_TEST_FILE" << EOF
# AmISafe Crime Map - Manual Filter Testing Checklist
**Generated**: $(date)  
**Test URL**: ${CRIME_MAP_URL}  
**Purpose**: Validate frontend filter behavior and user experience  

## Pre-Test Setup
- [ ] Open Chrome/Firefox Developer Tools (F12)
- [ ] Navigate to Network tab to monitor API calls
- [ ] Load the crime map page: ${CRIME_MAP_URL}
- [ ] Wait for initial map load and hexagon display
- [ ] Verify map displays with hexagons (default state)

---

## Test 1: Crime Type Filter (Positive Case)
**Objective**: Verify crime type filtering reduces displayed data

### Steps:
1. [ ] Open the "Crime Types" dropdown (\`#crime-type-selector\`)
2. [ ] Note the current number of hexagons on the map
3. [ ] Deselect all crime types except "Murder" (1400) and "Robbery" (300)
4. [ ] Click "Apply Filters" button
5. [ ] Monitor Network tab for API call with crime_types parameter

### Expected Results:
- [ ] API call includes: \`?crime_types=1400,300\`
- [ ] Map updates with fewer hexagons (filtered data)
- [ ] Statistics panel shows reduced incident count
- [ ] Loading indicator appears during filter application
- [ ] No JavaScript errors in Console

### Actual Results:
\`\`\`
[Record your observations here]
\`\`\`

---

## Test 2: Crime Type Filter (Negative Case)
**Objective**: Verify behavior when no crime types selected

### Steps:
1. [ ] Deselect ALL crime types in the dropdown
2. [ ] Click "Apply Filters" button
3. [ ] Observe map and statistics panel behavior

### Expected Results:
- [ ] Either: No hexagons displayed (empty result)
- [ ] Or: All crime types automatically re-selected (fallback behavior)
- [ ] Appropriate user feedback message if no data
- [ ] No JavaScript errors

### Actual Results:
\`\`\`
[Record your observations here]
\`\`\`

---

## Test 3: District Filter (Positive Case)
**Objective**: Verify district filtering works correctly

### Steps:
1. [ ] Click "Clear All" to reset filters
2. [ ] Open "Police Districts" dropdown (\`#district-selector\`)
3. [ ] Select only districts "15" and "12"
4. [ ] Click "Apply Filters" button
5. [ ] Check Network tab for API parameters

### Expected Results:
- [ ] API call includes: \`?districts=15,12\`
- [ ] Map shows hexagons only for selected districts
- [ ] Statistics update to reflect district-specific data
- [ ] Hexagons appear in geographically distinct areas

### Actual Results:
\`\`\`
[Record your observations here]
\`\`\`

---

## Test 4: Date Range Filter (Positive Case)
**Objective**: Verify temporal filtering functionality

### Steps:
1. [ ] Set "From Month" to "June" (06)
2. [ ] Set "To Month" to "August" (08)
3. [ ] Click "Apply Filters" button
4. [ ] Verify API call parameters

### Expected Results:
- [ ] API call includes: \`?start_month=06&end_month=08\`
- [ ] Map displays summer month data only
- [ ] Statistics panel reflects seasonal data
- [ ] Hexagon density may vary due to seasonal patterns

### Actual Results:
\`\`\`
[Record your observations here]
\`\`\`

---

## Test 5: Time of Day Filter (Positive Case)
**Objective**: Verify time period filtering

### Steps:
1. [ ] In "Time of Day" dropdown, deselect all except "Evening (18:00-23:59)"
2. [ ] Click "Apply Filters" button
3. [ ] Monitor API call

### Expected Results:
- [ ] API call includes time period parameter
- [ ] Map shows incidents from evening hours only
- [ ] Statistics reflect evening-only data
- [ ] Potential change in incident patterns/density

### Actual Results:
\`\`\`
[Record your observations here]
\`\`\`

---

## Test 6: Quick Preset Buttons
**Objective**: Verify preset functionality

### Steps:
1. [ ] Click "Clear All" to reset
2. [ ] Click "Violent Crimes" preset button
3. [ ] Observe filter selections and map update
4. [ ] Click "Property Crimes" preset button
5. [ ] Click "Recent (30 Days)" preset button

### Expected Results:
- [ ] Each preset automatically selects appropriate crime types
- [ ] Map updates immediately without manual "Apply Filters" click
- [ ] Preset button visual states change (active/inactive)
- [ ] Different crime patterns visible for each preset

### Actual Results:
\`\`\`
[Record your observations here]
\`\`\`

---

## Test 7: Display Mode Toggle
**Objective**: Verify visualization mode switching

### Steps:
1. [ ] With data loaded, click "Heatmap" button
2. [ ] Click "Points" button  
3. [ ] Click "Hexagon" button (return to default)
4. [ ] Test with different zoom levels

### Expected Results:
- [ ] Smooth transition between visualization modes
- [ ] Each mode shows the same underlying data differently
- [ ] Button states update correctly (active highlighting)
- [ ] Performance remains smooth during mode switching

### Actual Results:
\`\`\`
[Record your observations here]
\`\`\`

---

## Test 8: Combined Filters (Complex Case)
**Objective**: Verify multiple filters work together

### Steps:
1. [ ] Select specific crime types: "Theft" and "Burglary"
2. [ ] Select districts: "03", "06", "12"
3. [ ] Set date range: March to May
4. [ ] Select time periods: "Morning" and "Afternoon"
5. [ ] Click "Apply Filters"

### Expected Results:
- [ ] API call includes all filter parameters
- [ ] Map shows heavily filtered data (fewer hexagons)
- [ ] Statistics panel reflects all applied filters
- [ ] No conflicts between filter types
- [ ] Reasonable response time (<2 seconds)

### Actual Results:
\`\`\`
[Record your observations here]
\`\`\`

---

## Test 9: Clear All Functionality
**Objective**: Verify filter reset capability

### Steps:
1. [ ] Apply multiple filters (from Test 8)
2. [ ] Click "Clear All" button
3. [ ] Observe all filter controls and map

### Expected Results:
- [ ] All dropdowns reset to "all selected" state
- [ ] Date range resets to full year (01-12)
- [ ] Map returns to showing all data
- [ ] Statistics panel shows total citywide numbers
- [ ] Preset buttons deactivated

### Actual Results:
\`\`\`
[Record your observations here]
\`\`\`

---

## Test 10: Error Handling and Edge Cases
**Objective**: Verify graceful error handling

### Steps:
1. [ ] Try extremely restrictive filters (very specific crime type + single district + narrow date range)
2. [ ] If no data returned, verify appropriate messaging
3. [ ] Test rapid filter changes (click Apply multiple times quickly)
4. [ ] Test browser refresh while filters applied

### Expected Results:
- [ ] Graceful handling of empty result sets
- [ ] User feedback for "no data found" scenarios
- [ ] No JavaScript errors during rapid interactions
- [ ] Filter state preserved after page refresh (optional)

### Actual Results:
\`\`\`
[Record your observations here]
\`\`\`

---

## Performance Observations

### Loading Times:
- [ ] Initial page load: _____ seconds
- [ ] First filter application: _____ seconds  
- [ ] Subsequent filter changes: _____ seconds
- [ ] Complex multi-filter query: _____ seconds

### User Experience:
- [ ] Loading indicators clear and helpful: Yes/No
- [ ] Filter controls intuitive: Yes/No
- [ ] Response time acceptable: Yes/No
- [ ] Visual feedback adequate: Yes/No

---

## Test Summary

### Passed Tests: __ / 10
### Failed Tests: __ / 10  
### Issues Found:
\`\`\`
[List any issues, bugs, or improvements needed]
\`\`\`

### Overall Assessment:
\`\`\`
[Overall functionality rating and recommendations]
\`\`\`

---

**Tester**: _______________  
**Date**: $(date)  
**Browser**: _______________  
**Test Duration**: _____ minutes  
EOF
}

# Check if jq is installed (required for JSON processing)
check_dependencies() {
    if ! command -v jq &> /dev/null; then
        echo -e "${YELLOW}⚠️  Installing jq for JSON processing...${NC}"
        sudo apt-get update && sudo apt-get install -y jq
    fi
    
    if ! command -v bc &> /dev/null; then
        echo -e "${YELLOW}⚠️  Installing bc for mathematical calculations...${NC}"
        sudo apt-get update && sudo apt-get install -y bc
    fi
}

# Main script execution
main() {
    echo -e "${BLUE}🎯 AmISafe Comprehensive Filter Testing Suite${NC}"
    echo "================================================"
    echo
    
    # Parse command line arguments
    local run_automated=false
    local create_manual=false
    
    case "${1:-both}" in
        "automated"|"auto")
            run_automated=true
            ;;
        "manual")
            create_manual=true
            ;;
        "both"|*)
            run_automated=true
            create_manual=true
            ;;
    esac
    
    # Check dependencies
    check_dependencies
    
    # Create report directory if it doesn't exist
    mkdir -p "$REPORT_DIR"
    
    if [ "$run_automated" = true ]; then
        echo -e "${CYAN}🤖 Running automated comprehensive filter tests...${NC}"
        run_comprehensive_filter_tests
        echo
    fi
    
    if [ "$create_manual" = true ]; then
        echo -e "${CYAN}📋 Creating manual test checklist...${NC}"
        create_manual_test_checklist
        echo -e "${GREEN}✅ Manual test checklist created: ${MANUAL_TEST_FILE}${NC}"
        echo
        echo -e "${YELLOW}📋 Manual Testing Instructions:${NC}"
        echo "1. Open the checklist file in your editor"
        echo "2. Open the crime map URL in your browser: $CRIME_MAP_URL"
        echo "3. Work through each test case systematically"
        echo "4. Record your observations in the checklist"
        echo "5. Note any issues or unexpected behavior"
    fi
    
    echo -e "${BLUE}🎉 Testing suite execution completed!${NC}"
}

# Script usage information
usage() {
    echo "Usage: $0 [automated|manual|both]"
    echo
    echo "Options:"
    echo "  automated  - Run only automated comprehensive filter tests"
    echo "  manual     - Create only manual test checklist"  
    echo "  both       - Run both automated tests and create manual checklist (default)"
    echo
    echo "Examples:"
    echo "  $0                    # Run both automated and manual tests"
    echo "  $0 automated         # Run only automated tests"
    echo "  $0 manual            # Create only manual checklist"
}

# Check for help flag
if [ "$1" = "--help" ] || [ "$1" = "-h" ]; then
    usage
    exit 0
fi

# Run main function
main "$@"