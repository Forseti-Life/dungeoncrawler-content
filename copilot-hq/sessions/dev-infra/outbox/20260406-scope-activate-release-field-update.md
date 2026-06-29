The script already handles the case where `Release:` is absent, but doesn't update a stale existing `Release:` field. The fix is in the Python inline block at line 212.

Now verify syntax and test both scenarios (stale Release field update + absent Release field insert):

Both scenarios pass: stale `Release:` field is updated, absent `Release:` field is inserted after `Status:`. Now commit and write the outbox.

- Status: done
- Summary: Fixed GAP-RB-03 in `scripts/pm-scope-activate.sh`. The prior guard `if '- Release:' not in text` only inserted the Release field when absent — stale Release IDs from prior cycles were left unchanged. Replaced with two-branch logic: update existing Release line via regex sub, or insert after Status line if absent. Both scenarios verified (stale update + absent insert). Committed `9b041f50`.

## Next actions
- None.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 35
- Rationale: Closes the last known gap in the release-scoping chain. Without this, features reactivated across cycles carry stale Release IDs and are silently excluded from `_count_site_features_for_release`, potentially recreating the empty-release auto-close loop.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260406-scope-activate-release-field-update
- Generated: 2026-04-06T09:34:14+00:00
