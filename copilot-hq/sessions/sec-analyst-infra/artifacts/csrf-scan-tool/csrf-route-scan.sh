#!/usr/bin/env bash
# csrf-route-scan.sh
# Scans Drupal routing.yml files for POST routes missing CSRF protection.
#
# Usage:
#   bash csrf-route-scan.sh [REPO_ROOT]
#
# REPO_ROOT defaults to current directory.
#
# Exit codes:
#   0 = no flagged routes
#   1 = at least one flagged route found
#
# Draft artifact — proposed to dev-infra for adoption into scripts/
# See: sessions/sec-analyst-infra/artifacts/csrf-scan-tool/README.md

set -euo pipefail

REPO_ROOT="${1:-$(pwd)}"
FLAGGED=0

# Known CSRF-safe patterns in requirements block (within ~15 lines of a route):
#   _csrf_token: 'TRUE'
#   _csrf_request_header_mode: 'TRUE'
#   _form: '...'  (Drupal form API provides auto-CSRF)
#   _no_csrf_token is NOT an exception; it is an explicit opt-out — flag it.

echo "=== CSRF Route Scan ==="
echo "Repo root: $REPO_ROOT"
echo ""

while IFS= read -r -d '' routing_file; do
  # Parse the routing.yml file route by route.
  # Strategy: extract each route block (route name + indented block), then check.

  route_name=""
  in_requirements=0
  has_post_method=0
  has_csrf=0
  has_form=0
  line_num=0
  route_line=0

  while IFS= read -r line || [[ -n "$line" ]]; do
    line_num=$((line_num + 1))

    # Detect route name (starts at column 0, ends with colon, not a key-value)
    if [[ "$line" =~ ^([a-zA-Z_][a-zA-Z0-9_.]*):[[:space:]]*$ ]]; then
      # New route — emit flag for previous route if needed
      if [[ -n "$route_name" && "$has_post_method" -eq 1 && "$has_csrf" -eq 0 && "$has_form" -eq 0 ]]; then
        echo "FLAG: $routing_file (line $route_line): $route_name — POST route missing _csrf_token or _csrf_request_header_mode"
        FLAGGED=$((FLAGGED + 1))
      fi
      # Reset for new route
      route_name="${BASH_REMATCH[1]}"
      route_line=$line_num
      in_requirements=0
      has_post_method=0
      has_csrf=0
      has_form=0
      continue
    fi

    # Detect methods block
    if [[ "$line" =~ methods:[[:space:]]*\[([^]]*)\] ]]; then
      methods_val="${BASH_REMATCH[1]}"
      if [[ "$methods_val" =~ POST ]]; then
        has_post_method=1
      fi
    fi

    # Detect requirements block
    if [[ "$line" =~ ^[[:space:]]+requirements:[[:space:]]*$ ]]; then
      in_requirements=1
      continue
    fi

    # Inside requirements: look for CSRF keys or _form
    if [[ "$in_requirements" -eq 1 ]]; then
      if [[ "$line" =~ _csrf_token|_csrf_request_header_mode ]]; then
        has_csrf=1
      fi
      if [[ "$line" =~ _form:[[:space:]] ]]; then
        has_form=1
      fi
      # End of requirements block (next non-indented-enough line)
      if [[ "$line" =~ ^[[:space:]]{0,2}[a-zA-Z_] && ! "$line" =~ ^[[:space:]]{4} ]]; then
        in_requirements=0
      fi
    fi

    # _form in defaults also provides auto-CSRF
    if [[ "$line" =~ _form:[[:space:]] ]]; then
      has_form=1
    fi

  done < "$routing_file"

  # Emit for last route in file
  if [[ -n "$route_name" && "$has_post_method" -eq 1 && "$has_csrf" -eq 0 && "$has_form" -eq 0 ]]; then
    echo "FLAG: $routing_file (line $route_line): $route_name — POST route missing _csrf_token or _csrf_request_header_mode"
    FLAGGED=$((FLAGGED + 1))
  fi

done < <(find "$REPO_ROOT" -name "*.routing.yml" -not -path "*/node_modules/*" -not -path "*/.git/*" -print0 | sort -z)

echo ""
if [[ "$FLAGGED" -eq 0 ]]; then
  echo "PASS: No flagged routes found."
  exit 0
else
  echo "FAIL: $FLAGGED flagged route(s) found. Add _csrf_token: 'TRUE' to each requirements block."
  exit 1
fi
