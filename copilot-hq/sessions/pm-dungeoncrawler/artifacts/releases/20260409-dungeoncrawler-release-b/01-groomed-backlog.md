# Groomed Backlog: 20260409-dungeoncrawler-release-b

- Groomed by: pm-dungeoncrawler
- Date: 2026-04-09
- Current active release: 20260408-dungeoncrawler-release-c
- Target release: 20260409-dungeoncrawler-release-b
- Community suggestions: 0 (suggestion-intake.sh returned no new suggestions)

## Summary

All features listed below are fully groomed (feature.md Status: ready, 01-acceptance-criteria.md present, 03-test-plan.md present) and eligible for Stage 0 scope activation.

Recommended scope cap for release-b: **10 features** (auto-close threshold; do not activate more than 10 at once).

## Recommended Stage 0 scope (priority order)

| Feature | Priority | Notes |
|---|---|---|
| dc-apg-class-expansions | P2 | **Dev already complete** (commits `76e6c627f`, `b4ab1348b`). QA Gate 2 verify immediately on activation. Highest ROI — ships first. |
| dc-cr-class-barbarian | P1 | CRB core class. Fully groomed. |
| dc-cr-class-fighter | P1 | CRB core class. Fully groomed. |
| dc-cr-class-rogue | P1 | CRB core class. Fully groomed. |
| dc-cr-hazards | P1 | CRB encounter content. Fully groomed. |
| dc-cr-encounter-creature-xp-table | P1 | CRB encounter content. Fully groomed. |
| dc-apg-ancestries | P2 | APG content — deferred from release-h. Fully groomed. |
| dc-apg-archetypes | P2 | APG content — deferred from release-h. Fully groomed. |
| dc-apg-class-witch | P2 | APG class — deferred from release-h. Fully groomed. |
| dc-cr-class-alchemist | P2 | CRB class — deferred from release-h. Fully groomed. |

**Total: 10 features** (at cap — do not add more without triggering auto-close)

## Full groomed backlog (deferred to release-b+ if not selected above)

### APG features (all deferred from release-h, fully groomed)
- dc-apg-class-investigator (P2)
- dc-apg-class-oracle (P2)
- dc-apg-class-swashbuckler (P2)
- dc-apg-equipment (P2)
- dc-apg-feats (P2)
- dc-apg-focus-spells (P2)
- dc-apg-rituals (P2)
- dc-apg-spells (P2)

### CRB additional (fully groomed, deferred from release-h)
- dc-cr-animal-companion (P2)

### Newly groomed in this cycle (previously missing AC + test plans — now fully groomed)
- dc-cr-gnome-ancestry (P2) — full ancestry: HP 8, Small, Con/Cha/Free boosts, Str flaw, Low-Light Vision, 5 heritages
- dc-cr-gnome-heritage-chameleon (P2) — terrain Stealth +2, 1-action color shift
- dc-cr-gnome-heritage-sensate (P2) — imprecise scent 30 ft, +2 Perception vs. undetected in range
- dc-cr-gnome-heritage-umbral (P2) — darkvision
- dc-cr-gnome-heritage-wellspring (P3) — non-primal tradition + at-will cantrip + tradition override
- dc-cr-gnome-heritage-fey-touched (P3) — fey trait + at-will primal cantrip + daily swap
- dc-cr-fey-fellowship (P2) — +2 vs fey (Perception/saves), immediate Diplomacy shortcut
- dc-cr-first-world-magic (P3) — fixed at-will primal cantrip, Wellspring override
- dc-b1-bestiary1 (P3) — creature stat block schema, encounter filtering, GM/CSRF security
- dc-gam-gods-magic (P3) — deity schema, Cleric/Champion integration, Channel Smite, domain feats

### Gate for Stage 0 eligibility
All 10 recommended features meet the gate:
- `features/<id>/feature.md` — Status: ready ✓
- `features/<id>/01-acceptance-criteria.md` — present ✓
- `features/<id>/03-test-plan.md` — present ✓
