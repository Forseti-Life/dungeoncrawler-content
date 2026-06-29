Now I have the full picture. Let me add the suite entries and permission rules.

- Status: done
- Summary: Activated QA suite for `forseti-jobhunter-application-deadline-tracker`. Added 17 TCs to `qa-suites/products/forseti/suite.json` (185 total) and 3 permission rules to `org-chart/sites/forseti.life/qa-permissions.json` (84 total), all tagged `"feature_id": "forseti-jobhunter-application-deadline-tracker"`. Coverage: 8 curl/role-audit TCs (anon-403 for job page, deadlines route, and POST date save; auth-200 for job page and deadlines; CSRF-missing-403; cross-user ownership-403; non-integer-404), 8 Playwright TCs (date field presence, save happy path, blank→NULL, overdue-red indicator, amber-3day indicator, deadline sort order, empty state, invalid-date form error), 1 regression TC (date values absent from watchdog). Three PM-noted items require dev input before Playwright selectors can be finalized: AC-3 CSS class names for red/amber indicators, the 3-day boundary definition (inclusive of today?), and exact confirmation message wording. Suite validated OK. Commit: `251d2d081`.

## Next actions
- dev-forseti should document in implementation notes: (1) CSS class/element names for urgency indicators, (2) whether today counts as overdue or within-3-days, (3) exact confirmation message wording
- Run Gate 2 targeted verification once dev marks feature complete

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 45
- Rationale: Deadline tracking is a high-value job hunter feature; activating 17 TCs now ensures full Stage 4 regression coverage including ownership isolation and PII/watchdog checks. Early flagging of the 3 PM-noted items prevents a Gate 2 block loop.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260411-160846-suite-activate-forseti-jobhunter-application-deadline-tracker
- Generated: 2026-04-11T16:17:53+00:00
