# Gate 2 Verification Report — dc-cr-dice-system

- Feature/Item: dc-cr-dice-system
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260406-impl-dc-cr-dice-system.md
- Dev commits: beaebe9c, ed891ed6
- Verified by: qa-dungeoncrawler
- Date: 2026-04-06
- Verdict: **APPROVE**

---

## Knowledgebase check
- Reviewed `CombatCalculator.php` patterns for existing roll consumption (prior KB reference in AC). No new KB entries needed.

---

## Acceptance criteria verification

### Happy Path

| AC item | Expected | Actual | Result |
|---|---|---|---|
| `rollPathfinderDie()` all PF2E die types (d4/d6/d8/d10/d12/d20/d100) | Integer in [1, sides] | `drush php:eval` all 7 types: PASS | ✅ PASS |
| `rollExpression()` NdX notation (e.g. `2d6`, `1d20+5`, `d%`) | dice[], modifier, total, error=null | `drush php:eval`: 2d6 dice_count=2 total_in_range=YES; 1d20+5 modifier=5; d% total=32 in 1-100 | ✅ PASS |
| `POST /dice/roll` endpoint accepts `{"expression":"NdX+M"}` | `{success:true, expression, dice, kept, modifier, total}` | `curl POST /dice/roll {"expression":"2d6+3"}` → `{"success":true,"expression":"2d6+3","dice":[6,6],"kept":[6,6],"modifier":3,"total":15}` | ✅ PASS |
| Roll logged with timestamp, optional `character_id`, `roll_type` | Insert-only to `dc_roll_log` | Pre-count < post-count after `rollExpression("1d6")` confirmed | ✅ PASS |

### Edge Cases

| AC item | Expected | Actual | Result |
|---|---|---|---|
| Unsupported die type (d7) via `rollPathfinderDie` | Explicit `InvalidArgumentException` | `d7 exception: Unsupported Pathfinder die: d7` | ✅ PASS |
| Expression N=0 (`0d6`) | Error returned | `0d6 error=YES` | ✅ PASS |
| Modifier `+0` handled gracefully | `modifier=0`, no error | `1d20+0 modifier=0 error=null` | ✅ PASS |
| Keep-highest `4d6kh3` | `dice`=4 results, `kept`=3 highest | `all_dice=4 kept=3 error=null` | ✅ PASS |
| Keep-lowest `4d6kl1` | `kept`=1 lowest | `kept=1 error=null` | ✅ PASS |
| `d%` maps to 1–100 | `total` in [1, 100], `kept`=[total] | `d% total=32 in_range=YES` | ✅ PASS |

**Note**: Dev outbox states the response key is `kept` (not `kept_dice`) — confirmed in source and live response.

### Failure Modes

| AC item | Expected | Actual | Result |
|---|---|---|---|
| Invalid expression (`not-a-dice`) → HTTP 400 + human-readable error | `{success:false, error:"..."}`, status 400 | `drush`: `invalid expr error=YES`; `curl POST /dice/roll {"expression":"badexpr"}` → HTTP 400 | ✅ PASS |
| Missing `expression` field → HTTP 400 | `{success:false, error:"Missing required field: expression."}` | `curl`: `{"success":false,"error":"Missing required field: expression."}` | ✅ PASS |
| Keep count out of range (`2d6kh5`) | Error key in result | `kh_out_of_range error=YES: Keep count 5 is out of range for 2 dice.` | ✅ PASS |

### Permissions / Access Control

| AC item | Expected | Actual | Result |
|---|---|---|---|
| Anonymous user can POST `/dice/roll` | HTTP 200 (accessible anonymously) | Routing: `_access: 'TRUE'`; live curl without auth → 200 | ✅ PASS |
| Roll log records `uid` for authenticated users | `uid` field in insert | `logRoll()` sets `uid` from `currentUser->id()` | ✅ PASS |

### Data Integrity

| AC item | Expected | Actual | Result |
|---|---|---|---|
| `dc_roll_log` table exists | Schema present | `schema->tableExists('dc_roll_log')` → YES | ✅ PASS |
| Roll log is insert-only | No UPDATE/DELETE paths | `logRoll()` only calls `->insert('dc_roll_log')` | ✅ PASS |

---

## Live spot-checks (drush php:eval)

```
rollPathfinderDie(d4/d6/d8/d10/d12/d20/d100): all PASS ✅
rollExpression("2d6"):     dice=2, total∈[2,12]  ✅
rollExpression("1d20+5"):  modifier=5, total≥6    ✅
rollExpression("d%"):      total=32 ∈ [1,100]     ✅
rollExpression("4d6kh3"):  all_dice=4, kept=3      ✅
rollExpression("4d6kl1"):  kept=1                  ✅
rollExpression("not-a-dice"): error key present    ✅
rollExpression("0d6"):     error key present        ✅
rollExpression("1d20+0"):  modifier=0, no error    ✅
rollPathfinderDie(7):      InvalidArgumentException ✅
dc_roll_log insert after rollExpression: confirmed  ✅
```

## Live endpoint spot-checks (curl)

```
POST /dice/roll {"expression":"2d6+3"}:  200 {success:true, total:15}  ✅
POST /dice/roll {"expression":"badexpr"}: 400 {success:false}           ✅
POST /dice/roll {} (no expression):      400 {success:false}            ✅
```

---

## Suite coverage note

`suite.json` entry `dc-cr-dice-system-phpunit` targets `NumberGenerationServiceTest` (TC-DS-01 through TC-DS-17). Suite manifest exists; advisory: physical test files (`NumberGenerationServiceTest.php`, `DiceRollControllerTest.php`) should be validated in the next test-authoring cycle. Response key for keep results is `kept` — test assertions must use this exact key (not `kept_dice`).

---

## Verdict

**APPROVE** — All AC items verified. `rollExpression` handles all required formats (NdX, NdX+M, d%, keep-highest/lowest), returns structured errors on invalid input, `dc_roll_log` table exists with insert-only logging confirmed. `POST /dice/roll` returns correct JSON structure and HTTP 400 on bad input. Anonymous access confirmed. Site audit 20260406-163706: 0 failures, 0 violations.

No new Dev items identified for dice-system. PM may proceed on this feature.

---

## Evidence

- `NumberGenerationService.php` — `rollExpression()`, `rollPathfinderDie()`, `logRoll()` all present ✅
- `DiceRollController.php::roll()` — POST /dice/roll implementation ✅
- Routing: `dungeoncrawler_content.api.dice_roll` → path `/dice/roll`, methods [POST], `_access: TRUE` ✅
- `dc_roll_log` table: `schema->tableExists` = YES ✅
- `drush php:eval` spot-checks: all scenarios correct ✅
- Audit: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-163706/findings-summary.md` — 0 failures ✅
