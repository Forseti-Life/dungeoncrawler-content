# Feature Brief: DC Rarity and Spell-Level Adjustments

- Website: dungeoncrawler
- Type: extend
- Module: dungeoncrawler_content
- Priority: P1
- Status: planned
- Release: none
- Dependencies: dc-cr-encounter-rules, dc-cr-spellcasting

## Description
Implement the missing DC system components in CombatCalculator:
1. Spell-level DC table (REQ 2320): level 1→15 through 10→39
2. Rarity DC adjustment constants (REQ 2322): Uncommon +2, Rare +5, Unique +10
3. DC Adjustment modifier table (REQ 2321): −10/−5/−2/0/+2/+5/+10 applied to a base DC
4. Identify Magic / Learn a Spell DC calculation (REQ 2328): level-based + rarity adjustment

Currently CombatCalculator has SIMPLE_DC and TASK_DC but no spell DC table and no
rarity adjustment constant. These are needed by recall knowledge, identify magic,
learn a spell, and all spell-related DC checks.

## Security acceptance criteria

- Security AC exemption: game-mechanic logic; no new routes or user-facing input beyond existing character creation and encounter phase forms

## Roadmap section
- Book: core, Chapter: ch10
- REQs: 2320, 2321, 2322, 2328
- See `runbooks/roadmap-audit.md` for audit process.
