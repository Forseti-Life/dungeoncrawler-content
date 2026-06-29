# Verification Report: Reqs 2095–2100 — Armor Class and Defenses
- Date: 2026-04-06
- Verifier: qa-dungeoncrawler
- Verdict: APPROVE

## Scope
Reqs 2095–2100: AC formula, three save types, basic save damage tiers, Perception formula, Perception as default initiative, custom initiative override.

## KB reference
None found relevant to AC/saves/initiative in knowledgebase.

## Test Results

| TC | Verdict | Notes |
|---|---|---|
| TC-2095-P | PASS | `calculateAC(10,3,5,FALSE)` → 18 (10+dex+armor) |
| TC-2095-P shield | PASS | `calculateAC(10,3,5,TRUE,[],2)` → 20 (raised shield +2) |
| TC-2095-N | PASS | `calculateAC(10,0,6,FALSE)` → 16 (zero dex/heavy armor) |
| TC-2096-P | PASS | All three save types (Fort/Reflex/Will) return `['total' => ...]` |
| TC-2096-N | PASS | Negative Con mod: `rollSavingThrow(-2,2)` → modifier=0 |
| TC-2097 | PASS (note) | Inbox marked "pending" but req 2097 was implemented in `20260406-impl-save-half-damage`. Confirmed: `floor($base_damage / 2)` at ActionProcessor.php:392. All 4 tiers PASS — see prior verification report. |
| TC-2098-P | PASS | `calculateInitiative(5)` → modifier=5 |
| TC-2098-N | PASS | `calculateInitiative(0)` → modifier=0, total in [1,20] range |
| TC-2099-P | PASS | `CombatEngine::startEncounter` uses `resolvePerceptionModifier()` for auto-initiative at line 94 |
| TC-2099-N | PASS | `resolvePerceptionModifier` falls back: `$entity['perception_modifier'] ?? $entity['perception_mod'] ?? 0` (line 517) |
| TC-2100-P | PASS | `startEncounter($id, array $custom_initiatives = [])` — line 80; `isset($custom_initiatives[$pid])` check at line 88 |
| TC-2100-N | PASS | Participants NOT in `custom_initiatives` hit auto-roll at line 92 with comment "Auto-roll: Perception check = d20 + perception modifier" |

## Note on req 2097 inbox status
The inbox command.md marks req 2097 as "pending" and includes a test case expecting failure. This annotation is stale — the implementation was completed by dev-dungeoncrawler in `20260406-impl-save-half-damage` (committed `f8276b03c`). Full four-tier verification: see `sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-impl-save-half-damage.md`.

## Verification commands
```bash
cd /var/www/html/dungeoncrawler

# TC-2095: AC formula
./vendor/bin/drush ev '$c=\Drupal::service("dungeoncrawler_content.calculator"); var_export([$c->calculateAC(10,3,5,FALSE)["total"], $c->calculateAC(10,3,5,TRUE,[],2)["total"], $c->calculateAC(10,0,6,FALSE)["total"]]);'
# → [18, 20, 16]

# TC-2096: save types
./vendor/bin/drush ev '$c=\Drupal::service("dungeoncrawler_content.calculator"); $s=$c->rollSavingThrow(-2,2); echo $s["modifier"];'
# → 0

# TC-2098: initiative
./vendor/bin/drush ev '$c=\Drupal::service("dungeoncrawler_content.calculator"); $i=$c->calculateInitiative(5); echo $i["modifier"];'
# → 5

# TC-2099/2100: source inspection
grep -n "perception_mod\|custom_initiatives\|Auto-roll" web/modules/custom/dungeoncrawler_content/src/Service/CombatEngine.php
# → lines 80, 88, 92, 94, 513, 517 all confirm implementation
```
