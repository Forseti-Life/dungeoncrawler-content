All 4 functional tests PASS independently. Now update the checklist and write the outbox.

- Status: done
- Summary: Verified dev-infra commit `9b041f50` (GAP-RB-03): `pm-scope-activate.sh` now correctly updates stale Release: fields on feature reactivation rather than leaving prior-cycle IDs intact. All 4 functional unit tests PASS (stale-update, absent-insert, empty-release-id guard ×2). Operator audit clean (bash-n, lint 0, 5 suites). Site-audit skipped per infrastructure site instructions (no web surface). APPROVE outbox written and committed `03813077`.

## Next actions
- Inbox still has `20260406-unit-test-20260406-improvement-round-stale-signoff-grep-fix` pending (visible in checklist)

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 35
- Rationale: Closes the last gap in the release-scoping chain; without this fix, stale Release IDs on reactivated features cause silent zero-count in `_count_site_features_for_release`, which could suppress auto-close or miscount FEATURE_CAP.

---
- Agent: qa-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-infra/inbox/20260406-unit-test-20260406-scope-activate-release-field-update
- Generated: 2026-04-06T10:08:11+00:00
