# Gate 2 Verification Report — dc-cr-skill-system

- QA seat: qa-dungeoncrawler
- Date: 2026-04-06
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260405-impl-dc-cr-skill-system.md
- Dev commit reviewed: `97252c34`
- **VERDICT: APPROVE**

---

## Summary

All AC items for dc-cr-skill-system pass. The critical bug — `calculateProficiencyBonus()` adding `level` to the untrained rank — is confirmed fixed in production source. All 5 proficiency rank formulas verified via `drush ev`. All 17 skills defined with correct ability mappings. `calculateSkillCheck()` returns correct structure with degree-of-success, handles Lore specializations, stacks item bonuses additively, and returns explicit error for unknown skills. `GET /character/{id}/skills` endpoint returns 200 with correct skill list for anonymous access. AC regression confirmed clean (untrained armor class no longer inflated by character level).

---

## AC verification results

| AC item | Evidence | Result |
|---|---|---|
| 17 core PF2E skills with ability mappings | `CharacterCalculator.php:235-253` — all 17 skills present with correct ability keys | ✅ PASS |
| Proficiency bonus: Untrained=0 (bug fix) | `CharacterCalculator.php:163-179` — guard clause; `drush ev` confirms rank=untrained level=5 → 0 | ✅ PASS |
| Proficiency bonus: Trained=level+2 | `drush ev` rank=trained level=5 → 7 (5+2) | ✅ PASS |
| Proficiency bonus: Expert=level+4 | `drush ev` rank=expert level=5 → 9 (5+4) | ✅ PASS |
| Proficiency bonus: Master=level+6 | `drush ev` rank=master level=5 → 11 (5+6) | ✅ PASS |
| Proficiency bonus: Legendary=level+8 | `drush ev` rank=legendary level=5 → 13 (5+8) | ✅ PASS |
| `calculateSkillCheck()` returns roll, total, degree_of_success | `drush ev` — `degree` key present, structure confirmed | ✅ PASS |
| Lore specialization support (e.g., "sailing lore" uses Int) | `CharacterCalculator.php:283-290`; `drush ev` "sailing lore" with Int 16 → ability_modifier=3, no error | ✅ PASS |
| Item bonus stacks additively | `drush ev` acrobatics+item_bonus=2 with trained → proficiency_bonus=3, item_bonus=2 both correct | ✅ PASS |
| Unknown skill returns explicit error | `drush ev` "nonexistent_skill" → `{'error': 'Unknown skill: nonexistent_skill. Valid skills: ...'}` | ✅ PASS |
| Untrained uses ability mod only (no proficiency) | `drush ev` athletics untrained level=3, Str=18 → proficiency_bonus=0, ability_modifier=4 | ✅ PASS |
| `GET /character/{id}/skills` endpoint | `curl https://dungeoncrawler.forseti.life/character/1/skills` → 200, JSON with all 17 skills | ✅ PASS |
| Invalid character ID returns 404 | `curl .../character/999999/skills` → 404 | ✅ PASS |
| Lore skills with `is_lore: true` in response | `CharacterManager.php:2015-2032` — lore_skills appended with `is_lore: TRUE` | ✅ PASS |
| Multiple Lore skills stored independently | `CharacterManager.php:2015-2032` — iterates `lore_skills[]` array, appends each separately | ✅ PASS |

---

## AC regression: untrained armor class

Dev flagged a regression risk: `calculateArmorClass()` also calls `calculateProficiencyBonus()`. With the untrained fix in place:

```
Level 5, DEX 12, unarmored, untrained — expected AC: 10 + 1 (dex) + 0 (untrained prof) = 11
Pre-fix value would have been: 10 + 1 + 5 (level wrongly added) = 16
```

`drush ev` → total=11 ✅ PASS (regression confirmed clean)

---

## Prod API live test

```bash
curl -s https://dungeoncrawler.forseti.life/character/1/skills
# Returns: {"success": true, "character_id": 1, "skills": [{name: "acrobatics", rank: "untrained", ability: "dexterity", bonus: 0, is_lore: false}, ...17 entries]}
```

Site audit 20260406-141228: 0 missing assets, 0 permission violations. No skill-route failures.

---

## Advisory — CharacterCalculatorTest.php stubs

`dungeoncrawler_content/tests/src/Unit/Service/CharacterCalculatorTest.php` exists but all methods call `$this->markTestIncomplete()`. The dev AC references TC-SK-01 through TC-SK-17 test IDs that do not exist. This is advisory and does not block Gate 2 (correctness was verified via `drush ev`), but unit test coverage for `calculateSkillCheck()` and `calculateProficiencyBonus()` remains at stub-level. Recommend Dev implement these before Gate 4.

---

## Re-verification path

```bash
cd /home/ubuntu/forseti.life/sites/dungeoncrawler
./vendor/bin/drush ev "
  \$c = new \Drupal\dungeoncrawler_content\Service\CharacterCalculator();
  print \$c->calculateProficiencyBonus('untrained', 5); // expect 0
  print \$c->calculateProficiencyBonus('trained', 5);   // expect 7
"

curl -s https://dungeoncrawler.forseti.life/character/1/skills | python3 -m json.tool | grep '"name"' | wc -l
# expect 17
```
