# Verification Report: dc-cr-character-class

**Gate:** 2 — QA Verification  
**QA owner:** qa-dungeoncrawler  
**Date:** 2026-04-06  
**Dev commit verified:** `30e62db8`  
**Result:** APPROVE

---

## AC verification table

| # | AC item | Verified? | Evidence |
|---|---|---|---|
| 1 | `character_class` content type exists | ✅ | `drush php-eval` entity query returns 16 nodes; type `character_class` confirmed |
| 2 | All 12 core PF2E classes seeded (alchemist, barbarian, bard, champion, cleric, druid, fighter, monk, ranger, rogue, sorcerer, wizard) | ✅ | All 12 confirmed present in DB + 4 extended (investigator, oracle, swashbuckler, witch) |
| 3 | Class selection stored on character entity | ✅ | `CharacterCreationStepForm::submitForm()` stores `class` key in character JSON |
| 4 | HP-per-level set from class (fighter:10, wizard:6, cleric:8) | ✅ | `CharacterManager::CLASSES['fighter']['hp'] = 10`, `['wizard']['hp'] = 6`; stored in character data |
| 5 | Class proficiencies applied to character record | ✅ | `CharacterCreationStepForm.php:1578-1579` stores `class_proficiencies` from `CLASSES` constant |
| 6 | 1st-level class features recorded | ✅ | `CharacterManager::CLASSES` entries include `proficiencies` dict; step 4 commit stores them |
| 7 | Re-selecting class replaces prior choice | ✅ | `submitForm` step 4 overwrites `class` and `class_proficiencies` keys unconditionally |
| 8 | "Class selection is required." returned when no class chosen | ✅ | `CharacterCreationStepForm.php:1375` `setErrorByName('class', 'Class selection is required.')` |
| 9 | Key ability choice required for multi-option classes | ✅ | `CharacterCreationStepForm.php:1385-1386` sets error "You must choose a key ability for this class." |
| 10 | Invalid class ID → 400 | ✅ | `CharacterApiController.php:108-116` returns `{error: 'Invalid class: <id>'}` HTTP 400 |
| 11 | `character_class` nodes publicly readable (anon) | ✅ | `GET https://dungeoncrawler.forseti.life/node/18` → HTTP 200 |
| 12 | Character creation requires authentication (anon → 403) | ✅ | `GET /characters/create/step/1` → HTTP 403 for anon |

---

## Advisory notes (non-blocking)

1. **Content type fields**: The `character_class` Drupal content type has no custom Drupal fields (field_key_ability, field_hp_per_level, etc.). All mechanical data lives in `CharacterManager::CLASSES` constant. The content type is used for admin-facing node management (title only). Functional AC is satisfied but admin cannot edit class stats via Drupal UI without code changes. Flag for future BA/PM consideration as a UX improvement.

2. **TC-CC-10 string mismatch**: The validation message is `"Class selection is required."` not `"Class is required"` as written in TC-CC-10. This is a test plan inaccuracy — the implementation message is more descriptive. No functional gap.

3. **TC-CC-02 count**: DB has 16 class nodes; TC-CC-02 expects 12. The 4 extended classes (investigator, oracle, swashbuckler, witch) are bonus content, not a regression. All 12 core PF2E classes are present.

---

## Gate 2 result

**APPROVE** — all functional AC items verified. Three [NEW] AC gaps closed in commit `30e62db8`. Advisory notes filed for PM/BA review; none are blocking.
