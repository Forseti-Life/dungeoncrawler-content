#!/bin/bash
#
# Compare Drupal configurations between development and production
# Run this on your LOCAL development machine
#
# Usage: ./compare-config.sh [site-name] [ssh-host]
# Example: ./compare-config.sh forseti root@your-server

set -e

SITE_NAME=${1:-forseti}
SSH_HOST=${2:-}
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SITE_PATH="${REPO_ROOT}/sites/${SITE_NAME}"
COMPARE_DIR="/tmp/config-compare-${SITE_NAME}-$(date +%Y%m%d-%H%M%S)"
EXPECTED_DIFF_FILE="${SITE_PATH}/.config-differences.yml"

echo "🔍 Config Comparison Tool"
echo "========================="
echo ""
echo "Site: ${SITE_NAME}"
echo ""

# Load expected differences if file exists
declare -a EXPECTED_PATTERNS
if [ -f "${EXPECTED_DIFF_FILE}" ]; then
  echo "📋 Loading expected differences from .config-differences.yml"
  # Extract patterns from YAML (simple grep-based parsing)
  while IFS= read -r line; do
    if [[ $line =~ pattern:\ \"(.+)\" ]]; then
      EXPECTED_PATTERNS+=("${BASH_REMATCH[1]}")
    fi
  done < "${EXPECTED_DIFF_FILE}"
  echo "✅ Loaded ${#EXPECTED_PATTERNS[@]} expected difference patterns"
  echo ""
fi

# Prompt for SSH host if not provided
if [ -z "${SSH_HOST}" ]; then
  echo "Enter production SSH host (e.g., root@your-server.com):"
  read -r SSH_HOST
  
  if [ -z "${SSH_HOST}" ]; then
    echo "❌ Error: SSH host is required"
    exit 1
  fi
fi

# Create comparison directory
mkdir -p "${COMPARE_DIR}"/{dev,prod}

# Export development config
echo "📦 Exporting DEVELOPMENT config..."
cd "${SITE_PATH}"
if [ -f "vendor/bin/drush" ]; then
  ./vendor/bin/drush config:export -y
  cp -r config/sync/* "${COMPARE_DIR}/dev/"
  DEV_COUNT=$(ls -1 "${COMPARE_DIR}/dev" | wc -l)
  echo "✅ Exported ${DEV_COUNT} dev config files"
else
  echo "❌ Error: Drush not found in development"
  exit 1
fi

# Export production config
echo ""
echo "📦 Exporting PRODUCTION config..."
PROD_EXPORT="/tmp/prod-config-temp.tar.gz"
ssh "${SSH_HOST}" "cd /var/www/html/${SITE_NAME} && sudo -u www-data ./vendor/bin/drush config:export -y && sudo tar -czf /tmp/prod-export-temp.tar.gz config/sync/ && sudo chmod 644 /tmp/prod-export-temp.tar.gz"
scp "${SSH_HOST}:/tmp/prod-export-temp.tar.gz" "${PROD_EXPORT}"
ssh "${SSH_HOST}" "sudo rm /tmp/prod-export-temp.tar.gz"
tar -xzf "${PROD_EXPORT}" -C "${COMPARE_DIR}/prod" --strip-components=2
rm "${PROD_EXPORT}"
PROD_COUNT=$(ls -1 "${COMPARE_DIR}/prod" | wc -l)
echo "✅ Exported ${PROD_COUNT} prod config files"

# Helper function to check if file matches expected patterns
is_expected_difference() {
  local file=$1
  for pattern in "${EXPECTED_PATTERNS[@]}"; do
    if [[ $file == $pattern ]]; then
      return 0
    fi
  done
  return 1
}

# Compare
echo ""
echo "🔍 Analyzing differences..."
echo ""

# Files only in dev
DEV_ONLY=$(comm -23 <(ls -1 "${COMPARE_DIR}/dev" | sort) <(ls -1 "${COMPARE_DIR}/prod" | sort))
DEV_ONLY_COUNT=$(echo "$DEV_ONLY" | grep -c . || echo 0)
DEV_ONLY_EXPECTED=""
DEV_ONLY_UNEXPECTED=""
DEV_ONLY_EXPECTED_COUNT=0
DEV_ONLY_UNEXPECTED_COUNT=0

while IFS= read -r file; do
  if [ -n "$file" ]; then
    if is_expected_difference "$file"; then
      DEV_ONLY_EXPECTED="${DEV_ONLY_EXPECTED}${file}\n"
      ((DEV_ONLY_EXPECTED_COUNT++))
    else
      DEV_ONLY_UNEXPECTED="${DEV_ONLY_UNEXPECTED}${file}\n"
      ((DEV_ONLY_UNEXPECTED_COUNT++))
    fi
  fi
done <<< "$DEV_ONLY"

# Files only in prod
PROD_ONLY=$(comm -13 <(ls -1 "${COMPARE_DIR}/dev" | sort) <(ls -1 "${COMPARE_DIR}/prod" | sort))
PROD_ONLY_COUNT=$(echo "$PROD_ONLY" | grep -c . || echo 0)
PROD_ONLY_EXPECTED=""
PROD_ONLY_UNEXPECTED=""
PROD_ONLY_EXPECTED_COUNT=0
PROD_ONLY_UNEXPECTED_COUNT=0

while IFS= read -r file; do
  if [ -n "$file" ]; then
    if is_expected_difference "$file"; then
      PROD_ONLY_EXPECTED="${PROD_ONLY_EXPECTED}${file}\n"
      ((PROD_ONLY_EXPECTED_COUNT++))
    else
      PROD_ONLY_UNEXPECTED="${PROD_ONLY_UNEXPECTED}${file}\n"
      ((PROD_ONLY_UNEXPECTED_COUNT++))
    fi
  fi
done <<< "$PROD_ONLY"

# Files in both but different
BOTH_FILES=$(comm -12 <(ls -1 "${COMPARE_DIR}/dev" | sort) <(ls -1 "${COMPARE_DIR}/prod" | sort))
MODIFIED_FILES=""
MODIFIED_COUNT=0
MODIFIED_EXPECTED=""
MODIFIED_UNEXPECTED=""
MODIFIED_EXPECTED_COUNT=0
MODIFIED_UNEXPECTED_COUNT=0

while IFS= read -r file; do
  if [ -n "$file" ] && ! diff -q "${COMPARE_DIR}/dev/$file" "${COMPARE_DIR}/prod/$file" > /dev/null 2>&1; then
    MODIFIED_FILES="${MODIFIED_FILES}${file}\n"
    ((MODIFIED_COUNT++))
    
    if is_expected_difference "$file"; then
      MODIFIED_EXPECTED="${MODIFIED_EXPECTED}${file}\n"
      ((MODIFIED_EXPECTED_COUNT++))
    else
      MODIFIED_UNEXPECTED="${MODIFIED_UNEXPECTED}${file}\n"
      ((MODIFIED_UNEXPECTED_COUNT++))
    fi
  fi
done <<< "$BOTH_FILES"

# Summary
TOTAL_EXPECTED=$((DEV_ONLY_EXPECTED_COUNT + PROD_ONLY_EXPECTED_COUNT + MODIFIED_EXPECTED_COUNT))
TOTAL_UNEXPECTED=$((DEV_ONLY_UNEXPECTED_COUNT + PROD_ONLY_UNEXPECTED_COUNT + MODIFIED_UNEXPECTED_COUNT))

echo "╔════════════════════════════════════════════════════════╗"
echo "║            CONFIGURATION COMPARISON                     ║"
echo "╠════════════════════════════════════════════════════════╣"
echo "║ Dev files:      ${DEV_COUNT}                                      ║"
echo "║ Prod files:     ${PROD_COUNT}                                      ║"
echo "║                                                        ║"
echo "║ Only in DEV:    ${DEV_ONLY_COUNT} (${DEV_ONLY_EXPECTED_COUNT} expected, ${DEV_ONLY_UNEXPECTED_COUNT} unexpected)      ║"
echo "║ Only in PROD:   ${PROD_ONLY_COUNT} (${PROD_ONLY_EXPECTED_COUNT} expected, ${PROD_ONLY_UNEXPECTED_COUNT} unexpected)      ║"
echo "║ Modified:       ${MODIFIED_COUNT} (${MODIFIED_EXPECTED_COUNT} expected, ${MODIFIED_UNEXPECTED_COUNT} unexpected)      ║"
echo "║                                                        ║"
echo "║ Total expected differences:   ${TOTAL_EXPECTED}                     ║"
echo "║ Total UNEXPECTED differences: ${TOTAL_UNEXPECTED}                     ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""

if [ "$TOTAL_UNEXPECTED" -eq 0 ]; then
  echo "✅ All differences are expected! Environments are in sync."
  echo ""
else
  echo "⚠️  Found ${TOTAL_UNEXPECTED} unexpected difference(s) - review needed!"
  echo ""
fi

# Show unexpected differences first (these need attention)
if [ "$DEV_ONLY_UNEXPECTED_COUNT" -gt 0 ]; then
  echo "🚨 UNEXPECTED files only in DEVELOPMENT (${DEV_ONLY_UNEXPECTED_COUNT}):"
  echo -e "$DEV_ONLY_UNEXPECTED" | grep -v '^$' | head -20
  [ "$DEV_ONLY_UNEXPECTED_COUNT" -gt 20 ] && echo "... and $((DEV_ONLY_UNEXPECTED_COUNT - 20)) more"
  echo ""
fi

if [ "$PROD_ONLY_UNEXPECTED_COUNT" -gt 0 ]; then
  echo "🚨 UNEXPECTED files only in PRODUCTION (${PROD_ONLY_UNEXPECTED_COUNT}):"
  echo -e "$PROD_ONLY_UNEXPECTED" | grep -v '^$' | head -20
  [ "$PROD_ONLY_UNEXPECTED_COUNT" -gt 20 ] && echo "... and $((PROD_ONLY_UNEXPECTED_COUNT - 20)) more"
  echo ""
fi

if [ "$MODIFIED_UNEXPECTED_COUNT" -gt 0 ]; then
  echo "🚨 UNEXPECTED modified files (${MODIFIED_UNEXPECTED_COUNT}):"
  echo -e "$MODIFIED_UNEXPECTED" | grep -v '^$' | head -20
  [ "$MODIFIED_UNEXPECTED_COUNT" -gt 20 ] && echo "... and $((MODIFIED_UNEXPECTED_COUNT - 20)) more"
  echo ""
fi

# Show expected differences summary (collapsed unless verbose)
if [ "$TOTAL_EXPECTED" -gt 0 ]; then
  echo "✓ Expected differences (${TOTAL_EXPECTED} files) - documented in .config-differences.yml"
  if [ -n "${VERBOSE}" ]; then
    [ "$DEV_ONLY_EXPECTED_COUNT" -gt 0 ] && echo "  - ${DEV_ONLY_EXPECTED_COUNT} files only in dev (expected)"
    [ "$PROD_ONLY_EXPECTED_COUNT" -gt 0 ] && echo "  - ${PROD_ONLY_EXPECTED_COUNT} files only in prod (expected)"
    [ "$MODIFIED_EXPECTED_COUNT" -gt 0 ] && echo "  - ${MODIFIED_EXPECTED_COUNT} modified files (expected)"
  else
    echo "  (run with VERBOSE=1 to see details)"
  fi
  echo ""
fi

# Save detailed diff
DIFF_REPORT="${COMPARE_DIR}/diff-report.txt"
{
  echo "==================================="
  echo "Config Comparison Report"
  echo "Generated: $(date)"
  echo "Site: ${SITE_NAME}"
  echo "==================================="
  echo ""

if [ "$TOTAL_UNEXPECTED" -gt 0 ]; then
  echo "⚠️  Action required - unexpected differences found:"
  echo ""
  echo "1. Review unexpected differences:"
  echo "   less ${DIFF_REPORT}"
  echo ""
  echo "2. If differences are intentional:"
  echo "   - Update ${SITE_PATH}/.config-differences.yml"
  echo "   - Document the pattern and reason"
  echo ""
  echo "3. If production should be source of truth:"
  echo "   ./reconcile-config.sh ${SITE_NAME} ${SSH_HOST} use-prod"
  echo ""
  echo "4. If dev changes should go to production:"
  echo "   ./reconcile-config.sh ${SITE_NAME} ${SSH_HOST} use-dev"
  echo ""
else
  echo "✅ No action required - all differences are expected"
  echo ""
  echo "To sync anyway (e.g., after updating expected differences):"
  echo "   ./reconcile-config.sh ${SITE_NAME} ${SSH_HOST} use-prod"
  echo ""
fi

echo "5. For manual review:"
echo "   Dev config:  ${COMPARE_DIR}/dev/"
echo "   Prod config: ${COMPARE_DIR}/prod/"
echo "   Full report: ${DIFF_REPORT}="
  
  # Show diffs for modified files
  while IFS= read -r file; do
    if [ -n "$file" ] && [ -f "${COMPARE_DIR}/dev/$file" ] && [ -f "${COMPARE_DIR}/prod/$file" ]; then
      echo ""
      echo "--- $file ---"
      diff -u "${COMPARE_DIR}/prod/$file" "${COMPARE_DIR}/dev/$file" || true
    fi
  done <<< "$(echo -e "$MODIFIED_FILES" | grep -v '^$')"
} > "${DIFF_REPORT}"

echo "📄 Full report saved: ${DIFF_REPORT}"
echo ""
echo "🔧 Next steps:"
echo ""
echo "1. Review differences:"
echo "   less ${DIFF_REPORT}"
echo ""
echo "2. To use PRODUCTION config (recommended for sync):"
echo "   ./reconcile-config.sh ${SITE_NAME} ${SSH_HOST} use-prod"
echo ""
echo "3. To use DEVELOPMENT config (careful!):"
echo "   ./reconcile-config.sh ${SITE_NAME} ${SSH_HOST} use-dev"
echo ""
echo "4. For manual reconciliation:"
echo "   Dev config:  ${COMPARE_DIR}/dev/"
echo "   Prod config: ${COMPARE_DIR}/prod/"
echo ""

# Keep comparison directory for manual review
echo "⚠️  Comparison files preserved at: ${COMPARE_DIR}"
echo "   Delete when done: rm -rf ${COMPARE_DIR}"
