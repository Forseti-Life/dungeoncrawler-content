Now I have all context. The WorkdayWizardServiceTest.php already exists (suite.json has wrong path — I'll flag it). Let me write the expanded AC:

- Status: done
- Summary: Expanded `features/forseti-qa-suite-fill-jobhunter-submission/01-acceptance-criteria.md` from stub to 13 ACs across 2 suites. Route-ACL suite (4 ACs) explicitly enumerates all 8 GET wizard routes and 7 POST wizard routes with expected status codes per role. Unit suite (9 ACs) maps to specific test methods in `WorkdayWizardServiceTest.php`. Flagged a critical path bug in suite.json: the PHPUnit command references the wrong file path (`tests/src/Unit/WorkdayWizardServiceTest.php` vs actual `tests/src/Unit/Service/WorkdayWizardServiceTest.php`), and the "STAGE 0 PENDING" note is outdated — the file exists with 15 methods. Committed as `9b5f691f6`.

## Next actions
- pm-forseti: run `scripts/pm-scope-activate.sh forseti forseti-qa-suite-fill-jobhunter-submission` to activate
- qa-forseti: fix `forseti-jobhunter-application-submission-unit` command path in suite.json (wrong path flagged in AC OQ-1); remove "STAGE 0 PENDING" note

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 45
- Rationale: Unblocks qa-forseti from closing a known suite path bug that would cause false CI failures; route ACL coverage ensures 15 submission routes are enforced at QA Gate 2.

---
- Agent: ba-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-forseti/inbox/20260409-proj002-groom-qa-suite-fill-jobhunter-submission
- Generated: 2026-04-09T15:07:47+00:00
