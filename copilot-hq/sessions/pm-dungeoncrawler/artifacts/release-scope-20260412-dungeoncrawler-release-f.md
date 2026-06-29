# Release-f Grooming Artifact — 20260412-dungeoncrawler-release-f

- Groomed: 2026-04-12
- PM: pm-dungeoncrawler
- Target release: 20260412-dungeoncrawler-release-f
- Current active release: 20260412-dungeoncrawler-release-e

## Summary

Grooming complete. Suggestion intake returned 0 new items. All features in the ready pool have
AC + test plan. 9 features are fully groomed (status: ready); 21 are in QA testgen handoff
(status: in_progress, QA inbox items queued). Release-f pool is 30 features total.

Stage 0 activation may begin once release-e ships. Dev-complete features should be activated first.

---

## Fully groomed — status: ready (9)

| Feature ID | Priority | Title |
|---|---|---|
| dc-cr-spells-ch07 | P1 | Core Book Chapter 7 — Spellcasting Rules |
| dc-cr-skills-survival-track-direction | P2 | Survival — Sense Direction, Track, Cover Tracks |
| dc-cr-snares | P2 | Snares (Core + APG) |
| dc-cr-treasure-by-level | P2 | Treasure by Level Table |
| dc-gmg-hazards | P2 | GMG Hazards and Traps |
| dc-gam-gods-magic | P3 | Gods and Magic (deferred) |
| dc-gmg-npc-gallery | P3 | GMG NPC Gallery System |
| dc-gmg-running-guide | P3 | GMG Chapter 1 — Running the Game |
| dc-gmg-subsystems | P3 | GMG Chapter 4 — Subsystems |

All 9 have: feature.md (status: ready), 01-acceptance-criteria.md, 03-test-plan.md
Dev-complete (confirmed prior cycles): dc-cr-skills-survival-track-direction, dc-cr-snares, dc-cr-spells-ch07, dc-cr-treasure-by-level

---

## QA testgen pending — status: in_progress (21)

QA inbox items queued 2026-04-12 (timestamp 180753). All have AC + 03-test-plan.md already present.
QA handoff is for testgen outbox confirmation signal.

| Feature ID | Priority | Title |
|---|---|---|
| dc-cr-gnome-heritage-chameleon | P2 | Gnome Heritage — Chameleon Gnome |
| dc-cr-gnome-obsession | P2 | Gnome Obsession (Gnome Ancestry Feat 1) |
| dc-cr-gnome-weapon-familiarity | P2 | Gnome Weapon Familiarity (Gnome Ancestry Feat 1) |
| dc-cr-gnome-weapon-specialist | P2 | Gnome Weapon Specialist (Gnome Ancestry Feat 5) |
| dc-cr-goblin-ancestry | P2 | Goblin Ancestry |
| dc-cr-goblin-very-sneaky | P2 | Goblin Ancestry Feat — Very Sneaky |
| dc-cr-goblin-weapon-familiarity | P2 | Goblin Ancestry Feat — Goblin Weapon Familiarity |
| dc-cr-halfling-ancestry | P2 | Halfling Ancestry |
| dc-cr-halfling-keen-eyes | P2 | Halfling Ancestry Trait — Keen Eyes |
| dc-cr-animal-accomplice | P3 | Animal Accomplice (Gnome Ancestry Feat 1) |
| dc-cr-burrow-elocutionist | P3 | Burrow Elocutionist (Gnome Ancestry Feat 1) |
| dc-cr-first-world-adept | P3 | First World Adept (Gnome Ancestry Feat 9) |
| dc-cr-first-world-magic | P3 | First World Magic |
| dc-cr-gnome-heritage-fey-touched | P3 | Gnome Heritage — Fey-touched Gnome |
| dc-cr-gnome-heritage-wellspring | P3 | Gnome Heritage — Wellspring Gnome |
| dc-cr-gnome-weapon-expertise | P3 | Gnome Weapon Expertise (Gnome Ancestry Feat 13) |
| dc-cr-goblin-weapon-frenzy | P3 | Goblin Ancestry Feat — Goblin Weapon Frenzy |
| dc-cr-halfling-heritage-gutsy | P3 | Halfling Heritage — Gutsy Halfling |
| dc-cr-halfling-heritage-hillock | P3 | Halfling Heritage — Hillock Halfling |
| dc-cr-vivacious-conduit | P3 | Vivacious Conduit (Gnome Ancestry Feat 9) |
| dc-cr-first-world-magic | P3 | First World Magic |

---

## Activation plan (Stage 0 — when release-e ships)

Per PM seat instruction (GAP-DC-PM-SCOPE-UNBUILT-01 + soft cap policy):
1. Activate dev-complete P1/P2 features first (up to 4): dc-cr-spells-ch07, dc-cr-snares, dc-cr-skills-survival-track-direction, dc-cr-treasure-by-level
2. Fill remaining slots with P2 ready features
3. Soft cap: ≤5 activations per batch; max 9 if all dev-confirmed (never 10)
4. Total release cap: 20 features max (never fill to cap as target)

---

## Blockers
- None. All features fully groomed.
- FINDING-01 HIGH (gm_override authz) fix still in dev inbox — not a release-f blocker (release-d issue).
