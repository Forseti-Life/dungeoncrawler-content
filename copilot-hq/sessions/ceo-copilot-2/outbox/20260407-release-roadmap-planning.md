# CEO Outbox: Release Roadmap Planning — DungeonCrawler
- Date: 2026-04-07
- Author: ceo-copilot-2
- Type: planning

## Summary

Completed a full release roadmap planning pass for DungeonCrawler following the completed roadmap audit (240/240 sections covered).

## Actions Taken

### Feature Status Promotions (11 features)
Reviewed all 61 planned features against the done/shipped/in-progress set. Promoted 11 features whose dependencies are fully satisfied:

| Feature | From → To | Rationale |
|---|---|---|
| dc-cr-class-fighter | planned → ready | deps: character-class ✓, character-creation ✓, encounter-rules ✓ |
| dc-cr-class-rogue | planned → ready | deps: character-class ✓, character-creation ✓, skill-system ✓ |
| dc-cr-class-ranger | planned → ready | deps: character-class ✓, character-creation ✓ |
| dc-cr-hazards | planned → ready | no deps |
| dc-cr-encounter-creature-xp-table | planned → ready | deps: encounter-rules ✓ |
| dc-cr-environment-terrain | planned → ready | deps: encounter-rules ✓, skill-system ✓ |
| dc-gmg-hazards | planned → ready | deps: encounter-rules ✓ |
| dc-cr-skills-performance-perform | planned → ready | deps: skill-system ✓ |
| dc-cr-skills-society-create-forgery | planned → ready | deps: skill-system ✓ |
| dc-apg-ancestries | planned → ready | deps: ancestry-system ✓, heritage-system ✓, character-creation ✓ |
| dc-cr-dwarf-ancestry | deferred → ready | deps: ancestry-system ✓, heritage-system ✓, clan-dagger shipped ✓ |

### Release Plan Created
Full 5-release roadmap written to `sessions/pm-dungeoncrawler/artifacts/release-plans/20260407-release-roadmap.md`.

## Release Plan Summary

| Release | Theme | Key Features |
|---|---|---|
| B (in-flight) | Core Mechanics | conditions, difficulty-class, equipment-system |
| C (next) | Ancestry + Leveling | elf-ancestry chain, character-leveling, darkvision, ancestry-traits |
| D | First Classes | fighter, rogue, ranger, barbarian, hazards |
| E | Skill Depth | skills-calculator-hardening, athletics, stealth, medicine, acrobatics |
| F (forward) | Spellcasting | requires spellcasting un-deferral — Board decision needed |

## Ready Feature Count (post-promotion)
- Was: 9 ready
- Now: 20 ready (9 original + 11 promoted)

## Blockers / Escalations

**No Board escalations required.**

Ongoing blocker: `dc-cr-human-ancestry` still blocked on dev task `20260407-load-core-ancestry-requirements` (core/ch01 DB gap). No action needed until dev delivers.

**Recommendation for next PM pass:** Slot Release C features for scope-activation once Release B closes. Release D features (especially fighter) are already ready.

## Next Recommended Actions
1. PM: Scope-activate Release C when Release B closes
2. PM: Request spellcasting scope estimate (for Release F planning) before Release D closes
3. Dev: Deliver `dc-cr-dwarf-ancestry` as part of Release D to unblock the full dwarf heritage chain
