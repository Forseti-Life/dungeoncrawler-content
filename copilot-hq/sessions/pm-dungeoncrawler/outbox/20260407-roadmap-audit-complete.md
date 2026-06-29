# Outbox: Roadmap Audit Complete

- Agent: pm-dungeoncrawler
- Date: 2026-04-07
- Commit: 5045f57fe

## Summary

Completed full roadmap audit of all 2,556 pending requirements in `dc_requirements`.
No section remains at "Not Started" without an active plan. Every requirement is now either:
- In the QA verification queue (Track A — code likely exists), or
- Linked to a feature file in the pipeline (Track B — needs implementation)

## What was done

### 39 new feature files created (all status: planned or deferred)
**Core book classes (12):** dc-cr-class-alchemist, dc-cr-class-barbarian, dc-cr-class-bard,
dc-cr-class-champion, dc-cr-class-cleric, dc-cr-class-druid, dc-cr-class-fighter,
dc-cr-class-monk, dc-cr-class-ranger, dc-cr-class-rogue, dc-cr-class-sorcerer, dc-cr-class-wizard

**APG classes (4):** dc-apg-class-investigator, dc-apg-class-oracle, dc-apg-class-swashbuckler,
dc-apg-class-witch

**APG content (7):** dc-apg-ancestries, dc-apg-archetypes, dc-apg-feats, dc-apg-equipment,
dc-apg-focus-spells, dc-apg-rituals, dc-apg-spells

**Core book gaps (8):** dc-cr-economy, dc-cr-rune-system, dc-cr-snares, dc-cr-feats-ch05,
dc-cr-equipment-ch06, dc-cr-spells-ch07, dc-cr-magic-ch11, dc-cr-spells-ch07

**GMG (3):** dc-gmg-hazards, dc-gmg-npc-gallery, dc-gmg-running-guide

**Deferred/out-of-scope (7):** dc-b1-bestiary1, dc-b2-bestiary2, dc-b3-bestiary3,
dc-gam-guns-gears, dc-gng-lost-omens, dc-som-secrets-of-magic

### 6 QA dispatch batches created (Track A verification)
1. `20260407-roadmap-req-core-ch04-skills-overview` — ch04 overview/general actions/skill table (51 reqs)
2. `20260407-roadmap-req-core-ch04-skills-acrobatics-lore` — 8 skills, reqs 1602–1687 (72 reqs)
3. `20260407-roadmap-req-core-ch04-skills-medicine-thievery` — 9 skills, reqs 1688–1748 (57 reqs)
4. `20260407-roadmap-req-core-ch09-combat-checks` — attack rolls + core checks, reqs 2079–2093 (4 reqs)
5. `20260407-roadmap-req-core-ch10-encounter-dc-xp` — encounter/DC/XP/treasure, reqs 2311–2345 (34 reqs)
6. `20260407-roadmap-req-core-ch10-gm-tools` — env/hazards/NPC/resting, reqs 2331–2397 (37 reqs)

### Feature index updated
94 new entries added to `features/dc-feature-index.md`

## Next actions for QA
- Process 6 new roadmap verification batches in priority order (highest ROI first: ch04 skills)
- After each batch: return PASS/BLOCK results so PM can run drush for PASS sections

## Next actions for PM (after QA returns)
- Run drush to mark PASS sections as implemented
- Create dev dispatch items for any BLOCK findings that don't have a feature file yet
- Prioritize P1 class features (Fighter, Barbarian, Rogue) for next release grooming

## Priority recommendations for next release (post-audit)
1. **P1:** dc-cr-class-fighter, dc-cr-class-barbarian, dc-cr-class-rogue (no spellcasting dependency)
2. **P2:** dc-cr-class-alchemist, dc-cr-class-ranger, dc-cr-class-monk
3. **P2:** dc-cr-equipment-ch06, dc-cr-economy (prerequisite for class starting kits)
4. **P3:** dc-cr-spells-ch07 (large foundation feature, unblocks all spellcaster classes)
