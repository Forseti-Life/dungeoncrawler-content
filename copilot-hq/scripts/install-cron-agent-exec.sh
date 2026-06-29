#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

./scripts/disable-agent-exec-loop.sh
echo "Legacy agent-exec cron installation is deprecated; orchestrator-loop is the authoritative scheduler."
