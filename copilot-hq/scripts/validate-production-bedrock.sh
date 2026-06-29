#!/usr/bin/env bash
set -euo pipefail

# validate-production-bedrock.sh
#
# End-to-end production validation for HQ + Bedrock assistant integration.
# Safe by default: performs checks and read-only diagnostics.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SITE="${1:-forseti}"
STRICT="${STRICT:-1}"

pass() { echo "VALIDATE: OK   - $*"; }
warn() { echo "VALIDATE: WARN - $*" >&2; }
fail() { echo "VALIDATE: FAIL - $*" >&2; exit 1; }

if [ ! -x "./scripts/bedrock-assist.sh" ]; then
  fail "missing executable scripts/bedrock-assist.sh"
fi
pass "bedrock assistant script present"

if ! command -v python3 >/dev/null 2>&1; then
  fail "python3 not found"
fi
pass "python3 available"

if ! command -v sudo >/dev/null 2>&1; then
  warn "sudo not found; Bedrock script may fail if current user is not www-data"
else
  pass "sudo available"
fi

if ! env HQ_AGENTIC_BACKEND=bedrock BEDROCK_ASSIST_SCRIPT="$ROOT_DIR/scripts/bedrock-assist.sh" ./scripts/verify-hq-runtime.sh >/dev/null 2>&1; then
  if [ "$STRICT" = "1" ]; then
    fail "verify-hq-runtime failed in bedrock mode"
  fi
  warn "verify-hq-runtime failed in bedrock mode"
else
  pass "runtime verification passed in bedrock mode"
fi

VALIDATION_PROMPT="Respond with exactly: BEDROCK_VALIDATION_OK"
RESP="$(BEDROCK_MAX_TOKENS=80 BEDROCK_OPERATION=prod_bedrock_validation ./scripts/bedrock-assist.sh "$SITE" "$VALIDATION_PROMPT" 2>/dev/null || true)"

if printf '%s' "$RESP" | grep -q "BEDROCK_VALIDATION_OK"; then
  pass "bedrock assistant invocation returned validation token"
else
  if [ "$STRICT" = "1" ]; then
    fail "bedrock assistant invocation failed validation token check"
  fi
  warn "bedrock assistant invocation did not return expected validation token"
fi

pass "production Bedrock validation complete"
