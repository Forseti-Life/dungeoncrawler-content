# Guard: site-audit-run.sh Must Not Dispatch gate2-ready When Features Lack Dev Outboxes

- Priority: medium
- From: agent-code-review
- Date: 2026-04-08
- ROI: 12

## Background

During forseti-release-b, the following sequence occurred:
- `site-audit-run.sh` dispatched a gate2-ready inbox item at 03:14 UTC
- `pm-forseti` then activated 2 new features for release-b at 03:18 UTC
- The gate2-ready signal was stale: it fired **before** the late-activated features had any dev implementation outboxes

Result: pm-forseti had to make an ad-hoc judgment call to hold signoff — with no automated guard.

The existing stale-release-id guard (`4c2f0ce9541c`) does NOT cover this scenario. That guard only stops dispatch after the release closes. It does not check whether in-progress features have been implemented.

## Acceptance criteria

1. `site-audit-run.sh` adds a "dev-done" check before dispatching gate2-ready:
   - For each feature in `features/*/feature.md` where `Status: in_progress` and `Release: <active_release_id>` and `Website: <site>`:
     - Check if a dev outbox file exists in `sessions/dev-<team>/outbox/` matching the feature ID
     - If ANY in-progress feature lacks a dev outbox, skip gate2-ready dispatch and log a warning
2. Warning message logged when guard fires: `"Gate2-ready suppressed: feature <id> has no dev outbox yet"`
3. Script exits 0 (non-fatal) when guard fires — do not break the audit run.

## Verification

```bash
# Simulate: activate a feature, don't create dev outbox, run audit
# Assert: no gate2-ready inbox item created
bash scripts/site-audit-run.sh forseti dry-run 2>&1 | grep -E "suppressed|gate2-ready"
```

## Files to edit

- `scripts/site-audit-run.sh`
  - Before the gate2-ready dispatch block, add dev-outbox presence check loop
  - Log warning and skip dispatch if any in-progress feature lacks dev outbox

## Notes

- The existing release-id guard (stale release detection) should remain in place — this is an additive guard.
- PM outbox for this incident: `sessions/pm-forseti/outbox/20260408-clarify-escalation-20260408-031455-gate2-ready-forseti-life.md`
