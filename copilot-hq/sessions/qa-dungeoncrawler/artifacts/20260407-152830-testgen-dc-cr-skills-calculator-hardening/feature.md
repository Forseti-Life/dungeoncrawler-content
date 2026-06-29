# Feature Brief: Skills Calculator Hardening

- Website: dungeoncrawler
- Type: extend
- Module: dungeoncrawler_content
- Priority: P1
- Status: planned
- Release: none
- Dependencies: dc-cr-skill-system, dc-cr-character-leveling

## Description
Harden CharacterCalculator and CharacterLevelingService with missing enforcement
rules identified in QA audit of core/ch04 (REQs 1554–1568, 1600, 2323):

1. **Trained-only gating** (REQs 1554, 1600, 2323): calculateSkillCheck() must return
   error/block when an untrained character attempts a trained-only action
2. **Rank ceiling enforcement** (REQs 1563, 1564): submitSkillIncrease() must enforce
   level ≥ 7 for expert→master and level ≥ 15 for master→legendary
3. **Armor check penalty** (REQs 1566, 1567): calculateSkillCheck() must apply armor
   check penalty to Str/Dex-based skill rolls (exception: attack trait)
4. **Secret trait** (REQ 1568): calculateSkillCheck() must support a `secret` flag
   that withholds the raw roll result from the player-facing response
5. **Skill DC method** (REQ 1556): add calculateSkillDC() = 10 + skill modifier
6. **Redundant training redirect** (REQ 1553): detect same-skill grant from two
   sources and redirect to a different skill
7. **DC Adjustment modifiers** (REQ 2321): implement −10/−5/−2/0/+2/+5/+10 as
   additive modifiers applied to a base DC (not fixed TASK_DC values)

## Security acceptance criteria

- Security AC exemption: game-mechanic skill action logic; no new routes or user-facing input beyond existing character creation and leveling forms

## Roadmap section
- Book: core, Chapter: ch04, ch10
- REQs: 1553, 1554, 1556, 1563, 1564, 1566, 1567, 1568, 1600, 2321, 2323
- See `runbooks/roadmap-audit.md` for audit process.
