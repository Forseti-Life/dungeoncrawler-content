#!/usr/bin/env bash
# Escalation item validation and remediation

set -euo pipefail

ROOT_DIR="${ROOT_DIR:-.}"

# Validate that an escalation item (needs-info or blocked status) has required fields
validate_escalation_item() {
    local item_path="$1"
    
    if [ ! -f "$item_path" ]; then
        echo "ERROR: item not found: $item_path" >&2
        return 1
    fi
    
    local status
    status=$(grep "^- Status:" "$item_path" 2>/dev/null | head -1 | sed 's/^- Status: *//;s/ *$//' || echo "")
    
    # Only validate if status is needs-info or blocked
    if [ "$status" != "needs-info" ] && [ "$status" != "blocked" ]; then
        return 0  # Other statuses don't require Needs section
    fi
    
    # Check for "Needs from X" section
    local has_needs_section
    has_needs_section=$(grep "^## Needs from" "$item_path" 2>/dev/null | wc -l)
    
    if [ "$has_needs_section" -eq 0 ]; then
        echo "ERROR: escalation item missing 'Needs from X' section: $item_path" >&2
        return 1
    fi
    
    # Check that Needs section is not empty (N/A or whitespace-only)
    local needs_content
    needs_content=$(sed -n '/^## Needs from/,/^##/p' "$item_path" | tail -n +2 | sed '$ d' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' | grep -v '^$' || echo "")
    
    if [ -z "$needs_content" ] || [ "$needs_content" = "N/A" ] || [ "$needs_content" = "TBD" ]; then
        echo "ERROR: escalation item has empty 'Needs from' section: $item_path" >&2
        return 1
    fi
    
    return 0
}

# Scan inbox for malformed escalation items
scan_malformed_escalations() {
    local inbox_root="$1"
    local exit_code=0
    
    if [ ! -d "$inbox_root" ]; then
        return 0  # No inbox, no issues
    fi
    
    while IFS= read -r item_path; do
        if ! validate_escalation_item "$item_path"; then
            exit_code=1
        fi
    done < <(find "$inbox_root" -maxdepth 2 -name "*.md" -type f 2>/dev/null || true)
    
    return $exit_code
}

# Export functions
export -f validate_escalation_item
export -f scan_malformed_escalations
