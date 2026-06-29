#!/bin/bash

# Site Testing Script for Forseti.Life
# Tests all pages for CSS being displayed as text content
# Usage: ./test_all_pages.sh [prod|dev]
#   prod - Test production site (https://forseti.life)
#   dev  - Test local development (http://localhost) [default]

set -e

# Determine environment
ENVIRONMENT="${1:-dev}"

# Configuration based on environment
if [ "$ENVIRONMENT" = "prod" ] || [ "$ENVIRONMENT" = "production" ]; then
    BASE_URL="https://forseti.life"
    ENV_NAME="PRODUCTION"
elif [ "$ENVIRONMENT" = "dev" ] || [ "$ENVIRONMENT" = "local" ]; then
    BASE_URL="http://localhost"
    ENV_NAME="DEVELOPMENT"
else
    echo "Invalid environment: $ENVIRONMENT"
    echo "Usage: $0 [prod|dev]"
    echo "  prod - Test production site (https://forseti.life)"
    echo "  dev  - Test local development (http://localhost) [default]"
    exit 1
fi

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RESULTS_DIR="/home/keithaumiller/forseti.life/testing/results/results_${ENV_NAME}_${TIMESTAMP}"
REPORT_FILE="${RESULTS_DIR}/test_report.txt"
URLS_CONFIG_FILE="${RESULTS_DIR}/discovered_urls.txt"
ALL_LINKS_FILE="${RESULTS_DIR}/all_links_by_page.txt"

# Persistent URL configuration file (not timestamped)
PERSISTENT_URL_CONFIG="/home/keithaumiller/forseti.life/testing/test_urls_config.txt"

# Create results directory
mkdir -p "$RESULTS_DIR"

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Initialize counters
TOTAL_PAGES=0
PAGES_WITH_CSS_ISSUES=0
PAGES_CLEAN=0
TOTAL_LINKS_FOUND=0

echo -e "${BLUE}=== Forseti.Life Site Testing - $ENV_NAME ===${NC}"
echo "Testing all pages for CSS display issues..."
echo "Base URL: $BASE_URL"
echo "Results will be saved to: $RESULTS_DIR"
echo ""

# Initialize URL tracking files
touch "$URLS_CONFIG_FILE"
touch "$ALL_LINKS_FILE"

# Check if we should use the persistent URL config
USE_URL_CONFIG=false
if [ -f "$PERSISTENT_URL_CONFIG" ] && [ -s "$PERSISTENT_URL_CONFIG" ]; then
    url_count=$(wc -l < "$PERSISTENT_URL_CONFIG")
    echo -e "${YELLOW}Found existing URL config with $url_count URLs${NC}"
    echo -n -e "${BLUE}Use URL config for testing? (y/n) [default: n]: ${NC}"
    read -t 10 -n 1 use_config_response 2>/dev/null || use_config_response="n"
    echo ""
    if [ "$use_config_response" = "y" ] || [ "$use_config_response" = "Y" ]; then
        USE_URL_CONFIG=true
        echo -e "${GREEN}✓ Will test URLs from config file${NC}"
    else
        echo -e "${YELLOW}→ Using default test pages${NC}"
    fi
fi
echo ""

# Function to test a single page
test_page() {
    local url="$1"
    local page_name="$2"
    local endpoint="$3"
    
    TOTAL_PAGES=$((TOTAL_PAGES + 1))
    
    echo -e "${BLUE}Testing:${NC} $page_name ($endpoint)"
    
    # Test if page loads
    if ! curl -s --max-time 10 "$url" > "${RESULTS_DIR}/${page_name}_content.html" 2>/dev/null; then
        echo -e "${RED}  ❌ FAILED TO LOAD${NC}"
        echo "FAILED TO LOAD: $page_name ($endpoint)" >> "$REPORT_FILE"
        return 1
    fi
    
    # Check for CSS being displayed as text
    css_text_count=$(grep -c -i "font-family:\|background:\|color:\|margin:\|padding:" "${RESULTS_DIR}/${page_name}_content.html" 2>/dev/null || echo "0")
    css_text_count=$(echo "$css_text_count" | tr -d '\n\r')
    
    # Check for style tags in content (another indicator of CSS issues)
    style_tag_count=$(grep -c "<style>" "${RESULTS_DIR}/${page_name}_content.html" 2>/dev/null || echo "0")
    style_tag_count=$(echo "$style_tag_count" | tr -d '\n\r')
    
    # Extract any CSS text being displayed
    if [ "$css_text_count" -gt 0 ] 2>/dev/null; then
        grep -i "font-family:\|background:\|color:\|margin:\|padding:" "${RESULTS_DIR}/${page_name}_content.html" | head -10 > "${RESULTS_DIR}/${page_name}_css_issues.txt"
    fi
    
    # Check for HTTP errors
    http_status=$(curl -s -o /dev/null -w "%{http_code}" "$url" 2>/dev/null)
    http_status=$(echo "$http_status" | tr -d '\n\r')
    
    # Determine page status
    if [ "$http_status" -ne 200 ] 2>/dev/null; then
        echo -e "${RED}  ❌ HTTP ERROR: $http_status${NC}"
        echo "HTTP ERROR $http_status: $page_name ($endpoint)" >> "$REPORT_FILE"
    elif [ "$css_text_count" -gt 5 ] 2>/dev/null && [ "$style_tag_count" -gt 0 ] 2>/dev/null; then
        PAGES_WITH_CSS_ISSUES=$((PAGES_WITH_CSS_ISSUES + 1))
        echo -e "${YELLOW}  ⚠️  CSS ISSUES DETECTED: $css_text_count CSS properties as text, $style_tag_count style tags${NC}"
        echo "CSS ISSUES: $page_name ($endpoint) - $css_text_count CSS properties, $style_tag_count style tags" >> "$REPORT_FILE"
    else
        PAGES_CLEAN=$((PAGES_CLEAN + 1))
        echo -e "${GREEN}  ✅ CLEAN${NC}"
        echo "CLEAN: $page_name ($endpoint)" >> "$REPORT_FILE"
    fi
    
    # Extract all links from the page
    if [ -f "${RESULTS_DIR}/${page_name}_content.html" ]; then
        echo "" >> "$ALL_LINKS_FILE"
        echo "=== Links found on: $page_name ($endpoint) ===" >> "$ALL_LINKS_FILE"
        
        # Extract href attributes and clean them up
        grep -oP 'href="[^"]*"' "${RESULTS_DIR}/${page_name}_content.html" 2>/dev/null | \
            sed 's/href="//g' | sed 's/"//g' | \
            grep -v '^#' | \
            grep -v '^javascript:' | \
            grep -v '^mailto:' | \
            grep -v '^tel:' | \
            sort -u >> "$ALL_LINKS_FILE" 2>/dev/null || true
        
        # Count links found
        link_count=$(grep -oP 'href="[^"]*"' "${RESULTS_DIR}/${page_name}_content.html" 2>/dev/null | wc -l || echo "0")
        TOTAL_LINKS_FOUND=$((TOTAL_LINKS_FOUND + link_count))
        echo "  📎 Found $link_count links"
        
        # Extract internal links and add to discovered URLs (removing duplicates)
        grep -oP 'href="[^"]*"' "${RESULTS_DIR}/${page_name}_content.html" 2>/dev/null | \
            sed 's/href="//g' | sed 's/"//g' | \
            grep -v '^http' | \
            grep -v '^#' | \
            grep -v '^javascript:' | \
            grep -v '^mailto:' | \
            grep -v '^tel:' | \
            sed 's/&amp;/\&/g' | \
            sort -u >> "$URLS_CONFIG_FILE" 2>/dev/null || true
    fi
    
    echo ""
}

# Initialize report
echo "=== FORSETI.LIFE SITE TEST REPORT - $ENV_NAME ===" > "$REPORT_FILE"
echo "Generated: $(date)" >> "$REPORT_FILE"
echo "Base URL: $BASE_URL" >> "$REPORT_FILE"
echo "Testing Mode: $([ "$USE_URL_CONFIG" = true ] && echo "URL Config File" || echo "Default Pages")" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

# Test pages based on mode
if [ "$USE_URL_CONFIG" = true ]; then
    echo -e "${YELLOW}Testing URLs from config file...${NC}"
    
    # Read URLs from config and test each one
    while IFS= read -r url_path; do
        # Skip empty lines and comments
        [[ -z "$url_path" || "$url_path" =~ ^# ]] && continue
        
        # Skip asset files (CSS, JS, images)
        [[ "$url_path" =~ \.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot)(\?.*)?$ ]] && continue
        
        # Skip node URLs (Drupal node IDs)
        [[ "$url_path" =~ /node/ ]] && continue
        
        # Clean up the URL
        url_path=$(echo "$url_path" | sed 's/&amp;/\&/g' | sed 's/\?.*$//')
        
        # Generate a clean page name from the URL
        page_name=$(echo "$url_path" | sed 's/^\/\+//g' | sed 's/\//-/g' | sed 's/[^a-zA-Z0-9-]/_/g')
        [ -z "$page_name" ] && page_name="home"
        
        test_page "$BASE_URL$url_path" "$page_name" "$url_path"
    done < "$PERSISTENT_URL_CONFIG"
else
    # Test default pages (original hardcoded list)
# Test all main pages
echo -e "${YELLOW}Testing Main Navigation Pages...${NC}"
test_page "$BASE_URL/" "Home Page" "/"
test_page "$BASE_URL/home" "Home Alt" "/home"
test_page "$BASE_URL/about" "About Forseti" "/about"
test_page "$BASE_URL/talk-with-forseti" "Talk with Forseti" "/talk-with-forseti"
test_page "$BASE_URL/how-it-works" "How It Works" "/how-it-works"
test_page "$BASE_URL/safety-factors" "Safety Factors" "/safety-factors"
test_page "$BASE_URL/safety-map" "Safety Map" "/safety-map"
test_page "$BASE_URL/community" "Community" "/community"
test_page "$BASE_URL/mobile-app" "Mobile App" "/mobile-app"
test_page "$BASE_URL/privacy" "Privacy & Security" "/privacy"

echo -e "${YELLOW}Testing Agent Power Framework - Main Pages...${NC}"
test_page "$BASE_URL/agent-power-framework" "Agent Power Framework" "/agent-power-framework"
test_page "$BASE_URL/agent-power-framework/evaluations" "Evaluated Entities" "/agent-power-framework/evaluations"
test_page "$BASE_URL/agent-power-framework/evaluate" "Evaluate an Agent" "/agent-power-framework/evaluate"
test_page "$BASE_URL/agent-power-framework/us-government" "US Government Power" "/agent-power-framework/us-government"

echo -e "${YELLOW}Testing Agent Power Framework - Categories...${NC}"
test_page "$BASE_URL/agent-power-framework/information-access" "Information Access" "/agent-power-framework/information-access"
test_page "$BASE_URL/agent-power-framework/resource-control" "Resource Control" "/agent-power-framework/resource-control"
test_page "$BASE_URL/agent-power-framework/network-position" "Network Position" "/agent-power-framework/network-position"
test_page "$BASE_URL/agent-power-framework/authority" "Authority Category" "/agent-power-framework/authority"
test_page "$BASE_URL/agent-power-framework/synthesis" "Synthesis Category" "/agent-power-framework/synthesis"

echo -e "${YELLOW}Testing Information Access Dimensions...${NC}"
test_page "$BASE_URL/agent-power-framework/scope" "Scope & Breadth" "/agent-power-framework/scope"
test_page "$BASE_URL/agent-power-framework/restriction" "Content Restriction" "/agent-power-framework/restriction"
test_page "$BASE_URL/agent-power-framework/restriction/classification" "Classification Access" "/agent-power-framework/restriction/classification"
test_page "$BASE_URL/agent-power-framework/temporal" "Temporal Reach" "/agent-power-framework/temporal"
test_page "$BASE_URL/agent-power-framework/sources" "Source Diversity" "/agent-power-framework/sources"
test_page "$BASE_URL/agent-power-framework/granularity" "Data Granularity" "/agent-power-framework/granularity"
test_page "$BASE_URL/agent-power-framework/authority-level" "Authority Level" "/agent-power-framework/authority-level"
test_page "$BASE_URL/agent-power-framework/information-synthesis" "Information Synthesis" "/agent-power-framework/information-synthesis"
test_page "$BASE_URL/agent-power-framework/verification" "Data Verification" "/agent-power-framework/verification"

echo -e "${YELLOW}Testing Synthesis & Application Dimensions...${NC}"
test_page "$BASE_URL/agent-power-framework/creativity-generation" "Creativity Generation" "/agent-power-framework/creativity-generation"
test_page "$BASE_URL/agent-power-framework/strategic-planning" "Strategic Planning" "/agent-power-framework/strategic-planning"
test_page "$BASE_URL/agent-power-framework/decision-quality" "Decision Quality" "/agent-power-framework/decision-quality"
test_page "$BASE_URL/agent-power-framework/adaptive-learning" "Adaptive Learning" "/agent-power-framework/adaptive-learning"
test_page "$BASE_URL/agent-power-framework/memory-architecture" "Memory Architecture" "/agent-power-framework/memory-architecture"

echo -e "${YELLOW}Testing Resource Control Dimensions...${NC}"
test_page "$BASE_URL/agent-power-framework/computational-resources" "Computational Resources" "/agent-power-framework/computational-resources"
test_page "$BASE_URL/agent-power-framework/financial-capital" "Financial Capital" "/agent-power-framework/financial-capital"
test_page "$BASE_URL/agent-power-framework/infrastructure-access" "Infrastructure Access" "/agent-power-framework/infrastructure-access"
test_page "$BASE_URL/agent-power-framework/human-capital" "Human Capital" "/agent-power-framework/human-capital"
test_page "$BASE_URL/agent-power-framework/energy-resources" "Energy Resources" "/agent-power-framework/energy-resources"
test_page "$BASE_URL/agent-power-framework/time-allocation" "Time Allocation" "/agent-power-framework/time-allocation"

echo -e "${YELLOW}Testing Network Position Dimensions...${NC}"
test_page "$BASE_URL/agent-power-framework/trust-network-depth" "Trust Network Depth" "/agent-power-framework/trust-network-depth"
test_page "$BASE_URL/agent-power-framework/dependency-relationships" "Dependency Relationships" "/agent-power-framework/dependency-relationships"
test_page "$BASE_URL/agent-power-framework/gatekeeping-power" "Gatekeeping Power" "/agent-power-framework/gatekeeping-power"
test_page "$BASE_URL/agent-power-framework/influence-reach" "Influence Reach" "/agent-power-framework/influence-reach"
test_page "$BASE_URL/agent-power-framework/reputation-capital" "Reputation Capital" "/agent-power-framework/reputation-capital"
test_page "$BASE_URL/agent-power-framework/mobilization-capability" "Mobilization Capability" "/agent-power-framework/mobilization-capability"

echo -e "${YELLOW}Testing Authority & Permission Dimensions...${NC}"
test_page "$BASE_URL/agent-power-framework/legal-authorization" "Legal Authorization" "/agent-power-framework/legal-authorization"
test_page "$BASE_URL/agent-power-framework/decision-making-scope" "Decision Making Scope" "/agent-power-framework/decision-making-scope"
test_page "$BASE_URL/agent-power-framework/budget-authority" "Budget Authority" "/agent-power-framework/budget-authority"
test_page "$BASE_URL/agent-power-framework/jurisdictional-reach" "Jurisdictional Reach" "/agent-power-framework/jurisdictional-reach"
test_page "$BASE_URL/agent-power-framework/enforcement-power" "Enforcement Power" "/agent-power-framework/enforcement-power"
test_page "$BASE_URL/agent-power-framework/moral-authority" "Moral Authority" "/agent-power-framework/moral-authority"

echo -e "${YELLOW}Testing AmISafe Pages...${NC}"
test_page "$BASE_URL/amisafe" "AmISafe Dashboard" "/amisafe"
test_page "$BASE_URL/amisafe/crime-map" "AmISafe Crime Map" "/amisafe/crime-map"

echo -e "${YELLOW}Testing API Endpoints (GET)...${NC}"
test_page "$BASE_URL/api/amisafe/system-stats" "API System Stats" "/api/amisafe/system-stats"
test_page "$BASE_URL/api/amisafe/crime-types" "API Crime Types" "/api/amisafe/crime-types"
test_page "$BASE_URL/api/amisafe/date-range" "API Date Range" "/api/amisafe/date-range"
test_page "$BASE_URL/api/amisafe/districts" "API Districts" "/api/amisafe/districts"

# Generate summary
echo "" >> "$REPORT_FILE"
echo "=== SUMMARY ===" >> "$REPORT_FILE"
echo "Total Pages Tested: $TOTAL_PAGES" >> "$REPORT_FILE"
echo "Pages Clean: $PAGES_CLEAN" >> "$REPORT_FILE"
echo "Pages with CSS Issues: $PAGES_WITH_CSS_ISSUES" >> "$REPORT_FILE"
echo "Success Rate: $(( (PAGES_CLEAN * 100) / TOTAL_PAGES ))%" >> "$REPORT_FILE"

# Check multiple controller directories for Forseti modules
CONTROLLER_DIRS=(
    "/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/forseti_safety_content/src/Controller"
    "/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/amisafe/src/Controller"
    "/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/ai_conversation/src/Controller"
    "/home/keithaumiller/forseti.life/sites/forseti/web/modules/custom/agent_evaluation/src/Controller"
)

CONTROLLERS_WITHOUT_SITE_LIB=0
TOTAL_CONTROLLERS=0

for CONTROLLERS_DIR in "${CONTROLLER_DIRS[@]}"; do
    if [ -d "$CONTROLLERS_DIR" ]; then
        MODULE_NAME=$(basename "$(dirname "$(dirname "$CONTROLLERS_DIR")")")
        echo -e "${BLUE}Checking $MODULE_NAME controllers...${NC}"
        echo "Checking $MODULE_NAME controllers..." >> "$REPORT_FILE"
        
        for controller_file in "$CONTROLLERS_DIR"/*.php; do
            if [ -f "$controller_file" ]; then
                controller_name=$(basename "$controller_file" .php)
                TOTAL_CONTROLLERS=$((TOTAL_CONTROLLERS + 1))
                
                # Check if controller has methods that return render arrays but don't attach appropriate library
                has_return_array=$(grep -c "return \[" "$controller_file" 2>/dev/null || echo "0")
                has_return_array=$(echo "$has_return_array" | tr -d '\n\r')
                has_forseti_lib=$(grep -c "forseti/site\|amisafe/crime-map\|ai_conversation/chat" "$controller_file" 2>/dev/null || echo "0")
                has_forseti_lib=$(echo "$has_forseti_lib" | tr -d '\n\r')
                
                if [ "$has_return_array" -gt 0 ] 2>/dev/null && [ "$has_forseti_lib" -eq 0 ] 2>/dev/null; then
                    echo -e "  ${YELLOW}⚠️  $controller_name: Possibly missing library attachment${NC}"
                    echo "POSSIBLY MISSING LIBRARY: $MODULE_NAME/$controller_name" >> "$REPORT_FILE"
                    CONTROLLERS_WITHOUT_SITE_LIB=$((CONTROLLERS_WITHOUT_SITE_LIB + 1))
                else
                    echo -e "  ${GREEN}✅ $controller_name: Library properly attached or not needed${NC}"
                    echo "LIBRARY OK: $MODULE_NAME/$controller_name" >> "$REPORT_FILE"
                fi
            fi
        done
    fi
done

if [ $TOTAL_CONTROLLERS -eq 0 ]; then
    echo -e "  ${RED}❌ No controller directories found${NC}"
    echo "ERROR: No controller directories found" >> "$REPORT_FILE"
fi

echo ""
echo -e "Controllers checked: ${BLUE}$TOTAL_CONTROLLERS${NC}"
echo -e "Controllers possibly missing libraries: ${YELLOW}$CONTROLLERS_WITHOUT_SITE_LIB${NC}"
echo "" >> "$REPORT_FILE"
echo "Controllers checked: $TOTAL_CONTROLLERS" >> "$REPORT_FILE"
echo "Controllers possibly missing librariesE"
echo "Total Pages Tested: $TOTAL_PAGES" >> "$REPORT_FILE"
echo "Pages Clean: $PAGES_CLEAN" >> "$REPORT_FILE"
echo "Pages with CSS Issues: $PAGES_WITH_CSS_ISSUES" >> "$REPORT_FILE"
echo "Success Rate: $(( (PAGES_CLEAN * 100) / TOTAL_PAGES ))%" >> "$REPORT_FILE"

# Check controller library attachments
echo -e "${BLUE}=== CONTROLLER LIBRARY CHECK ===${NC}"
echo "=== CONTROLLER LIBRARY CHECK ===" >> "$REPORT_FILE"

CONTROLLERS_DIR="/workspaces/stlouisintegration.com/sites/theoryofconspiracies/web/modules/custom/theory_content/src/Controller"
CONTROLLERS_WITHOUT_SITE_LIB=0

if [ -d "$CONTROLLERS_DIR" ]; then
    for controller_file in "$CONTROLLERS_DIR"/*.php; do
        if [ -f "$controller_file" ]; then
            controller_name=$(basename "$controller_file" .php)
            
            # Check if controller has methods that return render arrays but don't attach site library
            has_return_array=$(grep -c "return \[" "$controller_file" 2>/dev/null || echo "0")
            has_site_lib=$(grep -c "theory_content/site" "$controller_file" 2>/dev/null || echo "0")
            
            if [ $has_return_array -gt 0 ] && [ $has_site_lib -eq 0 ]; then
                echo -e "  ${YELLOW}⚠️  $controller_name: Missing site library attachment${NC}"
                echo "MISSING SITE LIBRARY: $controller_name" >> "$REPORT_FILE"
                CONTROLLERS_WITHOUT_SITE_LIB=$((CONTROLLERS_WITHOUT_SITE_LIB + 1))
            else
                echo -e "  ${GREEN}✅ $controller_name: Site library properly attached${NC}"
                echo "SITE LIBRARY OK: $controller_name" >> "$REPORT_FILE"
            fi
        fi
    done
else
    echo -e "  ${RED}❌ Controllers directory not found${NC}"
    echo "ERROR: Controllers directory not found" >> "$REPORT_FILE"
fi

echo ""
echo -e "Controllers checked: ${BLUE}$(ls "$CONTROLLERS_DIR"/*.php 2>/dev/null | wc -l)${NC}"
echo -e "Controllers missing site library: ${YELLOW}$CONTROLLERS_WITHOUT_SITE_LIB${NC}"
echo "" >> "$REPORT_FILE"
echo "Controllers checked: $(ls "$CONTROLLERS_DIR"/*.php 2>/dev/null | wc -l)" >> "$REPORT_FILE"
echo "Controllers missing site library: $CONTROLLERS_WITHOUT_SITE_LIB" >> "$REPORT_FILE"
echo "" >> "$REPORT_FILE"

fi  # End of URL config mode check

# Process discovered URLs - remove duplicates and sort
if [ -f "$URLS_CONFIG_FILE" ]; then
    sort -u "$URLS_CONFIG_FILE" -o "$URLS_CONFIG_FILE"
    unique_urls=$(wc -l < "$URLS_CONFIG_FILE")
    echo "Total Links Found: $TOTAL_LINKS_FOUND" >> "$REPORT_FILE"
    echo "Unique Internal URLs Discovered: $unique_urls" >> "$REPORT_FILE"
    
    # Update persistent URL config file
    if [ -f "$PERSISTENT_URL_CONFIG" ]; then
        # Count URLs before merge
        before_count=$(wc -l < "$PERSISTENT_URL_CONFIG")
        # Merge with existing URLs
        cat "$PERSISTENT_URL_CONFIG" "$URLS_CONFIG_FILE" | sort -u > "${PERSISTENT_URL_CONFIG}.tmp"
        mv "${PERSISTENT_URL_CONFIG}.tmp" "$PERSISTENT_URL_CONFIG"
        persistent_urls=$(wc -l < "$PERSISTENT_URL_CONFIG")
        new_urls=$((persistent_urls - before_count))
        echo -e "${GREEN}✓ Updated persistent URL config${NC}"
        echo -e "  Total URLs in config: ${BLUE}$persistent_urls${NC}"
        [ $new_urls -gt 0 ] && echo -e "  New URLs added: ${YELLOW}$new_urls${NC}"
    else
        # Create new persistent config
        cp "$URLS_CONFIG_FILE" "$PERSISTENT_URL_CONFIG"
        echo -e "${GREEN}✓ Created new persistent URL config${NC}"
        echo -e "  URLs saved: ${BLUE}$unique_urls${NC}"
    fi
    
    echo ""
    echo -e "${BLUE}=== URL DISCOVERY SUMMARY ===${NC}"
    echo -e "Total links found: ${YELLOW}$TOTAL_LINKS_FOUND${NC}"
    echo -e "Unique internal URLs: ${YELLOW}$unique_urls${NC}"
    echo -e "Persistent config: ${BLUE}$PERSISTENT_URL_CONFIG${NC}"
    echo -e "URLs saved to: ${BLUE}$URLS_CONFIG_FILE${NC}"
    echo -e "Full link report: ${BLUE}$ALL_LINKS_FILE${NC}"
    echo ""
fi

# Display final summary
echo -e "${BLUE}=== TEST SUMMARY ===${NC}"
echo -e "Total Pages Tested: ${BLUE}$TOTAL_PAGES${NC}"
echo -e "Pages Clean: ${GREEN}$PAGES_CLEAN${NC}"
echo -e "Pages with CSS Issues: ${YELLOW}$PAGES_WITH_CSS_ISSUES${NC}"
echo -e "Success Rate: ${BLUE}$(( (PAGES_CLEAN * 100) / TOTAL_PAGES ))%${NC}"
echo ""
echo -e "Full report saved to: ${BLUE}$REPORT_FILE${NC}"
echo -e "Page content saved to: ${BLUE}$RESULTS_DIR${NC}"

# Show pages with issues
if [ $PAGES_WITH_CSS_ISSUES -gt 0 ]; then
    echo ""
    echo -e "${YELLOW}Pages with CSS Issues:${NC}"
    grep "CSS ISSUES:" "$REPORT_FILE" | sed 's/CSS ISSUES: /  - /'
fi

# Show any failed pages
failed_pages=$(grep -c "FAILED TO LOAD\|HTTP ERROR" "$REPORT_FILE" 2>/dev/null || echo "0")
if [ $failed_pages -gt 0 ]; then
    echo ""
    echo -e "${RED}Failed Pages:${NC}"
    grep "FAILED TO LOAD\|HTTP ERROR" "$REPORT_FILE" | sed 's/FAILED TO LOAD: /  - /' | sed 's/HTTP ERROR [0-9]*: /  - /'
fi

echo ""
echo -e "${BLUE}Testing complete!${NC}"