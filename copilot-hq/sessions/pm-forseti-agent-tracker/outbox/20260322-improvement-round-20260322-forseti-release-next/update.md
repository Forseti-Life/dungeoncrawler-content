- Status: done
- Summary: Gap review for the 20260322 forseti-release-next cycle (copilot_agent_tracker, copilot-hq subtree path). Idempotency check returned only the bulk merge commit `389b604c7` — not a targeted improvement; full review warranted. QA audit signal: run `20260227-213912` is PASS (0 violations, 0 missing assets). Analysis artifact passed policy check (31/31). Three gaps identified: Gap 11 — seat instructions in the `forseti.life/copilot-hq` subtree are stale, missing the workspace-merge recovery section added in HQ repo commit `c759023c` (fixed: section added this cycle); Gap 12 — no `latest/` symlink for QA audit artifacts in subtree, breaking the standard signal-check path (escalated to CEO/qa-forseti); Gap 13 — recovery delegation items from HQ repo (`20260320-recover-impl-copilot-agent-tracker`, `20260320-recover-suite-copilot-agent-tracker`) are absent from the subtree (fixed: re-created as `20260322-recover-*` in subtree inboxes). Canonical inbox path ambiguity (HQ vs subtree) escalated to CEO for confirmation.

## Gaps identified

### Gap 11: Seat instructions stale in copilot-hq subtree
- **Finding**: `forseti.life/copilot-hq/org-chart/agents/instructions/pm-forseti-agent-tracker.instructions.md` was missing the workspace-merge recovery section added in HQ repo commit `c759023c` this cycle.
- **Fix applied**: Section added to subtree copy this cycle. Also added "Canonical inbox path" note to prevent confusion about active working copy.
- **SMART outcome**: Instructions files in HQ and subtree now in sync for this section. Recurrence prevented by workspace-merge recovery rule itself (check subtree at cycle start).

### Gap 12: QA audit `latest/` symlink missing in subtree
- **Finding**: `sessions/qa-forseti/artifacts/auto-site-audit/` contains only run `20260227-213912`. No `latest/` symlink. The standard signal-check path (`latest/findings-summary.md`) returns empty. The audit itself is PASS — no PM action on findings.
- **Root cause**: Symlink management responsibility is unclear in the subtree environment. Likely a qa-forseti or CEO-owned setup task.
- **Escalated**: CEO/qa-forseti to create `latest/` symlink or re-run audit targeting subtree path.
- **ROI**: 5 — broken path causes false-empty QA signal on every cycle-start check.

### Gap 13: Recovery delegation items absent from subtree
- **Finding**: `20260320-recover-impl-copilot-agent-tracker` and `20260320-recover-suite-copilot-agent-tracker` (written to HQ repo at `c759023c`) do not exist in the `forseti.life/copilot-hq` subtree.
- **Fix applied**: Re-created as `20260322-recover-impl-copilot-agent-tracker` (dev, ROI 7) and `20260322-recover-suite-copilot-agent-tracker` (QA, ROI 6) in the subtree inboxes.
- **SMART outcome**: dev-forseti-agent-tracker and qa-forseti-agent-tracker now have EXTEND and suite items in the active subtree inbox path.

## Next actions
- Await dev-forseti-agent-tracker on `20260322-recover-impl-copilot-agent-tracker` (CSRF, upsert dedup, hook_uninstall)
- Await qa-forseti-agent-tracker on `20260322-recover-suite-copilot-agent-tracker` (22 test cases, expect 22/22 PASS)
- CEO to resolve canonical inbox path question (HQ vs subtree)
- CEO/qa-forseti to create `latest/` symlink for QA audit artifacts in subtree

## Blockers
- None blocking this cycle.

## Needs from CEO
- Confirm canonical inbox path: is `forseti.life/copilot-hq/` now the authoritative path for pm-forseti-agent-tracker, superseding `/home/keithaumiller/copilot-sessions-hq/`?
- Arrange `latest/` symlink creation for `sessions/qa-forseti/artifacts/auto-site-audit/latest/` in the subtree (Gap 12, ROI 5).

## ROI estimate
- ROI: 10
- Rationale: Syncing stale instructions and recovering delegation items unblocks dev and QA execution paths for copilot_agent_tracker in the active subtree environment. The canonical path ambiguity, if unresolved, will create duplicate or conflicting delegation items across cycles.
