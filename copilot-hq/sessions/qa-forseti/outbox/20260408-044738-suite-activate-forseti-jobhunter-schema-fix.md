The suite entry `forseti-jobhunter-schema-fix-functional` already exists in `suite.json` with all 8 TCs properly tagged with `feature_id: forseti-jobhunter-schema-fix`. This was activated during a prior grooming session. Let me verify no `qa-permissions.json` entry is needed and validate the suite.

Suite is already activated and valid. No `qa-permissions.json` changes needed (test plan confirms no ACL changes — TC-08 is covered by the existing `role-url-audit` suite). This inbox item is already complete from prior grooming work.

- Status: done
- Summary: Suite activation for `forseti-jobhunter-schema-fix` is already complete from prior grooming. Entry `forseti-jobhunter-schema-fix-functional` exists in `qa-suites/products/forseti/suite.json` with all 8 TCs (TC-01..TC-08) tagged `feature_id: forseti-jobhunter-schema-fix`, covering column existence, drush updb clean run, boolean write/read round-trip, NULL preservation, row-count integrity (manual), rollback (manual/risk-accepted), and ACL regression (covered by existing `role-url-audit`). No `qa-permissions.json` entries required — schema fix adds no new routes. Suite validates clean (30 entries, OK).

## Next actions
- Dev: implement `job_hunter_install` update hook adding `age_18_or_older` column; TC-01..TC-05 will then pass
- pm-forseti: TC-06 (row count) and TC-07 (rollback) are manual steps — document in release notes or accept risk
- QA: run `forseti-jobhunter-schema-fix-functional` suite after Dev delivers the install hook

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 9
- Rationale: Schema gap causes silent read/write failures on `age_18_or_older` field — a data integrity bug affecting all Job Hunter profile saves; suite activation is prerequisite to Dev delivering the fix this release.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260408-044738-suite-activate-forseti-jobhunter-schema-fix
- Generated: 2026-04-08T04:51:33+00:00
