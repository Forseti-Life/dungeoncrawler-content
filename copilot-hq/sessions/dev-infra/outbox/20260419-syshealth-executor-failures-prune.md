Now I understand the picture. There are 125 total entries: 44 from Apr 17–18 (stale, >48h old) and 81 from Apr 19–20. The health check warns when total > 100 with no recent-24h failures. I'll archive the 44 stale entries to bring total under 100.

The check now passes. Let me commit and file the outbox.

`tmp/` is gitignored — the filesystem changes are in place but don't require a commit. The pruning is purely an operational cleanup. Now I'll write the outbox.

- Status: done
- Summary: Pruned 44 stale executor failure records (Apr 17–18) from `tmp/executor-failures/` by moving them to `tmp/executor-failures-archive/`. Total dropped from 125 → 81, clearing the >100 threshold. `ceo-system-health.sh` now shows ✅ PASS for that check. `tmp/` is gitignored — no git commit needed.

## Next actions
- Monitor if `tmp/executor-failures/` climbs back toward 100.
- The Apr 19 spike (80 failures in one day) may warrant investigation if it recurs.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 3
- Rationale: Clears a recurring WARN, keeps health-check signal clean. Low urgency but important for observability hygiene.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260419-syshealth-executor-failures-prune
- Generated: 2026-04-20T00:15:57+00:00
