# Acceptance Criteria (PM-owned)
# Feature: dc-cr-difficulty-class

## Gap analysis reference

Gap analysis performed against `dungeoncrawler_content/src/Service/CombatCalculator.php`.

Coverage findings:
- `calculateDegreeOfSuccess(int $roll, int $dc, bool $naturalTwenty, bool $naturalOne)` — degree-of-success enum — **Partial** (method referenced in docblock; impl may be stubbed with TODO)
- `calculateMultipleAttackPenalty()` — **Full** (implemented correctly per PF2E spec)
- Simple DC table by level (1–20) — **None** (no lookup table found)
- Task DC guidelines (Trivial/Low/Moderate/High/Extreme/Incredible) — **None**
- Fixed DCs for specific tasks (e.g., DC 15 for Recall Knowledge on common topics) — **None**
- Non-combat skill check resolution (input roll+DC → degree) — **Partial** (CombatCalculator exists but scoped to combat)

Feature type: **enhancement** (complete stubbed degree-of-success; add Simple DC table and task DC guidelines as data)

## Happy Path
- [ ] `[EXTEND]` `determineDegreOfSuccess(int $rollTotal, int $dc, bool $naturalTwenty = false, bool $naturalOne = false): string` returns one of: `critical_success`, `success`, `failure`, `critical_failure`.
- [ ] `[EXTEND]` Degree logic: roll ≥ DC+10 = `critical_success`; roll ≥ DC = `success`; roll ≤ DC−10 = `critical_failure`; otherwise `failure`. Natural 20 bumps one degree up; natural 1 bumps one degree down.
- [ ] `[NEW]` A `getSimpleDC(int $level): int` method returns the Simple DC for a given level (1–20) per PF2E Core Rulebook table.
- [ ] `[NEW]` A `getTaskDC(string $difficulty): int` method returns a DC for named difficulty tiers: `trivial`, `low`, `moderate`, `high`, `extreme`, `incredible`.
- [ ] `[NEW]` A `POST /rules/check` API endpoint accepts `{ "roll": N, "dc": N, "natural_twenty": bool, "natural_one": bool }` and returns `{ "degree": "..." }`.

## Edge Cases
- [ ] `[EXTEND]` Natural 20 on a result already at `critical_success` stays at `critical_success` (no bump beyond max).
- [ ] `[EXTEND]` Natural 1 on a result already at `critical_failure` stays at `critical_failure` (no bump below min).
- [ ] `[NEW]` `getSimpleDC` for level 0 or negative returns an error; for levels > 20, returns the level-20 DC (capped).
- [ ] `[NEW]` Unknown `difficulty` string to `getTaskDC` returns an explicit error.

## Failure Modes
- [ ] `[TEST-ONLY]` Error messages are clear and actionable.
- [ ] `[NEW]` Invalid input (non-integer roll or DC) is rejected with explicit feedback.

## Permissions / Access Control
- [ ] Anonymous user behavior: rules check endpoint is accessible to anonymous users in game context (same as dice rolls).
- [ ] Authenticated user behavior: no additional behavior.
- [ ] Admin behavior: no special admin surface required.

## Data Integrity
- [ ] Simple DC table and task DC data are hardcoded constants (no DB storage required).
- [ ] Rollback path: no DB migrations; this is a pure logic/data change.

## Knowledgebase check
- Related lessons/playbooks: none found; `CombatCalculator.php` already references PF2E Core Rulebook p. 445 for degree-of-success rules — follow the same pattern.

## Test path guidance (for QA)
| Requirement | Test path |
|---|---|
| `determineDegreOfSuccess` all combinations | `tests/src/Unit/Service/CombatCalculatorTest.php` |
| Simple DC table | `tests/src/Unit/Service/CombatCalculatorTest.php` (extend) |
| Task DC guidelines | `tests/src/Unit/Service/CombatCalculatorTest.php` (extend) |
| `POST /rules/check` endpoint | `tests/src/Functional/RulesCheckControllerTest.php` (new) |
