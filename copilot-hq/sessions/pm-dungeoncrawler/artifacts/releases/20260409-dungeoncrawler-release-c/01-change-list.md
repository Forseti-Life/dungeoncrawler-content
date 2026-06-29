# Release Change List: 20260409-dungeoncrawler-release-c

## Release metadata
- Release ID: 20260409-dungeoncrawler-release-c
- Site: dungeoncrawler
- Status: active (in_progress)
- Activated: 2026-04-09

## Scoped features (10)

| # | Feature ID | Title | Priority | Dev status |
|---|---|---|---|---|
| 1 | dc-cr-class-champion | Champion Class Mechanics | P2 CRB | pending |
| 2 | dc-cr-class-monk | Monk Class Mechanics | P2 CRB | pending |
| 3 | dc-cr-class-ranger | Ranger Class Mechanics | P2 CRB | pending |
| 4 | dc-cr-gnome-ancestry | Gnome Ancestry | P2 CRB | pending |
| 5 | dc-cr-gnome-heritage-umbral | Gnome Heritage: Umbral | P2 CRB | pending |
| 6 | dc-cr-gnome-heritage-sensate | Gnome Heritage: Sensate | P2 CRB | pending |
| 7 | dc-cr-gnome-heritage-chameleon | Gnome Heritage: Chameleon | P2 CRB | pending |
| 8 | dc-cr-fey-fellowship | Fey Fellowship (Gnome Ancestry Feat) | P2 CRB | pending |
| 9 | dc-cr-tactical-grid | Tactical Grid | P2 CRB | pending |
| 10 | dc-cr-rune-system | Rune System | P2 CRB | pending |

## Design notes
- Features 1-3 complete the 12-class CRB core roster (barbarian/fighter/rogue/alchemist deferred from release-b are P1 for release-d)
- Features 4-8 are the gnome ancestry cluster — shipped together for cohesive ancestry completeness
- Features 9-10 are foundational infrastructure for encounter/equipment content

## Deferred to release-d
- dc-cr-class-barbarian, dc-cr-class-fighter, dc-cr-class-rogue, dc-cr-hazards, dc-cr-encounter-creature-xp-table, dc-cr-class-alchemist (deferred from release-b, P1)
- APG new classes: dc-apg-class-investigator, dc-apg-class-oracle, dc-apg-class-swashbuckler (P2)
- dc-cr-gnome-heritage-fey-touched, dc-cr-gnome-heritage-wellspring, dc-cr-first-world-magic (P3 gnome cluster remainder)

## Auto-close trigger
Release-c activated with exactly 10 in_progress features — at or above the ≥10 threshold.
Auto-close (`release-close-now`) fires when this threshold is met.
