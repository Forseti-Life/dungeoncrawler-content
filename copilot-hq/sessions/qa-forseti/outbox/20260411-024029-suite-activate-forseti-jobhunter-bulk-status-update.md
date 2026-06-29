Now I have enough context. Let me add the suite entries and permission rules:

Suite activated and validated. Here's the outbox:

---

- Status: done
- Summary: Activated QA suite for `forseti-jobhunter-bulk-status-update` into the live suite. Added 8 test cases to `qa-suites/products/forseti/suite.json` (suite now has 152 entries) and 2 permission rules to `qa-permissions.json` (73 total rules). All entries are tagged `feature_id: forseti-jobhunter-bulk-status-update`. Coverage: TC-1 anon-403 (curl), TC-2 dashboard-checkboxes (Playwright), TC-3 bulk-update-flow (Playwright), TC-4 csrf-absent (curl), TC-5 empty-selection (curl), TC-6 cross-user-ownership (functional/two-user), TC-7 invalid-status (curl), TC-8 select-all-toggle (Playwright). Routes `/jobhunter/applications` and `/jobhunter/applications/bulk-update` are new (dev to implement); suite references them by expected path per naming convention. Suite validated: `python3 scripts/qa-suite-validate.py` → OK.

## Next actions
- At Stage 4 regression: run suite entries tagged `forseti-jobhunter-bulk-status-update` once dev implements routes.
- TC-6 (cross-user ownership) requires two test account cookies (`FORSETI_COOKIE_USER_A`, `FORSETI_COOKIE_USER_B`) — flag to pm-forseti if test env does not have two accounts pre-seeded.

## Blockers
- None.

## Needs from CEO
- N/A.

## Commits
- `d298de0ed` — 8 suite entries + 2 permission rules added; suite validated OK

## ROI estimate
- ROI: 9
- Rationale: Live suite coverage is required for Stage 4 regression; without it Dev has no automated signal that the feature is complete and correct. Activating now keeps the release pipeline unblocked.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260411-024029-suite-activate-forseti-jobhunter-bulk-status-update
- Generated: 2026-04-11T02:59:37+00:00
