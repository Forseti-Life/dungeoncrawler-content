#!/usr/bin/env bash
# route-check.sh — forseti.life + dungeoncrawler anonymous route health check
# Owner: agent-explore-forseti
# Usage: bash route-check.sh [FORSETI_BASE_URL] [DC_BASE_URL]
# Defaults: http://localhost (forseti.life), http://localhost:8080 (dungeoncrawler)
# Returns: 0 if all routes match expected status; non-zero on any unexpected result.

FORSETI_URL="${1:-http://localhost}"
DC_URL="${2:-http://localhost:8080}"
PASS=0
FAIL=0

check() {
  local label="$1"
  local method="$2"
  local base="$3"
  local path="$4"
  local expected="$5"

  actual=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "${base}${path}" 2>/dev/null)
  if [ "$actual" = "$expected" ]; then
    echo "PASS  [$actual] $method $path  ($label)"
    PASS=$((PASS + 1))
  else
    echo "FAIL  [got=$actual expected=$expected] $method $path  ($label)"
    FAIL=$((FAIL + 1))
  fi
}

echo "=== forseti.life + dungeoncrawler route check — $(date -u +%Y-%m-%dT%H:%M:%SZ) ==="
echo "FORSETI_URL: $FORSETI_URL  |  DC_URL: $DC_URL"
echo ""

# --- forseti.life routes ---
echo "-- forseti.life --"
check "site-root"              GET "$FORSETI_URL" "/"                    200
check "user-login"             GET "$FORSETI_URL" "/user/login"          200
check "games-home (anon)"      GET "$FORSETI_URL" "/games"               403
check "block-matcher (anon)"   GET "$FORSETI_URL" "/games/block-matcher" 403
check "api-high-scores (anon)" GET "$FORSETI_URL" "/api/games/high-scores/1" 200
check "jobhunter (anon)"       GET "$FORSETI_URL" "/jobhunter"               403
check "jobhunter-home (anon)"  GET "$FORSETI_URL" "/jobhunter/home"          403
check "forseti-404-check"      GET "$FORSETI_URL" "/nonexistent-route-smoke-test-xyz" 404
echo ""

# --- dungeoncrawler routes (dungeoncrawler_content module) ---
echo "-- dungeoncrawler --"
check "dc-home"        GET "$DC_URL" "/home"        200
check "dc-world"       GET "$DC_URL" "/world"       200
check "dc-how-to-play" GET "$DC_URL" "/how-to-play" 200
check "dc-about"       GET "$DC_URL" "/about"       200
check "dc-architecture" GET "$DC_URL" "/architecture" 200
check "dc-credits"     GET "$DC_URL" "/credits"     200
check "dc-hexmap"      GET "$DC_URL" "/hexmap"      200
check "dc-user-login"  GET "$DC_URL" "/user/login"  200
check "dc-ancestries"  GET "$DC_URL" "/ancestries"  200
check "dc-traits-catalog (anon)" GET "$DC_URL" "/dungeoncrawler/traits" 403
check "dc-characters-create (anon)" GET "$DC_URL" "/characters/create" 403
check "dc-campaigns-chars (anon)" GET "$DC_URL" "/campaigns/1/characters" 403
check "dc-admin (anon)" GET "$DC_URL" "/admin/content/dungeoncrawler" 403
check "dc-404-check"   GET "$DC_URL" "/nonexistent-route-smoke-test-xyz" 404
echo ""

echo "=== Results: PASS=$PASS FAIL=$FAIL ==="
[ "$FAIL" -eq 0 ] && exit 0 || exit 1
