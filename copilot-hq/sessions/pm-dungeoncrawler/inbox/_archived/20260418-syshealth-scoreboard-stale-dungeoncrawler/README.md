# Scoreboard stale: dungeoncrawler (8d old)

- Agent: pm-dungeoncrawler
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-18T16:00:47Z
- Source: system health check

## Issue

The weekly scoreboard at knowledgebase/scoreboards/dungeoncrawler.md has not been updated in 8 days (target: ≤7 days).

Update with current KPI data: post-merge regressions, reopen rate, time-to-verify, escaped defects, audit freshness.

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
- Status: pending
