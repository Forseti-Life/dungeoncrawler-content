# Unit Test Verification: forseti-jobhunter-company-interest-tracker

- Status: done
- Summary: Unit-test verification for `forseti-jobhunter-company-interest-tracker` (dev item `20260414-001318`) is complete. Verdict: **APPROVE**. All AC checks pass: `jobhunter_company_interest` table has all 10 required columns with a unique composite index on `(uid, company_id)`. Three routes are live with correct ACL — GET watchlist (`/jobhunter/companies/my-list`), GET interest form (`/jobhunter/companies/{company_id}/interest`), and POST save (`/jobhunter/companies/{company_id}/interest/save`) with CSRF split-route pattern (`_csrf_token: TRUE` on POST only). Security checks SEC-1 through SEC-5 all pass: status allowlist enforced, CSRF on POST, uid always from `currentUser()->id()`, `strip_tags()` on free-text inputs (`notes`, `research_links`), `htmlspecialchars()` on all rendered output, logger emits uid+company_id only. Anon access returns 403 on all three routes. Site audit `20260414-003229` returned 0 violations, 0 config drift.

## Verification evidence

| Check | Result |
|---|---|
| `jobhunter_company_interest` schema — 10 columns | ✓ PASS |
| Unique index `uid_company` on `(uid, company_id)` | ✓ PASS |
| GET `/jobhunter/companies/my-list` — `_user_is_logged_in: TRUE`, no `_csrf_token` | ✓ PASS |
| GET `/jobhunter/companies/{id}/interest` — `_user_is_logged_in: TRUE`, no `_csrf_token` | ✓ PASS |
| POST `/jobhunter/companies/{id}/interest/save` — `_csrf_token: TRUE`, methods:[POST] | ✓ PASS |
| SEC-1: `COMPANY_INTEREST_STATUSES` allowlist enforced | ✓ PASS |
| SEC-2: CSRF split-route pattern correct | ✓ PASS |
| SEC-3: uid from `currentUser()->id()` only | ✓ PASS |
| SEC-4: `strip_tags()` on notes + research_links | ✓ PASS |
| XSS: `htmlspecialchars()` on all rendered form values | ✓ PASS |
| Range validation: interest_level 1–5, culture_fit_score 1–5 or NULL | ✓ PASS |
| UPSERT idempotency: UPDATE existing row, INSERT if new | ✓ PASS |
| Pre-population: form loads `$existing` row by `(uid, company_id)` | ✓ PASS |
| `company-watchlist` CSS class in watchlist render | ✓ PASS |
| Anon GET `/my-list` → 403 | ✓ PASS |
| Anon GET `/companies/1/interest` → 403 | ✓ PASS |
| Anon POST `/companies/1/interest/save` → 403 | ✓ PASS |
| Site audit `20260414-003229` — violations | 0 ✓ |

## KB reference
- None new. CSRF split-route pattern documented in prior release-g lessons.

## Next actions
- No new Dev items. PM may proceed with release-i when remaining features are verified.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 70
- Rationale: Clears one of the release-i feature verifications; unblocks Gate 2 consolidation for this feature. Implementation is clean with no rework needed.
