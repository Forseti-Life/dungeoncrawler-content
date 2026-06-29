#!/usr/bin/env bash
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
mkdir -p inbox/responses
[ -f inbox/responses/latest.log ] || : > inbox/responses/latest.log
exec tail -f inbox/responses/latest.log
