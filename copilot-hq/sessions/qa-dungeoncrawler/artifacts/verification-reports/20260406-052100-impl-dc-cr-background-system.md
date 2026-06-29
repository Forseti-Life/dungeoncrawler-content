# Verification Report: 20260406-052100-impl-dc-cr-background-system

- Date: 2026-04-06
- QA seat: qa-dungeoncrawler
- Feature/Item: 20260406-052100-impl-dc-cr-background-system
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260406-052100-impl-dc-cr-background-system.md
- Dev commits: `ebf67c518` (code), `8252852ea` (impl notes)
- Result: **APPROVE**

## Summary

This item is a targeted re-verification of 4 specific gaps from the prior background-system implementation (`20260405-impl-dc-cr-background-system`, previously APPROVE with advisory). Dev fixed all 4 gaps in this pass. All 4 are confirmed resolved. The outstanding API-path advisory remains non-blocking and is unchanged.

## Prior state (from 20260405 QA cycle)

Gaps that were left open:
1. `BACKGROUNDS` constant missing `fixed_boost` key → ability model was 2 free boosts instead of 1 fixed + 1 free
2. 4 required backgrounds absent (Acrobat, Animal Whisperer, Artisan, Barkeep)
3. Wrong validation error messages
4. Background content type lacked custom Drupal fields (`field_bg_fixed_boost` etc.)

## Gap verification (4/4 resolved)

### Gap 1: BACKGROUNDS constant has fixed_boost + correct model
- All 13 entries in `CharacterManager::BACKGROUNDS` have `fixed_boost`, `skill`, `lore`, `feat`
- Sample: Acolyte → `fixed_boost=wis`, `skill=Religion`, `lore=Scribing Lore`, `feat=Student of the Canon` ✓
- `AbilityScoreTracker::applyBackgroundBoosts()` implements fixed+free model (line 285: `isset($bg_data['fixed_boost'])`) ✓
- **PASS**

### Gap 2: 4 new backgrounds seeded
- `acrobat` → OK (in BACKGROUNDS constant + node seeded, fixed_boost=dex)
- `animal_whisperer` → OK (fixed_boost=wis)
- `artisan` → OK (fixed_boost=str)
- `barkeep` → OK (fixed_boost=cha)
- Total background nodes in prod DB: 13 ✓
- **PASS**

### Gap 3: Validation error messages
- `CharacterCreationStepController::saveStep` (line 717): `'Background is required.'` ✓
- Line 733: `'Cannot apply two boosts to the same ability score from a single background.'` ✓
- **PASS**

### Gap 4: Background content type fields
- `field_bg_fixed_boost`: EXISTS ✓
- `field_bg_skill_training`: EXISTS ✓
- `field_bg_lore_skill`: EXISTS ✓
- `field_bg_skill_feat`: EXISTS ✓
- `field_bg_free_boost`: MISSING — correct/intentional; free boost is player-selected (character creation data), not a background node attribute ✓
- **PASS**

## Original AC regression check

| AC | Description | Result |
|---|---|---|
| AC1 | `background` content type with required fields | PASS — 5 custom fields present |
| AC2 | ≥5 backgrounds seeded | PASS — 13 seeded |
| AC3 | Step 3 stores background + boosts | PASS — form path (line 1595–1597) |
| AC4 | Fixed + free ability boosts applied | PASS — `applyBackgroundBoosts()` fixed+free model |
| AC5 | Skill training granted | PASS — form stores `background_skill_training` |
| AC6 | Lore skill granted | PASS — form stores `background_lore_skill` |
| AC7 | Duplicate boost rejected | PASS — controller line 732–733 |
| AC8 | Re-select replaces prior | PASS — `AbilityScoreTracker` recalculates from current `background` |
| AC9 | Missing background → validation error | PASS — "Background is required." |
| AC10 | Invalid background ID → 400 | PASS — "Invalid background selection." |
| AC11 | Conflict boost caught at save | PASS — duplicate check at validation step |
| AC12 | Anon read backgrounds → 200 | PASS — `GET /backgrounds` returns 200 |
| AC13 | Auth required for character | PASS — `GET /characters/create/step/3` → 403 anon |
| AC14 | Auth user can't modify other's character | PASS — uid check in saveStep |

## Advisory (unchanged, non-blocking)

**API path gap — `background_skill_training` / `background_lore_skill` / `background_skill_feat` not derived in JSON API path.**
- `CharacterCreationStepController::saveStep` step 3 maps only `background` and `background_boosts`; does not derive and store skill/lore/feat.
- Primary form path (`CharacterCreationStepForm`) correctly stores all three at line 1595–1597.
- Status: non-blocking (primary form path used by game UI; API gap is a secondary path concern)
- Recommended Dev fix: mirror form logic in `CharacterCreationStepController::updateStepData` at step 3.

## Evidence

- Background node count: 13 (verified via `drush php:eval`, 2026-04-06T17:49 UTC)
- All 13 entries in `CharacterManager::BACKGROUNDS` verified for complete data (fixed_boost, skill, lore, feat)
- New backgrounds (acrobat, animal_whisperer, artisan, barkeep) confirmed in both constant and seeded nodes
- Site audit 20260406-170141: 0 failures, 0 violations, 0 config drift
- Evidence: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260406-170141/findings-summary.md`

## KB references

- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after module changes (applied by dev)
- Prior QA report: `features/dc-cr-background-system/04-verification-report.md`

## Verdict: APPROVE

All 4 gaps from the prior cycle are confirmed resolved. Background system AC is fully met via primary form path. API path advisory remains open as non-blocking. No new Dev items identified.
