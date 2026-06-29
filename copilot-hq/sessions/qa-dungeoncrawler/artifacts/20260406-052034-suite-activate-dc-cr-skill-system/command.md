# Suite Activation: dc-cr-skill-system

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-06T05:20:34+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-skill-system"`**  
   This links the test to the living requirements doc at `features/dc-cr-skill-system/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-skill-system-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-skill-system",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-skill-system"`**  
   Example:
   ```json
   {
     "id": "dc-cr-skill-system-<route-slug>",
     "feature_id": "dc-cr-skill-system",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-skill-system",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-skill-system

**Owner:** qa-dungeoncrawler  
**Date:** 2026-03-28  
**Feature:** Skill System — 17 skill constants with ability associations, proficiency bonus formula, calculateSkillCheck(), Lore specializations, GET /character/{id}/skills, permissions  
**AC source:** `features/dc-cr-skill-system/01-acceptance-criteria.md`  
**Implementation notes:** `features/dc-cr-skill-system/02-implementation-notes.md`  

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` after any module changes; test immediately after rebuild.
- KB check (AC): "none found" — no prior lessons for skill check resolution. Reference: PF2E Core Rulebook Chapter 4: Skills.
- Prior art: `dc-cr-difficulty-class` AC defines `getSimpleDC(level)` and `getTaskDC(difficulty)` — skill check DCs will come from that service. Regression risk when both features are in scope.

## Gap analysis summary (from AC + impl notes)
- 17 core SKILLS constant with ability associations — **Partial** (no typed constant confirmed); TCs verify all 17 present with correct ability
- Proficiency bonus formula — **Partial** (`calculateProficiencyBonus()` exists as TODO); TCs verify each rank
- `calculateSkillCheck()` — **Partial** (`CharacterCalculator` exists; method missing); TCs verify happy path + item bonus stacking + untrained
- Lore skill specialization — **None** → new; TCs verify store + retrieve + multiple Lore skills
- `GET /character/{id}/skills` endpoint — **None** → new route (slice 2); TCs verify response shape and access control
- Unknown skill name error — **None** → new; TC verifies structured error

## Suite mapping

| Suite | Runner | Use for |
|---|---|---|
| `module-test-suite` (unit) | PHPUnit unit (`./vendor/bin/phpunit tests/src/Unit/Service/`) | CharacterCalculator: SKILLS constant, proficiency formula, calculateSkillCheck, item bonus stacking, unknown skill error; CharacterManager: Lore storage + multi-Lore |
| `module-test-suite` (functional) | PHPUnit functional (`./vendor/bin/phpunit tests/src/Functional/`) | GET /character/{id}/skills — response shape, auth, owner-only write, admin override |
| `role-url-audit` | `scripts/site-audit-run.sh` | GET /character/{id}/skills permissions (anonymous read in game session, auth-required for rank modification) |

> **Note:** `GET /character/{id}/skills` uses a parameterized path — add as `ignore` in route-scan in `qa-permissions.json` at Stage 0 preflight. Access control verified via functional tests.

---

## Test cases

### TC-SK-01 — SKILLS constant defines all 17 core skills
- **AC:** `[EXTEND]` All 17 core PF2E skills defined as named constants
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testSkillsConstantContainsAll17Skills()`
- **Setup:** Access `CharacterCalculator::SKILLS`; count entries
- **Expected:** Count = 17; all 17 skill names present: Acrobatics, Arcana, Athletics, Crafting, Deception, Diplomacy, Intimidation, Lore, Medicine, Nature, Occultism, Performance, Religion, Society, Stealth, Survival, Thievery
- **Roles:** n/a (data layer)

### TC-SK-02 — SKILLS constant maps each skill to correct ability score
- **AC:** `[EXTEND]` Each skill associated with the correct ability: Acrobatics=Dex, Arcana=Int, Athletics=Str, etc.
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testSkillsConstantHasCorrectAbilityMappings()`
- **Setup:** Read `CharacterCalculator::SKILLS`; assert key-value pairs
- **Expected:** `SKILLS['Acrobatics'] = 'Dex'`, `SKILLS['Athletics'] = 'Str'`, `SKILLS['Arcana'] = 'Int'`, `SKILLS['Lore'] = 'Int'`, `SKILLS['Stealth'] = 'Dex'` (spot-check all 17)
- **Roles:** n/a (data layer)

### TC-SK-03 — Proficiency bonus: Untrained = 0 (at any level)
- **AC:** `[EXTEND]` Untrained proficiency bonus = 0
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testProficiencyBonusUntrained()`
- **Setup:** Call `calculateProficiencyBonus('untrained', 5)`
- **Expected:** Returns 0 (no level-based bonus for untrained)
- **Roles:** n/a (service layer)

### TC-SK-04 — Proficiency bonus: Trained = level + 2
- **AC:** `[EXTEND]` Trained proficiency bonus = level + 2
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testProficiencyBonusTrained()`
- **Setup:** Call `calculateProficiencyBonus('trained', 3)`
- **Expected:** Returns 5 (3 + 2)
- **Roles:** n/a (service layer)

### TC-SK-05 — Proficiency bonus: Expert = level + 4; Master = level + 6; Legendary = level + 8
- **AC:** `[EXTEND]` Expert/Master/Legendary proficiency bonus formulas
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testProficiencyBonusHigherRanks()`
- **Setup:** Call each: `calculateProficiencyBonus('expert', 5)`, `calculateProficiencyBonus('master', 5)`, `calculateProficiencyBonus('legendary', 5)`
- **Expected:** Expert=9 (5+4), Master=11 (5+6), Legendary=13 (5+8)
- **Roles:** n/a (service layer)

### TC-SK-06 — calculateSkillCheck returns roll, total, and degree_of_success
- **AC:** `[EXTEND]` `calculateSkillCheck()` returns `{roll, total, degree_of_success}`
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testCalculateSkillCheckReturnShape()`
- **Setup:** Character with Stealth rank=trained at level 3 (proficiency=5) and Dex modifier +3; mock d20 roll = 10; DC = 15; call `calculateSkillCheck($id, 'Stealth', 15)`
- **Expected:** `roll = 10`, `total = 10 + 3 + 5 = 18`, `degree_of_success = success` (18 ≥ DC 15)
- **Roles:** n/a (service layer)

### TC-SK-07 — calculateSkillCheck: untrained uses ability mod only
- **AC:** `[EXTEND]` Untrained skill check uses ability modifier only (proficiency = 0)
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testCalculateSkillCheckUntrained()`
- **Setup:** Character with Arcana rank=untrained at level 5; Int modifier +2; mock d20 = 12; DC = 14
- **Expected:** `total = 12 + 2 + 0 = 14`; `degree_of_success = success` (exactly meets DC)
- **Roles:** n/a (service layer)

### TC-SK-08 — calculateSkillCheck: item_bonus stacks additively
- **AC:** `[NEW]` Skill check item_bonus stacks additive with proficiency and ability modifier
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testCalculateSkillCheckItemBonusStacks()`
- **Setup:** Character with Athletics rank=expert at level 4 (proficiency=8), Str modifier +4; mock d20 = 8; DC = 22; item_bonus = +2; call `calculateSkillCheck($id, 'Athletics', 22, 2)`
- **Expected:** `total = 8 + 4 + 8 + 2 = 22`; `degree_of_success = success` (exactly meets DC)
- **Roles:** n/a (service layer)

### TC-SK-09 — calculateSkillCheck with unknown skill name returns explicit error
- **AC:** `[NEW]` Unknown skill name returns explicit error
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterCalculatorTest::testCalculateSkillCheckUnknownSkillReturnsError()`
- **Setup:** Call `calculateSkillCheck($id, 'Prestidigitation', 15)` (not a valid PF2E skill name)
- **Expected:** Returns structured error array with `error` key and descriptive message; no unhandled PHP exception
- **Roles:** n/a (service layer)

### TC-SK-10 — Lore skill stored with specialization string as key
- **AC:** `[NEW]` Lore skill supports specialization string; stored as distinct skill
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterManagerTest::testLoreSkillStoredWithSpecialization()`
- **Setup:** Add "Sailing Lore" as a skill to character with rank=trained; save; reload character
- **Expected:** Character's skill data contains `"Sailing Lore"` with `{rank: 'trained', ability: 'Int'}`; distinct from core `"Lore"` entry
- **Roles:** n/a (service layer)

### TC-SK-11 — Multiple Lore skills stored and retrievable independently
- **AC:** `[EXTEND]` Multiple Lore skills on the same character are all stored independently
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterManagerTest::testMultipleLoreSkillsStoredIndependently()`
- **Setup:** Add "Sailing Lore" (trained) and "Underworld Lore" (expert) to the same character; reload
- **Expected:** Both Lore entries present; "Sailing Lore" has rank=trained; "Underworld Lore" has rank=expert; neither overwrites the other
- **Roles:** n/a (service layer)

### TC-SK-12 — GET /character/{id}/skills returns skill list with rank and bonus
- **AC:** `[NEW]` `GET /character/{id}/skills` returns character's skill list with proficiency rank and current bonus
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CharacterSkillsApiTest::testGetCharacterSkillsReturnsRankAndBonus()`
- **Setup:** Character owner authenticated; character has Stealth=trained at level 3, Dex=+2; GET `/character/{id}/skills`
- **Expected:** HTTP 200; response includes `Stealth: {rank: 'trained', bonus: 7}` (3+2+2); all 17 core skills present; Lore specializations included if any
- **Roles:** authenticated player (owner)

### TC-SK-13 — GET /character/{id}/skills: anonymous access in active game session
- **AC:** Anonymous user behavior: character skill data is readable in the context of an active game session
- **Suite:** `role-url-audit`
- **Entry:** `GET /character/{id}/skills` — parameterized path; add `ignore` to route-scan at Stage 0; verify via functional test: anonymous GET returns 200 (public game state read)
- **Test class/method:** `CharacterSkillsApiTest::testAnonymousCanReadSkillsInGameSession()`
- **Expected:** HTTP 200 for anonymous; skill list with ranks returned (read-only view)
- **Roles:** anonymous

### TC-SK-14 — Only character owner can modify skill ranks
- **AC:** Authenticated user behavior: only the character's owner can modify skill ranks
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CharacterSkillsApiTest::testOnlyOwnerCanModifySkillRanks()`
- **Setup:** Player A owns character; Player B attempts to PATCH/PUT skill rank on Player A's character
- **Expected:** 403 or authorization exception; skill rank unchanged
- **Roles:** authenticated player (other's character)

### TC-SK-15 — Admin can override skill ranks
- **AC:** Admin behavior: admin can override skill ranks for QA/debug
- **Suite:** `module-test-suite` (functional)
- **Test class/method:** `CharacterSkillsApiTest::testAdminCanOverrideSkillRank()`
- **Setup:** Admin modifies skill rank for any character
- **Expected:** Success; skill rank updated; character JSON reflects new rank
- **Roles:** admin

### TC-SK-16 — Skill ranks persist in character entity JSON
- **AC:** Skill ranks stored as part of character entity JSON (CharacterStateService pattern)
- **Suite:** `module-test-suite` (unit)
- **Test class/method:** `CharacterManagerTest::testSkillRanksPersistInCharacterJson()`
- **Setup:** Set Athletics rank=master for a character; save; reload from CharacterStateService
- **Expected:** Reloaded character's skill JSON contains Athletics=master; no data loss
- **Roles:** n/a (data integrity)

### TC-SK-17 — QA audit still passes after module deployment
- **AC:** QA automated audit must still pass (0 violations, 0 failures)
- **Suite:** `role-url-audit` (full audit run)
- **Command:** `scripts/site-audit-run.sh dungeoncrawler` (local dev)
- **Expected:** 0 violations, 0 failures
- **Roles:** all 6 roles

---

## Items not expressable as automation (PM note)

| AC item | Reason |
|---|---|
| "Error messages are clear and actionable" (`[TEST-ONLY]`) | Automation verifies error is returned with a structured key; "actionable" judgment requires human review. |
| "Untrained skill check may be penalized if task requires training" (AC edge case) | The penalty for using untrained in a training-required context is a game-rule decision (not yet defined in AC as a code behavior). Automation cannot verify an undefined penalty amount. PM should clarify whether this is a structured error, a numerical penalty, or a soft warning; test case deferred until AC is concrete. |
| Skill action catalog | AC notes "None" for skill action definitions (out of scope for this feature). No TCs included. |

---

## Regression risk areas

1. `dc-cr-difficulty-class` coupling: `calculateSkillCheck()` compares total vs DC; if `dc-cr-difficulty-class` changes DC calculation logic or introduces a breaking interface change, skill check degree-of-success results may silently shift.
2. `dc-cr-encounter-rules` coupling: `resolveAttack()` and skill checks both use `NumberGenerationService::rollDie(20)` and `calculateProficiencyBonus()`; any refactor of `CharacterCalculator` affects both systems.
3. `dc-cr-equipment-system` coupling: armor check penalty from `dc-cr-equipment-system` TC-EQ-17 applies to Str/Dex skill checks (Athletics, Acrobatics, Stealth, Thievery); if equipment system applies the penalty via `getSkillModifier()`, skill check calculation must call through that method rather than directly accessing ability modifier.
4. `CharacterStateService` JSON structure: Lore specialization storage pattern must be consistent with how `CharacterStateService` serializes and deserializes the skills array; any change to the character JSON schema can silently drop Lore entries.
5. Untrained penalty (deferred): if/when PM defines a numerical penalty for training-required tasks on untrained characters, TC-SK-07 must be updated and a new TC added for the penalty behavior.
6. `GET /character/{id}/skills` route registration: new route must be added to `qa-permissions.json` at Stage 0 preflight (parameterized path → `ignore` for route-scan; access control verified via functional tests).

### Acceptance criteria (reference)

# Acceptance Criteria (PM-owned)
# Feature: dc-cr-skill-system

## Gap analysis reference

Gap analysis performed against `CharacterManager.php`, `CharacterCalculator.php`, `CharacterStateService.php`.

Coverage findings:
- Skill list (17 core skills) — **Partial** (likely in CharacterStateService character JSON; not confirmed as typed constants)
- Proficiency ranks (Untrained/Trained/Expert/Master/Legendary = 0–4) — **Partial** (character JSON may store ranks; enforcement in checks unclear)
- Skill check calculation (d20 + ability mod + proficiency bonus + item bonus vs. DC) — **Partial** (CharacterCalculator exists; skill check formula not confirmed)
- Skill action definitions — **None** (no skill action catalog found)
- Lore skills (variable specializations) — **None**

Feature type: **enhancement** (character skill fields likely exist; add typed constants, check resolution endpoint, and Lore skill support)

## Happy Path
- [ ] `[EXTEND]` All 17 core PF2E skills are defined as named constants with their associated ability score: Acrobatics(Dex), Arcana(Int), Athletics(Str), Crafting(Int), Deception(Cha), Diplomacy(Cha), Intimidation(Cha), Lore(Int), Medicine(Wis), Nature(Wis), Occultism(Int), Performance(Cha), Religion(Wis), Society(Int), Stealth(Dex), Survival(Wis), Thievery(Dex).
- [ ] `[EXTEND]` Proficiency bonus by rank: Untrained = 0, Trained = level+2, Expert = level+4, Master = level+6, Legendary = level+8.
- [ ] `[EXTEND]` `calculateSkillCheck(character_id, skill_name, dc, item_bonus = 0): array` returns `{ roll, total, degree_of_success }`.
- [ ] `[NEW]` Lore skill supports a specialization string (e.g., "Sailing Lore", "Underworld Lore") and is stored/retrieved as a distinct skill on the character.
- [ ] `[NEW]` `GET /character/{id}/skills` returns the character's skill list with proficiency rank and current bonus.

## Edge Cases
- [ ] `[EXTEND]` Untrained skill check uses the ability modifier only (no proficiency bonus), but may be penalized if the task requires training.
- [ ] `[NEW]` Skill check with item_bonus stacks additive with proficiency and ability modifier.
- [ ] `[EXTEND]` Multiple Lore skills on the same character are all stored and retrievable independently.

## Failure Modes
- [ ] `[TEST-ONLY]` Error messages are clear and actionable.
- [ ] `[NEW]` Requesting a skill check for an unknown skill name returns an explicit error.

## Permissions / Access Control
- [ ] Anonymous user behavior: character skill data is readable in the context of an active game session.
- [ ] Authenticated user behavior: only the character's owner can modify skill ranks.
- [ ] Admin behavior: admin can override skill ranks for QA/debug purposes.

## Data Integrity
- [ ] Skill ranks are stored as part of the character entity JSON (consistent with existing CharacterStateService pattern).
- [ ] Rollback path: skill rank changes are part of character save state; character can be reverted to a prior JSON snapshot.

## Knowledgebase check
- Related lessons/playbooks: none found. Reference PF2E Core Rulebook Chapter 4: Skills.

## Test path guidance (for QA)
| Requirement | Test path |
|---|---|
| 17 skills defined with ability | `tests/src/Unit/Service/CharacterCalculatorTest.php` |
| Proficiency bonus formula | `tests/src/Unit/Service/CharacterCalculatorTest.php` |
| `calculateSkillCheck` | `tests/src/Unit/Service/CharacterCalculatorTest.php` (extend) |
| Lore skill storage | `tests/src/Unit/Service/CharacterManagerTest.php` |
| `GET /character/{id}/skills` | `tests/src/Functional/CharacterSkillsApiTest.php` (new) |
- Agent: qa-dungeoncrawler
- Status: pending
