# CEO Work Item — Absalom City Buildout (Dungeon System Surface)

## Objective
Build out Absalom as a city-scale dungeon surface with canonical location data, population allocations, and per-landmark inventory visibility suitable for runtime generation and validation flows.

## Scope baseline
- Total planned landmark locations: **34**
- Locations with provided population/detail logs: **34**
- Locations still missing full logs: **0**
- Sum of currently provided actor allocations: **729**
- NPC creation rule: **only create NPC entities for explicitly named NPCs**
- Non-named actor rule: **include only in each location description text (no separate actor entities/fields)**

## Category allocation check
| Category | Planned locations | Detailed locations received | Actor total (detailed only) |
|---|---:|---:|---:|
| Civic, Power, & Political Hubs | 4 | 4 | 87 |
| Noble Estates & Faction Hubs | 4 | 4 | 104 |
| Aristocratic Rest & Social Hubs | 2 | 2 | 39 |
| Enforcement & Security | 4 | 4 | 87 |
| Daily Services & Commerce | 7 | 7 | 113 |
| Hospitality & Lodging | 3 | 3 | 53 |
| Faith, Health, & Knowledge | 4 | 4 | 96 |
| Infrastructure & Production | 6 | 6 | 150 |
| **Total** | **34** | **34** | **729** |

## Execution phases
### Phase 1 — Baseline lock and data completion
- Freeze category/location taxonomy.
- Completed: all infrastructure location logs captured (actors, named NPCs, visible inventory).

### Phase 2 — Canonical city landmark contract prep
- Standardize per-location payload shape (location id, category, actor count, named NPC list, visible item inventory list).
- Ensure non-named/ambient actor population is represented only in location description text.
- Assign deterministic canonical IDs for all 34 landmarks.

### Phase 3 — Content ingestion implementation
- Build import-ready records for Absalom landmarks into the canonical content path.
- Wire the city landmark surface into dungeon/city discovery and encounter references.
- Canonical structure payload source: `04-absalom-canonical-structures.json`.

### Phase 4 — Validation and closeout
- Run targeted contract/validator gates for the new landmark surfaces.
- Produce closeout evidence and ship commits/push.
