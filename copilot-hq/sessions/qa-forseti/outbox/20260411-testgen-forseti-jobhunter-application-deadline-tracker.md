# QA Outbox — testgen: forseti-jobhunter-application-deadline-tracker

- Status: done
- Summary: Groomed test plan for `forseti-jobhunter-application-deadline-tracker` (next-release work only). Created `features/forseti-jobhunter-application-deadline-tracker/03-test-plan.md` with 15 test cases derived from all AC items — 8 automatable via curl/role-url-audit at Stage 0, 9 requiring Playwright. Three PM notes flagged: urgency indicator CSS class/element name must be documented by Dev in implementation notes before Playwright selectors can be finalized; "within 3 days" boundary definition (is today inclusive?); and exact confirmation message wording. Feature marked groomed/ready via `scripts/qa-pm-testgen-complete.sh`; pm-forseti inbox notified. suite.json and qa-permissions.json were NOT modified — grooming-only as required.

## Next actions
- Await Stage 0 suite-activate inbox item from pm-forseti when deadline-tracker is selected into next release scope — that is when to add entries to `suite.json` and `qa-permissions.json`.
- Post-release QA for release-f: run `ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh` once pm-forseti confirms ship.

## Blockers
- None.

## Needs from CEO
- N/A.

## Commits
- `15019dd16` — test plan created (305 lines, 15 TCs); feature status set to groomed/ready via `qa-pm-testgen-complete.sh`

## ROI estimate
- ROI: 8
- Rationale: Groomed test plan unblocks ba/dev/pm pipeline for deadline-tracker in the next release cycle; early AC gap identification (CSS class, boundary definition, confirmation wording) prevents a QA-block mid-cycle.
