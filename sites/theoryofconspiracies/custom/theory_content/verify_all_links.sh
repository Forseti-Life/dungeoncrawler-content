#!/bin/bash

# Theory of Conspiracies - Complete Link Verification Script
# Extracts all links from every page and verifies they point to existing pages

echo "🔍 THEORY OF CONSPIRACIES - COMPLETE LINK VERIFICATION"
echo "======================================================"
echo "Extracting and verifying all internal links from all pages..."
echo ""

BASE_URL="http://localhost:8080"
TEMP_DIR="/tmp/toc_links"
mkdir -p "$TEMP_DIR"

# Define all our known valid pages from the routing file
VALID_PAGES=(
    "/home"
    "/characters"
    "/characters/sal-mueller"
    "/characters/tiger-mueller"
    "/characters/estella-mueller"
    "/characters/gallad-mueller"
    "/characters/iris-vasquez"
    "/characters/maria-santos"
    "/characters/keith-ai"
    "/characters/david-ai"
    "/characters/mcdrone"
    "/characters/commander-chen"
    "/characters/ron-whiteside"
    "/characters/dr-eleanor-voss"
    "/characters/elena-ai"
    "/characters/pal-drone"
    "/story/act-i"
    "/story/act-ii"
    "/story/sequences/01-first-assignment"
    "/story/sequences/02-character-introductions"
    "/story/sequences/03-family-dinner"
    "/story/sequences/04-mothers-secret"
    "/story/sequences/05-keiths-revelation"
    "/story/sequences/06-underground-contact"
    "/story/sequences/07-institutional-loyalty"
    "/story/sequences/08-underground-integration"
    "/story/sequences/09-digital-networks-revealed"
    "/story/sequences/10-resistance-network-architecture"
    "/story/sequences/11-public-trial-spectacle-planning"
    "/story/sequences/12-technical-resistance-workshop"
    "/story/sequences/13-rons-depression-negotiation"
    "/story/sequences/14-tigers-moral-crisis"
    "/story/sequences/15-algorithm-warfare"
    "/story/sequences/16-professional-evacuation"
    "/setting/philadelphia-2085"
)

echo "📊 VALID PAGES INVENTORY: ${#VALID_PAGES[@]} total pages"
echo ""

# Function to extract links from a page
extract_links_from_page() {
    local url="$1"
    local page_name="$2"
    
    echo "🔗 Extracting links from: $page_name ($url)"
    
    # Get the page content and extract href attributes
    curl -s "$BASE_URL$url" | grep -oP 'href="[^"]*"' | sed 's/href="//g' | sed 's/"//g' > "$TEMP_DIR/links_$(echo $page_name | tr '/' '_' | tr ' ' '_').txt"
    
    # Count links found
    local link_count=$(wc -l < "$TEMP_DIR/links_$(echo $page_name | tr '/' '_' | tr ' ' '_').txt")
    echo "   → Found $link_count links"
}

# Function to check if a link is internal and valid
is_valid_internal_link() {
    local link="$1"
    
    # Skip external links (http/https), fragments (#), javascript, mailto, etc.
    if [[ "$link" =~ ^(http|https|#|javascript|mailto|tel|ftp) ]]; then
        return 1
    fi
    
    # Skip empty links
    if [[ -z "$link" ]]; then
        return 1
    fi
    
    # Check if the link matches any of our valid pages
    for valid_page in "${VALID_PAGES[@]}"; do
        if [[ "$link" == "$valid_page" ]]; then
            return 0
        fi
    done
    
    return 1
}

# Extract links from all pages
echo "🚀 STARTING LINK EXTRACTION FROM ALL PAGES"
echo "==========================================="

for page in "${VALID_PAGES[@]}"; do
    extract_links_from_page "$page" "$page"
done

echo ""
echo "📋 LINK EXTRACTION COMPLETE"
echo "==========================="

# Combine all extracted links and analyze
echo ""
echo "🔍 ANALYZING ALL EXTRACTED LINKS"
echo "================================"

ALL_LINKS_FILE="$TEMP_DIR/all_links.txt"
cat "$TEMP_DIR"/links_*.txt | sort | uniq > "$ALL_LINKS_FILE"

total_unique_links=$(wc -l < "$ALL_LINKS_FILE")
echo "📊 Total unique links found: $total_unique_links"

# Separate internal vs external links
INTERNAL_LINKS_FILE="$TEMP_DIR/internal_links.txt"
EXTERNAL_LINKS_FILE="$TEMP_DIR/external_links.txt"
INVALID_INTERNAL_FILE="$TEMP_DIR/invalid_internal_links.txt"
VALID_INTERNAL_FILE="$TEMP_DIR/valid_internal_links.txt"

> "$INTERNAL_LINKS_FILE"
> "$EXTERNAL_LINKS_FILE"
> "$INVALID_INTERNAL_FILE"
> "$VALID_INTERNAL_FILE"

while IFS= read -r link; do
    if [[ "$link" =~ ^(http|https|#|javascript|mailto|tel|ftp) ]] || [[ -z "$link" ]]; then
        echo "$link" >> "$EXTERNAL_LINKS_FILE"
    else
        echo "$link" >> "$INTERNAL_LINKS_FILE"
        
        # Check if this internal link is valid
        if is_valid_internal_link "$link"; then
            echo "$link" >> "$VALID_INTERNAL_FILE"
        else
            echo "$link" >> "$INVALID_INTERNAL_FILE"
        fi
    fi
done < "$ALL_LINKS_FILE"

internal_count=$(wc -l < "$INTERNAL_LINKS_FILE")
external_count=$(wc -l < "$EXTERNAL_LINKS_FILE")
valid_internal_count=$(wc -l < "$VALID_INTERNAL_FILE")
invalid_internal_count=$(wc -l < "$INVALID_INTERNAL_FILE")

echo "🔗 Internal links: $internal_count"
echo "🌐 External links: $external_count"
echo "✅ Valid internal links: $valid_internal_count"
echo "❌ Invalid internal links: $invalid_internal_count"

echo ""
echo "📊 LINK VALIDATION SUMMARY"
echo "=========================="

if [[ $invalid_internal_count -gt 0 ]]; then
    echo ""
    echo "⚠️  INVALID INTERNAL LINKS FOUND:"
    echo "================================="
    while IFS= read -r invalid_link; do
        echo "❌ $invalid_link"
        
        # Test the invalid link to see what status it returns
        status_code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$invalid_link")
        echo "   → Status: $status_code"
        
        # Find which pages contain this invalid link
        echo "   → Found on pages:"
        grep -l "$invalid_link" "$TEMP_DIR"/links_*.txt | while IFS= read -r file; do
            page_name=$(basename "$file" | sed 's/links_//g' | sed 's/.txt//g' | tr '_' '/')
            echo "     * $page_name"
        done
        echo ""
    done < "$INVALID_INTERNAL_FILE"
else
    echo "🎉 ALL INTERNAL LINKS ARE VALID!"
    echo ""
    echo "✅ Perfect link integrity across all pages"
    echo "✅ All $valid_internal_count internal links point to existing pages"
    echo "✅ No broken internal navigation found"
fi

echo ""
echo "📈 DETAILED BREAKDOWN"
echo "===================="
echo "Valid internal links found:"
sort "$VALID_INTERNAL_FILE" | uniq -c | sort -nr

echo ""
echo "🧹 CLEANUP"
echo "=========="
# Clean up temporary files
rm -rf "$TEMP_DIR"
echo "Temporary files cleaned up"

echo ""
echo "🏁 LINK VERIFICATION COMPLETE"
echo "============================="