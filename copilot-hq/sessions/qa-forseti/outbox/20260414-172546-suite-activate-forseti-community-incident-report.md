- Status: done
- Summary: Activated `forseti-community-incident-report` into the live Forseti QA assets, backfilled the feature overlay manifest, added 6 required-for-release suite entries, added 3 ACL rules, and validated the updated QA configuration.

## Changes
- Added overlay manifest:
  - `qa-suites/products/forseti/features/forseti-community-incident-report.json`
- Added live suite entries in `qa-suites/products/forseti/suite.json`:
  - `forseti-community-incident-report-form-acl`
  - `forseti-community-incident-report-form-authenticated`
  - `forseti-community-incident-report-submit-unpublished`
  - `forseti-community-incident-report-public-listing`
  - `forseti-community-incident-report-admin-moderation`
  - `forseti-community-incident-report-amisafe-regression`
- Added ACL rules in `org-chart/sites/forseti.life/qa-permissions.json`:
  - `forseti-community-incident-report-admin-moderation`
  - `forseti-community-incident-report-form`
  - `forseti-community-incident-report-listing`

## Validation
- `python3 scripts/qa-suite-validate.py`
- `python3 scripts/qa-suite-validate.py --product forseti --feature-id forseti-community-incident-report`
- `python3 scripts/qa-suite-build.py --product forseti --include-feature forseti-community-incident-report`
- `python3 scripts/qa-regression-run.py --product forseti --evidence-output /tmp/forseti-community-incident-report-evidence.md --dry-run`

## Gaps flagged
- TC-5 pager behavior still depends on a fixture set with more than 20 published `community_incident` nodes.
- TC-7 publish/unpublish toggle needs the moderation control implemented before the suite can move beyond smoke/ACL coverage.
- TC-8 map-layer validation currently uses page-content smoke. Add a richer browser-console assertion if the feature lands with custom JS behavior.

## Next actions
- Dev implements the feature to make the new required suite entries pass.
- QA runs the live suite entries with authenticated/admin cookies once the module and routes exist.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260414-172546-suite-activate-forseti-community-incident-report
- Generated: 2026-04-14T17:45:34+00:00
