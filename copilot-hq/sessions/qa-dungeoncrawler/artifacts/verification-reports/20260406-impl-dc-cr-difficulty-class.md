# Gate 2 Verification Report — dc-cr-difficulty-class

- Feature/Item: dc-cr-difficulty-class
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260406-impl-dc-cr-difficulty-class.md
- Dev commits: ebd5fad6
- Verified by: qa-dungeoncrawler
- Date: 2026-04-06
- Verdict: **BLOCK**

---

## Knowledgebase check
- AC references `CombatCalculator.php` p. 445 degree-of-success pattern — consistent with prior implementation.

---

## BLOCK: /rules/check endpoint requires auth (AC says anonymous)

### Defect
**TC-DC-AUTH-01** — `POST /rules/check` returns HTTP 403 for anonymous users.

**AC states**: "Anonymous user behavior: rules check endpoint is accessible to anonymous users in game context (same as dice rolls)."

**Actual**: Route `dungeoncrawler_content.api.rules_check` has `_permission: 'access dungeoncrawler characters'`, which requires an authenticated user with the `dc_playwright_player` role. Anonymous users receive HTTP 403.

**Contrast**: `/dice/roll` correctly uses `_access: 'TRUE'` for anonymous access.

**Fix required**: Change routing requirement from `_permission: 'access dungeoncrawler characters'` to `_access: 'TRUE'` (matching `/dice/roll` pattern), then run `drush cr`.

```yaml
# Current (WRONG):
requirements:
  _permission: 'access dungeoncrawler characters'
  _csrf_request_header_mode: TRUE

# Required (match dice/roll):
requirements:
  _access: 'TRUE'
  _csrf_request_header_mode: TRUE
```

**Severity**: Medium. The service-layer logic is correct; only the route permission guard is wrong. Single-line routing fix.

**Reproduction**:
```bash
curl -s -o /dev/null -w "%{http_code}" -X POST https://dungeoncrawler.forseti.life/rules/check \
  -H 'Content-Type: application/json' \
  -d '{"roll":25,"dc":15,"natural_twenty":false,"natural_one":false}'
# Returns: 403 (Expected: 200)
```

---

## Passing AC items (all service-layer logic correct)

### Happy Path

| AC item | Expected | Actual | Result |
|---|---|---|---|
| `determineDegreOfSuccess(25, 15, false, false)` → critical_success | beat DC by 10+ | `drush`: `critical_success` ✅ | ✅ PASS |
| `determineDegreOfSuccess(18, 15, false, false)` → success | beat DC | `drush`: `success` ✅ | ✅ PASS |
| `determineDegreOfSuccess(5, 15, false, false)` → critical_failure | miss DC by 10+ | `drush`: `critical_failure` ✅ | ✅ PASS |
| `determineDegreOfSuccess(10, 15, false, false)` → failure | miss by <10 | `drush`: `failure` ✅ | ✅ PASS |
| Nat-20 bump: `determineDegreOfSuccess(14, 15, true, false)` → success | failure→success | `drush`: `success` ✅ | ✅ PASS |
| Nat-1 bump: `determineDegreOfSuccess(18, 15, false, true)` → failure | success→failure | `drush`: `failure` ✅ | ✅ PASS |
| `getSimpleDC(1)=15`, `getSimpleDC(10)=27`, `getSimpleDC(20)=40` | PF2E Table 10-5 values | L1=15, L5=20, L10=27, L15=34, L20=40 ✅ | ✅ PASS |
| `getTaskDC("trivial")=10`, `getTaskDC("moderate")=20`, `getTaskDC("extreme")=30` | Named tier values | trivial=10, low=15, moderate=20, high=25, extreme=30, incredible=40 ✅ | ✅ PASS |
| `POST /rules/check` route registered | Route exists | `dungeoncrawler_content.api.rules_check` in routing.yml ✅ | ✅ PASS (registered) |
| `POST /rules/check` anonymous access | HTTP 200 | HTTP 403 (WRONG permission guard) | ❌ FAIL — **BLOCK** |

### Edge Cases

| AC item | Expected | Actual | Result |
|---|---|---|---|
| Nat-20 on critical_success clamped | stays critical_success | `drush determineDegreOfSuccess(25,15,true,false)` → `critical_success` ✅ | ✅ PASS |
| Nat-1 on critical_failure clamped | stays critical_failure | `drush determineDegreOfSuccess(5,15,false,true)` → `critical_failure` ✅ | ✅ PASS |
| `getSimpleDC(0)` → error | Structured error | Returns `['error' => 'Level must be a positive integer (1–20), got: 0.']` ✅ | ✅ PASS |
| `getSimpleDC(21)` → capped at L20 | L20 DC (40) | Returns 40 ✅ | ✅ PASS |
| `getTaskDC("unknown-tier")` → error | Structured error | Returns `['error' => 'Unknown difficulty tier: ...']` ✅ | ✅ PASS |
| `getTaskDC("EXTREME")` case-insensitive | 30 | `EXTREME=30` ✅ | ✅ PASS |

---

## Live spot-checks (drush php:eval)

```
determineDegreOfSuccess(25, 15, false, false) → critical_success ✅
determineDegreOfSuccess(18, 15, false, false) → success           ✅
determineDegreOfSuccess(5,  15, false, false) → critical_failure  ✅
determineDegreOfSuccess(10, 15, false, false) → failure           ✅
nat-20 bump (14 vs DC15): success                                 ✅
nat-1 bump  (18 vs DC15): failure                                 ✅
nat-20 clamp at crit_success: critical_success                    ✅
nat-1 clamp at crit_failure:  critical_failure                    ✅
getSimpleDC: L1=15, L5=20, L10=27, L15=34, L20=40                ✅
getTaskDC all 6 tiers: 10/15/20/25/30/40                          ✅
getSimpleDC(0): error array                                       ✅
getSimpleDC(21): 40 (capped)                                      ✅
getTaskDC("unknown"): error array                                 ✅
getTaskDC("EXTREME"): 30 (case-insensitive)                       ✅
POST /rules/check (anonymous): 403 ← FAIL                        ❌
```

---

## Site audit

- Run: 20260406-164327
- Findings: 0 failures, 0 violations, 0 missing assets ✅

---

## Required fix

**File**: `web/modules/custom/dungeoncrawler_content/dungeoncrawler_content.routing.yml`

Change line ~1732:
```
_permission: 'access dungeoncrawler characters'
```
to:
```
_access: 'TRUE'
```
Then run `drush cr` on production. No code logic changes needed.

---

## Verdict

**BLOCK** — 1 defect: `POST /rules/check` returns HTTP 403 for anonymous users. All service-layer logic (degree-of-success, Simple DC table, Task DC table, edge cases, clamp behavior, case-insensitivity, error returns) is correct and verified. Only the route permission guard needs to be corrected from `_permission: 'access dungeoncrawler characters'` to `_access: 'TRUE'`. This is a single-line routing fix.

After fix: re-verify with `curl -s -X POST https://dungeoncrawler.forseti.life/rules/check -H 'Content-Type: application/json' -d '{"roll":25,"dc":15}'` → expect HTTP 200.
