#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
POLICY_DIR="$ROOT_DIR/policies/analysis"

usage() {
  cat <<'USAGE'
Usage:
  scripts/analysis-policy-check.sh <analysis-file.{yaml,yml,json}> [...]

Behavior:
  - Runs Conftest against policies/analysis
  - Fails non-zero on any policy denial
USAGE
}

if [ "$#" -lt 1 ]; then
  usage
  exit 1
fi

if command -v conftest >/dev/null 2>&1; then
  CONFTEST_BIN="conftest"
elif [ -x "$ROOT_DIR/tools/bin/conftest" ]; then
  CONFTEST_BIN="$ROOT_DIR/tools/bin/conftest"
else
  echo "ERROR: conftest not found in PATH or tools/bin."
  echo "Run: scripts/install-opa-conftest.sh"
  exit 1
fi

echo "Policy dir: $POLICY_DIR"
echo "Conftest: $CONFTEST_BIN"

"$CONFTEST_BIN" test --policy "$POLICY_DIR" --namespace hq.analysis "$@"
