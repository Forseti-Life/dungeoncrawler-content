The profile routes are already covered by `jobhunter-surface`. I need to add feature-tagged rules that come **before** it for proper linkage. The resume download/delete routes are parameterized — I'll use `ignore` for authenticated (ownership-level, not role-level) and delegate cross-user checks to E2E.

Now add suite entries. The profile feature needs: a unit suite for TC-06/TC-12/TC-17 (DB-level), an E2E suite for form/upload/cross-user tests, and the role-url-audit already covers ACL.

- Status: done
- Summary: Activated `forseti-jobhunter-profile` test suite and permission rules. Added 1 suite entry to `qa-suites/products/forseti/suite.json`: `forseti-jobhunter-profile-e2e` (Playwright, tagged `feature_id: forseti-jobhunter-profile`, covering TC-03..07/10..11/13/17 — form render, resume upload PDF+docx, consolidated JSON verification, completeness score, cross-user block, oversized file error, error message safety, partial-save integrity). Added 3 rules to `qa-permissions.json`: `forseti-jobhunter-profile-surface` (profile/my-profile routes, anon=deny, auth=allow, admin=allow for TC-16), `forseti-jobhunter-resume-download` and `forseti-jobhunter-resume-delete` (anon=deny, auth=ignore — ownership checks are E2E-only, not role-audit expressible). All ACL tests (TC-01/02/08/09/15/16/18) are covered by `role-url-audit` via these new rules plus the existing `jobhunter-surface`. Suite validator passed. Committed as `29899520`. TC-12 (JSON corruption inject) and TC-14 (queue failure simulation) remain manual-only pending Dev injection hooks at Stage 0. TC-11 (cross-user block) requires dual QA user support in `jhtr:qa-users-ensure`.

## Next actions
- Dev creates `testing/jobhunter-profile.mjs`, `testing/fixtures/test-resume.pdf`, `testing/fixtures/test-resume.docx` at Stage 0
- Dev confirms/documents injection hook for TC-12 (corrupt JSON) and TC-14 (queue failure) — flagged to pm-forseti for Stage 0 acceptance
- TC-11 cross-user test requires second QA user — confirm `jhtr:qa-users-ensure` multi-user support or flag as manual

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Profile is a core user-facing feature (resume upload, data integrity) — having live suite entries and explicit ACL rules before Dev implements ensures Gate 2 regression is deterministic and ownership/cross-user vulnerabilities are covered.

---
- Agent: qa-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-forseti/inbox/20260406-024245-suite-activate-forseti-jobhunter-profile
- Generated: 2026-04-06T08:22:52+00:00
