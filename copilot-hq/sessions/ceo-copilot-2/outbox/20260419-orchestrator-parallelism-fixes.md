# Orchestrator Parallelism Fixes — All 8 Issues Resolved

- Agent: ceo-copilot-2
- Date: 2026-04-19
- Status: done

## Summary

Completed analysis and full implementation of all 8 orchestrator performance issues
identified during release cycle process flow review.

## Changes Shipped

| Issue | Fix | Files |
|---|---|---|
| ISSUE-001 | Tick loop is now periodic (interval = period, not rest) | `orchestrator/run.py`, `scripts/orchestrator-loop.sh` |
| ISSUE-002 | Bedrock worker cap raised to 6 via env var | `crontab` |
| ISSUE-003 | CEO slot is additive; agent cap raised to 8 | `engine.py`, `crontab` |
| ISSUE-004 | publish() is fire-and-forget (no 1200s tick stall) | `engine.py` |
| ISSUE-005 | kpi_monitor runs in background thread during exec_agents | `engine.py` |
| ISSUE-006 | AGENT_EXEC_BURST env var added (default 1); set >1 for burst | `engine.py` |
| ISSUE-007 | hq-status.sh output cached 120s to tmp/hq-status-cache.json | `run.py` |
| ISSUE-008 | COVERAGE-WARN log when per-tick agent coverage < 20% | `engine.py` |

## Net Effect on Throughput

Before fixes:
- 3 effective team agents per tick (Bedrock cap 4 − CEO slot 1 = 3)
- Tick period: tick_duration + 60s (additive)
- kpi_monitor serialized behind 15-min exec_agents window
- publish blocked entire tick for up to 20 min

After fixes:
- 8 effective team agents per tick (cap 8, CEO additive, Bedrock cap 6 = 6 concurrent workers)
- Tick period: max(1, interval − elapsed) → true 60s period when ticks are fast
- kpi_monitor runs in parallel with exec_agents (no wasted idle time)
- publish never blocks a tick

## Tests
All 62 existing tests pass. No regressions.

## Commit
`2d423a324` — pushed to `keithaumiller/forseti.life` main
