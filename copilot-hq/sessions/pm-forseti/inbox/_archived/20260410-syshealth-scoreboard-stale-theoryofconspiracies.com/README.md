# Scoreboard stale: theoryofconspiracies.com (18d old)

- Agent: pm-forseti
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-10T14:18:27Z
- Source: system health check

## Issue

The weekly scoreboard at knowledgebase/scoreboards/theoryofconspiracies.com.md has not been updated in 18 days (target: ≤7 days).\n\nUpdate with current KPI data: post-merge regressions, reopen rate, time-to-verify, escaped defects, audit freshness.

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
