# Implementation Notes (Dev-owned)
# Feature: dc-cr-skill-system

## Status
- Status: done (QA Gate 2 APPROVE — commit 711a7b894)

## Summary
Assessment (2026-04-06): All AC items confirmed implemented. One bug fixed: `calculateProficiencyBonus()` was incorrectly adding `level` to the untrained rank (returning `level` instead of `0`). Fixed to return `0` for untrained unconditionally. SKILLS constant (17 skills, correct ability mappings), `calculateSkillCheck()` (d20+ability+proficiency+item, degree-of-success, Lore specializations, unknown skill error), and `GET /character/{id}/skills` endpoint are all complete. QA Gate 2 APPROVE: commit `711a7b894`.

## Impact Analysis
- `CharacterCalculator.php` — additive changes only (new constant + new method).
- No schema changes needed; skills are stored in character JSON (existing `CharacterStateService` pattern).
- `GET /character/{id}/skills` route is slice 2 (new route in routing.yml + controller extension).
- Lore skill: stored as a named entry in the character's skill array with specialization as the key (e.g., `"Sailing Lore" => {rank: 1, ability: "Int"}`); additive to the 17 core skills.

## Files / Components Touched
- `dungeoncrawler_content/src/Service/CharacterCalculator.php`:
  - Add `const SKILLS` array: 17 entries keyed by skill name with `ability` field (Str/Dex/Con/Int/Wis/Cha)
  - Implement `calculateSkillCheck(int $character_id, string $skill_name, int $dc, int $item_bonus = 0): array` — returns `{roll, total, degree_of_success}`
  - Implement `calculateProficiencyBonus(string $rank, int $level): int` — currently a TODO; formula: untrained=0, trained=level+2, expert=level+4, master=level+6, legendary=level+8
- `dungeoncrawler_content/dungeoncrawler_content.routing.yml` — `GET /character/{id}/skills` (slice 2)
- New/extended controller for character skills endpoint (slice 2)

## Data Model / Storage Changes
- Schema updates: none (skills in character JSON)
- Config changes: none
- Migrations: none

## First code slice
1. Add `SKILLS` constant to `CharacterCalculator`:
   ```php
   const SKILLS = [
     'Acrobatics' => 'Dex', 'Arcana' => 'Int', 'Athletics' => 'Str',
     'Crafting' => 'Int', 'Deception' => 'Cha', 'Diplomacy' => 'Cha',
     'Intimidation' => 'Cha', 'Lore' => 'Int', 'Medicine' => 'Wis',
     'Nature' => 'Wis', 'Occultism' => 'Int', 'Performance' => 'Cha',
     'Religion' => 'Wis', 'Society' => 'Int', 'Stealth' => 'Dex',
     'Survival' => 'Wis', 'Thievery' => 'Dex',
   ];
   ```
2. Implement `calculateProficiencyBonus()` (remove TODO; add formula).
3. Implement `calculateSkillCheck()`: load character, get ability modifier, get proficiency rank for skill, roll d20, compute total, compare vs DC, return degree_of_success array.
4. Unknown skill name → return explicit error array.

## Security Considerations
- Input validation: skill_name must exist in SKILLS or be a Lore specialization; reject unknown names.
- Access checks: `GET /character/{id}/skills` is auth-required for the character owner (slice 2).
- Sensitive data handling: none.

## Testing Performed
- Commands run: (pending implementation)
- Targeted scenarios:
  - calculateSkillCheck with trained rank (level 1) → proficiency = 3; total = d20 + ability_mod + 3 + item_bonus
  - Untrained → proficiency = 0
  - Unknown skill name → explicit error
  - Lore skill (stored as "Sailing Lore") → retrievable independently

## Rollback / Recovery
- Revert commit. No schema changes. CharacterCalculator is a pure-PHP service.

## Knowledgebase references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md`
- PF2E Core Rulebook Chapter 4: Skills (reference for skill definitions).

## What I learned (Dev)
- (pending)

## What I'd change next time (Dev)
- (pending)
