# HQ repo has merge/integration blockers

- Agent: dev-infra
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: 2026-04-20T14:00:53Z
- Source: system health check

## Issue

The HQ repo has merge/integration blockers.

Summary: 31 tracked local change(s), 15 untracked file(s)

Details:
```
Tracked change: copilot-hq/dashboards/FEATURE_PROGRESS.md
Tracked change: copilot-hq/inbox/commands/20260420-134523-release-handoff-full-investigation.md
Tracked change: copilot-hq/inbox/commands/20260420-134523-release-handoff-gap.md
Tracked change: copilot-hq/inbox/commands/20260420-134523-release-kpi-stagnation.md
Tracked change: copilot-hq/inbox/responses/langgraph-parity-latest.json
Tracked change: copilot-hq/inbox/responses/langgraph-ticks.jsonl
Tracked change: copilot-hq/sessions/ceo-copilot-2/inbox/20260420-needs-ceo-copilot-2-auto-investigate-fix/roi.txt
Tracked change: copilot-hq/sessions/ceo-copilot-2/inbox/20260420-needs-ceo-copilot-2-stagnation-full-analysis/roi.txt
Tracked change: copilot-hq/sessions/ceo-copilot-2/inbox/20260420-needs-pm-forseti-20260420-sla-outbox-lag-qa-forseti-20260419-unit-test-202604/roi.txt
Tracked change: copilot-hq/sessions/pm-dungeoncrawler/artifacts/active-inbox-item.json
Tracked change: copilot-hq/sessions/pm-dungeoncrawler/inbox/20260420-groom-20260412-dungeoncrawler-release-t/.inwork
Tracked change: copilot-hq/sessions/pm-dungeoncrawler/inbox/20260420-groom-20260412-dungeoncrawler-release-t/command.md
Tracked change: copilot-hq/sessions/pm-dungeoncrawler/inbox/20260420-groom-20260412-dungeoncrawler-release-t/roi.txt
Tracked change: copilot-hq/sessions/pm-forseti/inbox/20260420-groom-20260412-forseti-release-r/roi.txt
Tracked change: copilot-hq/sessions/pm-forseti/inbox/20260420-post-push-20260412-dungeoncrawler-release-s/roi.txt
Tracked change: copilot-hq/sessions/pm-forseti/inbox/20260420-release-handoff-full-investigation/README.md
Tracked change: copilot-hq/sessions/pm-forseti/inbox/20260420-release-handoff-full-investigation/roi.txt
Tracked change: copilot-hq/sessions/pm-forseti/inbox/20260420-release-handoff-gap/README.md
Tracked change: copilot-hq/sessions/pm-forseti/inbox/20260420-release-handoff-gap/roi.txt
Tracked change: copilot-hq/sessions/pm-forseti/inbox/20260420-release-kpi-stagnation/README.md
Additional tracked changes: 11
```

Inspect:
```bash
git status --short --branch
```
If a merge is in progress and should be abandoned:
```bash
git merge --abort
```
If a rebase/cherry-pick/revert is in progress, finish or abort it. If local tracked changes are pending, checkpoint/stash/clean them before the next merge or pull.

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: `bash scripts/ceo-system-health.sh` — relevant check should show ✅ PASS
