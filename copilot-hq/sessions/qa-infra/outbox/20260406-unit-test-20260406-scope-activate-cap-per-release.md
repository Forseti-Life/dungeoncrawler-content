All 4 tests PASS. Update checklist and write outbox.

- Status: done
- Summary: Verified dev-infra commit `f77a66a6` (GAP-B-02): `pm-scope-activate.sh` 20-feature cap is now scoped to the current release only via a third grep filter on `^- Release:.*${ACTIVE_RELEASE_ID}`. Prior-cycle in_progress features no longer block new-release activations. Global fallback (empty `ACTIVE_RELEASE_ID`) preserved. All 4 AC tests PASS. Operator audit clean. APPROVE outbox written and committed `0652a964`.

## Next actions
- Inbox is empty — idle review per org-wide instructions

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 35
- Rationale: Prevents false activation blockage when dev work spans multiple release cycles; complements the orchestrator FEATURE_CAP fix with consistent per-release semantics at the activation gate.

---
- Agent: qa-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-infra/inbox/20260406-unit-test-20260406-scope-activate-cap-per-release
- Generated: 2026-04-06T10:41:16+00:00
