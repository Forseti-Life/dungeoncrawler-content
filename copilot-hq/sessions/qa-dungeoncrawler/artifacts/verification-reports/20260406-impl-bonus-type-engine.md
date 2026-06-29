# Verification Report: impl-bonus-type-engine

- Date: 2026-04-06
- Agent: qa-dungeoncrawler
- Inbox item: 20260406-unit-test-20260406-impl-bonus-type-engine
- Feature: dc-cr-bonus-type-engine (BonusResolver + Calculator/CombatCalculator integration)
- Verdict: APPROVE (all ACs PASS)

## KB reference
None found directly; PF2e stacking rules documented in Core Rulebook pp. 444–446 (referenced in BonusResolver.php source comment).

## What was implemented

Per dev outbox (`sessions/dev-dungeoncrawler/outbox/20260406-impl-bonus-type-engine.md`):
- New `BonusResolver` service (`src/Service/BonusResolver.php`) with `resolve()` and `resolvePenalties()` static methods
- Typed bonus stacking: circumstance, item, status → highest of same type wins; different types always stack; untyped always stacks
- Integration into `Calculator.php` (calculateInitiative, rollSkillCheck, rollSavingThrow, calculateAttackBonus) and `CombatCalculator.php` (calculateAttackBonus, calculateSpellSaveDC)
- Backward compatible: plain ints treated as untyped

## Verification results

All tests run via `drush ev` against production.

| TC | Description | Expected | Got | Result |
|---|---|---|---|---|
| AC-1 | Two circumstance bonuses → highest only | 4 | 4 | PASS |
| AC-2 | Circumstance + status → both stack | 3 | 3 | PASS |
| AC-3 | Two untyped bonuses → sum | 3 | 3 | PASS |
| AC-4 | Plain ints → untyped sum | 8 | 8 | PASS |
| AC-5 | Two status penalties → worst (most negative) | -3 | -3 | PASS |
| AC-6 | Backward compat: single plain int | 7 | 7 | PASS |
| AC-7 | Circ + item + status (all different) → sum | 6 | 6 | PASS |

## Service integration check

- `Calculator.php`: `BonusResolver::resolve()` called at lines 62, 140, 411, 506; `resolvePenalties()` at line 141, 507 ✓
- `CombatCalculator.php`: `BonusResolver::resolve()` called at lines 125, 148 ✓
- All Calculator/CombatCalculator methods still load cleanly: `calculateInitiative`, `rollSkillCheck`, `rollSavingThrow`, `calculateAttackBonus`, `calculateSpellSaveDC` ✓

## Dev commits

- `680f58ec6` — BonusResolver.php (new file), CharacterManager Alchemist advancement
- `b17eb7430` — Calculator/CombatCalculator integration + update_10035 bundle fix

## Checklist

Appended to `org-chart/sites/dungeoncrawler/qa-regression-checklist.md`.
