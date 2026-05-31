# Acceptance Criteria (PM-owned)
# Feature: dc-cr-dice-system

## Gap analysis reference

Gap analysis performed against `dungeoncrawler_content/src/Service/NumberGenerationService.php`.

Coverage findings:
- `rollPathfinderDie(int $sides)` — all PF2E die types (d4/d6/d8/d10/d12/d20/d100) — **Full**
- `rollPercentile()` — d% support — **Full**
- `rollRange()` — arbitrary range — **Partial** (not PF2E-specific notation)
- NdX expression parser (e.g., `"4d6"`, `"1d20+5"`) — **None** (not present)
- Roll logging / audit trail (timestamp, character id, roll type) — **None**
- `POST /dice/roll` API endpoint — **None** (no route in routing.yml for this)
- Keep-highest/keep-lowest (ability score generation) — **None**

Feature type: **enhancement** (extend existing NumberGenerationService; build missing expression parser + API + logging)

## Happy Path
- [ ] `[EXTEND]` `rollPathfinderDie()` accepts all PF2E die types (d4, d6, d8, d10, d12, d20, d100) and returns an integer in `[1, sides]`.
- [ ] `[NEW]` A `rollExpression(string $expression)` method (or equivalent) accepts NdX notation (e.g., `"4d6"`, `"1d20+5"`, `"d%"`) and returns individual die results plus total.
- [ ] `[NEW]` `POST /dice/roll` API endpoint accepts `{ "expression": "NdX+M" }` and returns `{ "dice": [...], "modifier": M, "total": N }`.
- [ ] `[NEW]` Each roll is logged with timestamp, optional `character_id`, and `roll_type` context (e.g., `attack`, `skill`, `damage`, `save`, `initiative`).

## Edge Cases
- [ ] `[EXTEND]` Unsupported die type (e.g., d7) returns an explicit error (not a silent incorrect result).
- [ ] `[NEW]` Expression with N=0 or N<0 returns an error.
- [ ] `[NEW]` Expression with modifier `+0` is handled gracefully (same as no modifier).
- [ ] `[NEW]` Keep-highest/keep-lowest modifier (e.g., `"4d6kh3"` for ability score gen) returns the kept subset and total.
- [ ] `[NEW]` `d%` expression maps to two d10s (tens + ones) producing 1–100.

## Failure Modes
- [ ] `[TEST-ONLY]` Invalid expression string returns HTTP 400 with a human-readable message.
- [ ] `[NEW]` Invalid input is rejected with explicit feedback (expression parse error message included in response).

## Permissions / Access Control
- [ ] Anonymous user behavior: dice rolls are accessible to anonymous users in game context (game sessions are auth-gated at session level, not individual roll level — confirm with site auth policy).
- [ ] Authenticated user behavior: roll log records `character_id` when authenticated user makes a roll.
- [ ] Admin behavior: admins can query roll log for audit/debug (no special UI required in this release).

## Data Integrity
- [ ] Roll log entries are immutable (insert-only; no update/delete of historical rolls).
- [ ] Rollback path: roll log table can be safely dropped and recreated; no foreign key dependencies in this release.

## Knowledgebase check
- Related lessons/playbooks: none found for dice engine specifically; see `CombatCalculator.php` for existing degree-of-success patterns that consume roll results.

## Test path guidance (for QA)
| Requirement | Test path |
|---|---|
| `rollPathfinderDie` all types | `tests/src/Unit/Service/NumberGenerationServiceTest.php` |
| `rollExpression` NdX parser | `tests/src/Unit/Service/NumberGenerationServiceTest.php` (extend) |
| `POST /dice/roll` endpoint | `tests/src/Functional/DiceRollControllerTest.php` (new) |
| Roll logging | `tests/src/Unit/Service/NumberGenerationServiceTest.php` (extend) |
| Error handling | `tests/src/Unit/Service/NumberGenerationServiceTest.php` (extend) |
