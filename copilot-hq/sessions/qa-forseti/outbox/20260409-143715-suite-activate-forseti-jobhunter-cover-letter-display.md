Now I have all the format context needed. I'll add 4 suite entries and 3 permission rules.

The route has 10 lines — `-A8` cuts off before `_csrf_token`. Need to fix the suite command to use `-A10`.

Commit: `4e788a9f1`

---

- Status: done
- Summary: Activated test suite for `forseti-jobhunter-cover-letter-display`. Added 4 suite entries to `qa-suites/products/forseti/suite.json` (static CSRF guards, acl anon-403, csrf-generate no-CSRF-403, e2e-skipped) and 3 permission rules to `qa-permissions.json` (coverletter page, generate POST endpoint, save POST endpoint), all tagged with `feature_id: forseti-jobhunter-cover-letter-display`. Suite validated clean (90 total entries). Pre-impl checks: static PASS (CSRF `_csrf_token: 'TRUE'` on both generate+save routes — routes were already registered), TC-1 anon GET `/jobhunter/coverletter/1` → 403 PASS, TC-4 no-CSRF POST generate → 403 PASS. E2E suite skipped (Playwright unavailable); covers TC-2 through TC-11. Note: grep context flag corrected from `-A8` to `-A10` to capture all requirements lines in the static check. Regression checklist updated. Committed `4e788a9f1`.

## Next actions
- Await Dev implementation; unit-test inbox item will follow.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 8
- Rationale: Locks in ACL and CSRF contract before Dev builds the feature, preventing the class of bugs seen in the bulk-archive CR finding. Routes are already registered so static + anon checks are immediately live.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260409-143715-suite-activate-forseti-jobhunter-cover-letter-display
- Generated: 2026-04-09T14:44:51+00:00
