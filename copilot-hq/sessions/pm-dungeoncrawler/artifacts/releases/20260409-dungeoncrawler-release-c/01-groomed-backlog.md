# Groomed Backlog: 20260409-dungeoncrawler-release-c

- Groomed by: pm-dungeoncrawler
- Date: 2026-04-09
- Current active release: 20260409-dungeoncrawler-release-b
- Target release: 20260409-dungeoncrawler-release-c
- Community suggestions: 0 (suggestion-intake.sh returned no new suggestions)

## Summary

All 73 ready dungeoncrawler features are fully groomed (feature.md Status: ready + AC + test plan present). Release-b takes the 10 highest-ROI features (5 P1s + 5 P2s). Release-c targets the next 10 by priority:

- **3 remaining CRB core classes**: Champion, Monk, Ranger (completes the 12-class CRB roster started in releases b through here)
- **Gnome ancestry cluster** (4 features): Gnome Ancestry + Umbral/Sensate/Chameleon heritages — cohesive group delivery avoids partial ancestry in production
- **Core game infrastructure** (3 features): Tactical Grid, Rune System, Multiclass Archetype — foundational mechanics that unblock downstream content

Recommended scope cap: **10 features** (auto-close threshold).

## Recommended Stage 0 scope (priority order)

| Feature | Priority | Notes |
|---|---|---|
| dc-cr-class-champion | P2 | Remaining CRB class. Depends on dc-cr-character-class + dc-cr-focus-spells (ships with release-b or earlier). |
| dc-cr-class-monk | P2 | Remaining CRB class. Same dependency chain as Champion. |
| dc-cr-class-ranger | P2 | Remaining CRB class. Depends on dc-cr-character-class + dc-cr-character-creation. |
| dc-cr-gnome-ancestry | P2 | Cohesive group head. Unlocks all gnome heritages. |
| dc-cr-gnome-heritage-umbral | P2 | Gnome cluster — darkvision reuse (simplest implementation). |
| dc-cr-gnome-heritage-sensate | P2 | Gnome cluster — scent sense + Perception bonus. |
| dc-cr-gnome-heritage-chameleon | P2 | Gnome cluster — terrain Stealth bonus. |
| dc-cr-fey-fellowship | P2 | Gnome ancestry feat. Ships with the ancestry cluster. |
| dc-cr-tactical-grid | P2 | Core infrastructure — grid/positioning engine needed by encounter content. |
| dc-cr-rune-system | P2 | Core infrastructure — item enhancement system needed by equipment-heavy content. |

**Total: 10 features** (at cap — do not add more without triggering auto-close)

## Full groomed backlog (deferred to release-c+ if not selected above)

### Gnome heritage cluster remainder
- dc-cr-gnome-heritage-fey-touched (P3) — fey trait + at-will primal cantrip + daily swap
- dc-cr-gnome-heritage-wellspring (P3) — non-primal tradition override
- dc-cr-first-world-magic (P3) — fixed at-will primal cantrip

### Remaining CRB classes (P3)
- dc-cr-class-bard (P3)
- dc-cr-class-cleric (P3)
- dc-cr-class-druid (P3)
- dc-cr-class-sorcerer (P3)
- dc-cr-class-wizard (P3)

### CRB game mechanics (P2, next after release-c)
- dc-cr-multiclass-archetype (P2)
- dc-cr-exploration-mode (P2)
- dc-cr-downtime-mode (P2)
- dc-cr-economy (P2)
- dc-cr-creature-identification (P2)
- dc-cr-decipher-identify-learn (P2)
- dc-cr-npc-system (P2)
- dc-cr-familiar (P2)
- dc-cr-magic-ch11 (P2)
- dc-cr-feats-ch05 (P2)
- dc-cr-spells-ch07 (P2)
- dc-cr-equipment-ch06 (P2)
- dc-cr-snares (P2)
- dc-cr-treasure-by-level (P2)
- dc-cr-environment-terrain (P2)
- dc-cr-skills-acrobatics-actions (P2)
- dc-cr-skills-crafting-actions (P2)
- dc-cr-skills-deception-actions (P2)
- dc-cr-skills-diplomacy-actions (P2)
- dc-cr-skills-lore-earn-income (P2)
- dc-cr-skills-nature-command-animal (P2)
- dc-cr-skills-survival-track-direction (P2)
- dc-cr-dwarf-ancestry (P2)
- dc-cr-tactical-grid (P2) — if not selected above

### APG deferred (P2, already earmarked for release-b deferred pool)
- dc-apg-class-investigator (P2)
- dc-apg-class-oracle (P2)
- dc-apg-class-swashbuckler (P2)
- dc-apg-equipment (P2)
- dc-apg-feats (P2)
- dc-apg-focus-spells (P2)
- dc-apg-rituals (P2)
- dc-apg-spells (P2)
- dc-cr-animal-companion (P2)

### Other content sources (P3)
- dc-b1-bestiary1 (P3)
- dc-gam-gods-magic (P3)
- dc-gmg-hazards (P3)
- dc-gmg-npc-gallery (P3)
- dc-gmg-running-guide (P3)
- dc-gmg-subsystems (P3)
- dc-cr-gm-narrative-engine (P3)
- dc-cr-crafting (P3)
- dc-cr-rest-watch-starvation (P3)
- dc-cr-dwarf-heritage-ancient-blooded (P3)
- dc-cr-skills-arcana-borrow-spell (P3)
- dc-cr-skills-performance-perform (P3)
- dc-cr-skills-society-create-forgery (P3)

## Groomed/ready gate verification

All 10 recommended features pass the Stage 0 eligibility gate:
- `features/<id>/feature.md` — Status: ready ✓
- `features/<id>/01-acceptance-criteria.md` — present ✓
- `features/<id>/03-test-plan.md` — present ✓
