#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SITE_DIR="${ROOT_DIR}/sites/dungeoncrawler"
DRUSH="${SITE_DIR}/vendor/bin/drush"
BASE_URL="${PLAYWRIGHT_BASE_URL:-http://localhost:8080}"

if [ ! -x "${DRUSH}" ]; then
  echo "ERROR: Drush not found at ${DRUSH}" >&2
  exit 1
fi

run_drush() {
  "${DRUSH}" --root="${SITE_DIR}/web" --uri="${BASE_URL}" "$@"
}

ensure_role() {
  local role="$1"
  shift
  if ! run_drush role:list --format=list | grep -q "^${role}$"; then
    run_drush role:create "${role}"
  fi
  if [ "$#" -gt 0 ]; then
    local perms
    perms=$(IFS=','; echo "$*")
    run_drush role:perm:add "${role}" "${perms}" || true
  fi
}

ensure_user() {
  local username="$1"
  local email="$2"
  local password="$3"

  if ! run_drush user:information "${username}" --fields=uid --format=table >/dev/null 2>&1; then
    run_drush user:create "${username}" --mail="${email}" --password="${password}"
  else
    run_drush user:password "${username}" "${password}"
  fi
}

assign_role() {
  local role="$1"
  local username="$2"
  run_drush user:role:add "${role}" "${username}" || true
}

PLAYER_ROLE="dc_playwright_player"
ADMIN_ROLE="dc_playwright_admin"

PLAYER_USER="playwright_player"
ADMIN_USER="admin"

PLAYER_PASS="${PLAYWRIGHT_PLAYER_PASS:-}"
ADMIN_PASS="${PLAYWRIGHT_ADMIN_PASS:-}"

PLAYER_EMAIL="${PLAYWRIGHT_PLAYER_EMAIL:-playwright_player@dungeoncrawler.local}"
ADMIN_EMAIL="${PLAYWRIGHT_ADMIN_EMAIL:-admin@dungeoncrawler.local}"

if [ -z "${PLAYER_PASS}" ] || [ -z "${ADMIN_PASS}" ]; then
  echo "ERROR: PLAYWRIGHT_PLAYER_PASS and PLAYWRIGHT_ADMIN_PASS must be set." >&2
  exit 1
fi

ensure_role "${PLAYER_ROLE}" \
  "access dungeoncrawler characters" \
  "create dungeoncrawler characters" \
  "edit own dungeoncrawler characters" \
  "delete own dungeoncrawler characters" \
  "generate dungeons" \
  "generate rooms"

ensure_role "${ADMIN_ROLE}" \
  "administer site configuration" \
  "administer dungeoncrawler content" \
  "edit any dungeoncrawler characters" \
  "delete any dungeoncrawler characters"

ensure_user "${PLAYER_USER}" "${PLAYER_EMAIL}" "${PLAYER_PASS}"
ensure_user "${ADMIN_USER}" "${ADMIN_EMAIL}" "${ADMIN_PASS}"

assign_role "${PLAYER_ROLE}" "${PLAYER_USER}"
assign_role "${ADMIN_ROLE}" "${ADMIN_USER}"

PLAYER_LOGIN_URL=$(run_drush uli --name="${PLAYER_USER}" --uri="${BASE_URL}" | tr -d '\n')
ADMIN_LOGIN_URL=$(run_drush uli --name="${ADMIN_USER}" --uri="${BASE_URL}" | tr -d '\n')

cat <<EOF
Playwright auth users ready.

Player user: ${PLAYER_USER}
Admin user: ${ADMIN_USER}

Export for player tests:
  export PLAYWRIGHT_LOGIN_URL="${PLAYER_LOGIN_URL}"

Export for admin tests:
  export PLAYWRIGHT_LOGIN_URL="${ADMIN_LOGIN_URL}"

Base URL used: ${BASE_URL}
EOF
