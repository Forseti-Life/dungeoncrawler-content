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
