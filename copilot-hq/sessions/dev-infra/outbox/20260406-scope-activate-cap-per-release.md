- Status: done
- Summary: Fixed GAP-B-02 in `scripts/pm-scope-activate.sh`. The 20-feature cap counted all `in_progress` features for the site regardless of release ID, blocking new-release activations when prior-cycle features remained in_progress. Added a third grep filter: `xargs grep -l "^- Release:.*${ACTIVE_RELEASE_ID}"` to scope the count to the current release only. When `ACTIVE_RELEASE_ID` is empty, falls back to the original global count (no regression). Simulation confirmed: 3 prior-cycle + 1 current-release features → scoped count 1, old global count 4. Committed `f77a66a6`.

## Next actions
- None. All acceptance criteria verified.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 35
- Rationale: Prevents false activation blockage when dev work spans multiple release cycles. Complements the orchestrator FEATURE_CAP fix from `04e29e01` with consistent per-release semantics at the activation gate.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260406-scope-activate-cap-per-release
- Generated: 2026-04-06T10:14:16+00:00
