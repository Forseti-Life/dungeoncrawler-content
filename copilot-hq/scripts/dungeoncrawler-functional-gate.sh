#!/usr/bin/env bash

set -euo pipefail

DRY_RUN=0
if [ "${1:-}" = "--dry-run" ]; then
  DRY_RUN=1
  shift
fi

SITE_ROOT="${DUNGEONCRAWLER_SITE_ROOT:-/home/ubuntu/forseti.life/sites/dungeoncrawler}"
ARTIFACTS_ROOT="${DUNGEONCRAWLER_QA_ARTIFACTS_ROOT:-/home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/artifacts/phpunit-functional}"
BASE_URL="${DUNGEONCRAWLER_SIMPLETEST_BASE_URL:-${SIMPLETEST_BASE_URL:-http://localhost:8080}}"
DB_DSN="${DUNGEONCRAWLER_SIMPLETEST_DB:-${SIMPLETEST_DB:-}}"
DB_PASSWORD_VALUE="${DUNGEONCRAWLER_DB_PASSWORD:-${DB_PASSWORD:-}}"

if [ -z "$DB_DSN" ] && [ -n "$DB_PASSWORD_VALUE" ]; then
  DB_DSN="mysql://drupal_user:${DB_PASSWORD_VALUE}@127.0.0.1:3306/dungeoncrawler_dev"
fi

if [ -z "$DB_DSN" ]; then
  echo "ERROR: Dungeoncrawler functional gate requires SIMPLETEST_DB." >&2
  echo "Set one of: DUNGEONCRAWLER_SIMPLETEST_DB, SIMPLETEST_DB, DUNGEONCRAWLER_DB_PASSWORD, or DB_PASSWORD." >&2
  exit 2
fi

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
ARTIFACT_DIR="${ARTIFACTS_ROOT}/${TIMESTAMP}"
LOG_FILE="${ARTIFACT_DIR}/phpunit-output.txt"

mkdir -p "$ARTIFACT_DIR"

if [ "$DRY_RUN" -eq 1 ]; then
  echo "DRY RUN: would run Dungeoncrawler functional PHPUnit gate"
  echo "SITE_ROOT=$SITE_ROOT"
  echo "SIMPLETEST_BASE_URL=$BASE_URL"
  echo "SIMPLETEST_DB configured=yes"
  echo "LOG_FILE=$LOG_FILE"
  exit 0
fi

if ! curl -fsS --max-time 5 "$BASE_URL" >/dev/null; then
  echo "ERROR: Dungeoncrawler functional gate requires a reachable local site at $BASE_URL" >&2
  exit 3
fi

cd "$SITE_ROOT"
export SIMPLETEST_DB="$DB_DSN"
export SIMPLETEST_BASE_URL="$BASE_URL"

./tests/setup.sh >/dev/null
./tests/run-tests.sh --testsuite=functional "$@" 2>&1 | tee "$LOG_FILE"
