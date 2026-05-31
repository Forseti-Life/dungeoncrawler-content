#!/usr/bin/env bash
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"

ts="$(date -Iseconds)"
logdir="inbox/responses"
mkdir -p "$logdir"

status_out=$(./scripts/hq-status.sh 2>/dev/null | head -n 80 || true)
breaches=$(./scripts/sla-report.sh 2>/dev/null | grep -c '^BREACH' || true)

{
  echo "[$ts] Forseti triage"
  echo "$status_out"
  echo
  echo "SLA breaches: $breaches"
  if [ "$breaches" -gt 0 ]; then
    ./scripts/sla-report.sh | grep '^BREACH' | head -n 50
  fi
  echo "----"
} > "$logdir/forseti-triage-latest.log"
