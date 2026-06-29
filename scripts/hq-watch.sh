#!/usr/bin/env bash
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
interval="${1:-2}"
while true; do
  clear || true
  ./scripts/hq-status.sh
  sleep "$interval"
done
