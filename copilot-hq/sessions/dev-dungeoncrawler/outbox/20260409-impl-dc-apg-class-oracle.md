# Outbox: Oracle Class (APG) Implementation

- Status: done
- Summary: Oracle class mechanics were already fully implemented in CharacterManager.php. `CLASSES['oracle']` (L2038) contains complete Mystery selection, Cursebound state machine (4 stages: basic→minor→moderate→overwhelmed), Focus Pool (start 2, cap 3), Revelation Spells, and spontaneous divine spellcasting. `ORACLE_MYSTERIES` (L3018) defines all 8 mysteries (Ancestors, Battle, Bones, Cosmos, Flames, Life, Lore, Tempest) with initial/advanced/greater revelation spells per mystery. `CLASS_ADVANCEMENT['oracle']` (L7298) covers L1–L19 with all proficiency milestones including L11 Major Curse unlock, L15 Extreme Curse unlock, Expert→Master→Legendary spellcasting progression, and L17 Resolve. `FOCUS_POOLS['oracle']`, `CASTER_SPELL_SLOTS['oracle']`, `CLASS_TRADITIONS['oracle']`, and `STARTING_GEAR['oracle']` are all present. `dc_requirements` has 39 oracle-related rows (> 0 threshold met). Site returns 200. No code changes were needed; feature.md updated to done.

## Commits
- None required (no code changes — implementation was already complete)

## Verification
- `CLASSES['oracle']`: Cursebound state machine, Mystery required, Focus Pool (2/3), 8 mysteries ✓
- `ORACLE_MYSTERIES`: 8 entries with initial/advanced/greater revelation spells ✓
- `CLASS_ADVANCEMENT['oracle']`: L1–L19 auto_features including curse stages, spellcasting tiers ✓
- `dc_requirements` oracle rows: 39 (> 0) ✓
- Site: `https://dungeoncrawler.forseti.life` → HTTP 200 ✓
- Feature `dc-apg-class-oracle` marked Status: done ✓

## Next actions
- Awaiting next inbox item dispatch

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 18
- Rationale: Completes Oracle for release-g; unblocks QA gate for this class. All curse mechanics and mystery variants are implemented, enabling the high-risk spellcaster playstyle.
