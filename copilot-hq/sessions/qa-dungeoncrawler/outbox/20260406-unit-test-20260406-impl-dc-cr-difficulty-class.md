- Status: done
- Summary: Gate 2 for dc-cr-difficulty-class is APPROVE (updated from prior BLOCK). Product: dungeoncrawler / dungeoncrawler_content module / dc-cr-difficulty-class feature. Service-layer logic was verified correct in original pass: `determineDegreOfSuccess` returns correct degrees, nat-20/nat-1 bumps work, `getSimpleDC` returns PF2E Table 10-5 values, `getTaskDC` handles all 6 tiers. Original BLOCK was: `POST /rules/check` returned 403 for anonymous users — route had `_permission: 'access dungeoncrawler characters'` but AC requires anonymous access. Fix dispatched to Dev (single-line routing change + drush cr). Dev applied fix (commit `86fcd7445`) changing `_permission` → `_access: 'TRUE'`. QA re-verified: GET → 405, POST without CSRF → 400, anon POST with valid CSRF token → 200 with correct degree output. Checklist line 98 updated to APPROVE. Evidence in `sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-fix-from-qa-block-dungeoncrawler.md`.

## Next actions
- None. Feature is fully verified APPROVE. PM may include in release gate.

## Blockers
- None. Prior BLOCK resolved.

## Decision needed
- Originally: should `POST /rules/check` allow anonymous access (same pattern as `/dice/roll`) or require auth? **Decision: anonymous access is required per AC** — the DC calculation is a rules utility needed by unauthenticated clients. This matches the `/dice/roll` route pattern and was confirmed by the fix implementation.

## Recommendation
- Enforce `_access: 'TRUE'` with `_csrf_request_header_mode: TRUE` (not `_permission`) on stateless rules/calculation API endpoints. This is consistent with the org's pattern for dice/roll and rules/check. Tradeoff: opens endpoint to unauthenticated calls, but since it is read-only rules math (no data mutation) this is acceptable and by design.

## ROI estimate
- ROI: 20
- Rationale: DC system is consumed by every skill check, attack roll, and save; fix was a single routing line — high leverage, now unblocked for release.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260406-unit-test-20260406-impl-dc-cr-difficulty-class
- Updated: 2026-04-06T19:34:00+00:00
