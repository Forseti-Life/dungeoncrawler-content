#!/usr/bin/env bash
# multirepository-validator.sh — Validate access to all Forseti-Life public repositories
#
# Validates:
#   • GitHub token valid and has org access
#   • All 11 public repos accessible
#   • Repository structure and key files present
#   • Git remotes configured correctly
#
# Usage:
#   bash scripts/multirepository-validator.sh
#   bash scripts/multirepository-validator.sh --clone-test    # test cloning each repo
#   bash scripts/multirepository-validator.sh --full          # full validation with clone test
#
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PASS="✅ PASS"
FAIL="❌ FAIL"
WARN="⚠️  WARN"
INFO="   ℹ️ "

FAILURES=0
CLONE_TEST=0
FULL_MODE=0

for arg in "$@"; do
  [ "$arg" = "--clone-test" ] && CLONE_TEST=1
  [ "$arg" = "--full" ] && FULL_MODE=1 && CLONE_TEST=1
done

fail() { echo "$FAIL $*"; FAILURES=$((FAILURES + 1)); }
pass() { echo "$PASS $*"; }
warn() { echo "$WARN $*"; }
info() { echo "$INFO $*"; }
hr() { echo "────────────────────────────────────────────────────────"; }

# ── TOKEN VALIDATION ────────────────────────────────────────────────────────
echo
echo "═══════════════════════════════════════════════════════"
echo "  Multi-Repository Access Validator"
echo "  $(date -u '+%Y-%m-%dT%H:%M:%SZ')"
echo "═══════════════════════════════════════════════════════"
echo

TOKEN_FILE="/home/ubuntu/github.token"
if [ ! -f "$TOKEN_FILE" ]; then
  fail "Token file not found: $TOKEN_FILE"
  exit 1
fi

TOKEN=$(cat "$TOKEN_FILE")
if [ -z "$TOKEN" ]; then
  fail "Token file is empty: $TOKEN_FILE"
  exit 1
fi

export GH_TOKEN="$TOKEN"

# ── PERSONAL ACCOUNT ACCESS ─────────────────────────────────────────────────
echo
hr
echo "  Step 1: Personal Account Access"
hr

if gh api /user -q '.login' >/dev/null 2>&1; then
  USER_LOGIN=$(gh api /user -q '.login' 2>/dev/null)
  pass "Personal account accessible: $USER_LOGIN"
else
  fail "Cannot access personal GitHub account (invalid token or wrong scopes)"
  exit 1
fi

# ── ORGANIZATION ACCESS ─────────────────────────────────────────────────────
echo
hr
echo "  Step 2: Organization Access"
hr

if gh api orgs/Forseti-Life -q '.login' >/dev/null 2>&1; then
  ORG_LOGIN=$(gh api orgs/Forseti-Life -q '.login' 2>/dev/null)
  pass "Organization accessible: $ORG_LOGIN"
else
  fail "Cannot access Forseti-Life organization (check token org:read scope)"
  exit 1
fi

# ── REPOSITORY INVENTORY ────────────────────────────────────────────────────
echo
hr
echo "  Step 3: Repository Inventory"
hr

REPOS=$(gh repo list Forseti-Life --limit 100 --json nameWithOwner,description,isPrivate -q '.[].nameWithOwner' 2>/dev/null || true)

if [ -z "$REPOS" ]; then
  fail "Could not list repositories in Forseti-Life organization"
  exit 1
fi

REPO_COUNT=$(echo "$REPOS" | wc -l)
pass "Found $REPO_COUNT repositories in Forseti-Life organization"

echo
info "Repository list:"
echo "$REPOS" | sed 's/^/  → /'

# ── EXPECTED REPOS VALIDATION ───────────────────────────────────────────────
echo
hr
echo "  Step 4: Expected Repositories Validation"
hr

EXPECTED_REPOS=(
  "Forseti-Life/forseti-job-hunter"
  "Forseti-Life/dungeoncrawler-pf2e"
  "Forseti-Life/forseti-shared-modules"
  "Forseti-Life/forseti-mobile"
  "Forseti-Life/forseti-meshd"
  "Forseti-Life/h3-geolocation"
  "Forseti-Life/copilot-hq"
  "Forseti-Life/forseti-devops"
  "Forseti-Life/forseti-docs"
  "Forseti-Life/dungeoncrawler-content"
  "Forseti-Life/forseti-platform-specs"
)

FOUND=0
MISSING=0

for expected_repo in "${EXPECTED_REPOS[@]}"; do
  if echo "$REPOS" | grep -q "^${expected_repo}$"; then
    pass "$expected_repo exists"
    FOUND=$((FOUND + 1))
  else
    fail "$expected_repo NOT FOUND"
    MISSING=$((MISSING + 1))
  fi
done

echo
info "Expected: ${#EXPECTED_REPOS[@]} | Found: $FOUND | Missing: $MISSING"

# ── CLONE TEST (Optional) ───────────────────────────────────────────────────
if [ "$CLONE_TEST" = "1" ]; then
  echo
  hr
  echo "  Step 5: Clone Test (sampling repos)"
  hr

  TEST_REPOS=(
    "Forseti-Life/forseti-job-hunter"
    "Forseti-Life/copilot-hq"
    "Forseti-Life/dungeoncrawler-pf2e"
  )

  TEMP_DIR=$(mktemp -d)
  trap "rm -rf $TEMP_DIR" EXIT

  for test_repo in "${TEST_REPOS[@]}"; do
    REPO_NAME=$(echo "$test_repo" | cut -d/ -f2)
    CLONE_URL="https://github.com/${test_repo}.git"

    if git clone --depth 1 "$CLONE_URL" "$TEMP_DIR/$REPO_NAME" >/dev/null 2>&1; then
      pass "Can clone: $test_repo"
      if [ -f "$TEMP_DIR/$REPO_NAME/README.md" ]; then
        pass "  ✓ Has README.md"
      else
        warn "  ⚠ Missing README.md"
      fi
    else
      fail "Cannot clone: $test_repo"
    fi
  done
fi

# ── GIT REMOTES CHECK ───────────────────────────────────────────────────────
echo
hr
echo "  Step 6: Local Git Remotes"
hr

echo
info "Current remotes:"
git remote -v | sed 's/^/  /'

# Check for embedded tokens
if git remote -v | grep -q "ghp_"; then
  fail "Found embedded GitHub token in git remotes (security risk)"
  warn "Run: git remote set-url origin https://github.com/keithaumiller/forseti.life.git"
  warn "Run: git remote set-url community https://github.com/Forseti-Life/forseti.life.git"
else
  pass "No embedded tokens in git remotes"
fi

# ── SUMMARY ─────────────────────────────────────────────────────────────────
echo
hr
echo "  Validation Summary"
hr

if [ "$FAILURES" = "0" ]; then
  pass "All validation checks passed ✅"
  echo
  exit 0
else
  fail "Validation failed with $FAILURES error(s) ❌"
  echo
  exit 1
fi
