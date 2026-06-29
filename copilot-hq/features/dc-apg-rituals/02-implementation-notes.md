# Implementation Notes — APG Rituals

**Feature:** dc-apg-rituals  
**Commit:** `3abb9cc8f`  
**File changed:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php`

---

## What was implemented

### RITUALS constant (new)
Added at line 1631, after `FOCUS_POOLS` and before `GENERAL_FEATS`.

Total entries: **26 rituals** — 13 CRB baseline + 13 APG new rituals.

#### Data structure per entry
| Field | Type | Notes |
|-------|------|-------|
| `id` | string | kebab-case identifier |
| `name` | string | Display name |
| `level` | int | Ritual level (1–10) |
| `book_id` | string | `'crb'` or `'apg'` — composite key guard (AC Edge-2) |
| `rarity` | string | `'common'`, `'uncommon'`, `'rare'` |
| `traits` | array | Trait tags |
| `casting_time` | string | e.g. `'1 day'`, `'1 hour'` |
| `cost` | string | Material cost description including GP amount |
| `primary_check` | assoc array | `['skill' => ..., 'min_proficiency' => ...]` |
| `secondary_casters` | int | **0** for primary-only rituals (AC Edge-1) |
| `secondary_checks` | array | Array of `['skill', 'min_proficiency']` objects; empty array when `secondary_casters=0` |
| `targets` | string | Target description |
| `description` | string | Effect description |
| `gm_approval` | bool | Present only on Uncommon/Rare entries with Evil trait or Rare rarity; consumer should gate these |

#### CRB rituals added (13)
sanctify-water (1), create-undead (2), divination (2), heartbond (2), geas (3), atone (4), community-gathering (4), planar-binding (5), call-spirit (5), commune (6), raise-dead (7), teleportation-circle (7), resurrect (10)

#### APG rituals added (13)
| Ritual | Level | Rarity |
|--------|-------|--------|
| Bless the Hearth | 1 | common |
| Fantastic Facade | 4 | uncommon |
| Fey Influence | 4 | uncommon |
| Inveigle | 4 | uncommon |
| Angelic Messenger | 5 | uncommon |
| Elemental Sentinel | 5 | common |
| Primal Call | 5 | uncommon |
| Ravenous Reanimation | 5 | uncommon + gm_approval |
| Establish Stronghold | 6 | common |
| Infuse Companion | 6 | uncommon |
| Create Nexus | 7 | uncommon |
| Subjugate Undead | 7 | uncommon |
| Unspeakable Shadow | 7 | rare + gm_approval |

---

## Design decisions

| Decision | Rationale |
|----------|-----------|
| Flat array (not nested by book_id) | AC specifies APG and CRB appear in the **same** ritual selection UI; a flat array keyed by position is simpler for consumers to iterate and filter. Filtering by book_id is still trivial. |
| `secondary_casters: 0` + empty `secondary_checks: []` | Explicitly encodes the primary-only ritual pattern (AC Edge-1). Consumers should render secondary-caster UI only when `secondary_casters > 0`. |
| `book_id` on every entry | AC Edge-2 specifies that same-named rituals from different sourcebooks must be differentiable. Using `(id + book_id)` as composite key prevents silent collisions. |
| `gm_approval: TRUE` on Ravenous Reanimation + Unspeakable Shadow | Evil trait on an uncommon ritual and Rare rarity are both flags that require GM adjudication before a character may initiate. Other uncommon rituals do NOT carry `gm_approval` — rarity alone does not mandate the gate; GM workflow is at GM discretion for common uncommons. Only Evil+uncommon or Rare rituals trigger it here. |
| `secondary_checks` as array of objects | AC Integration-2: rituals may require multiple secondary casters each with different skill requirements (e.g. community-gathering needs Diplomacy+Performance+Society). Flat array of `['skill','min_proficiency']` objects is the most flexible and directly readable format. |
| Cost as descriptive string (not separate gp int) | Some costs are narrative (e.g. "offerings of the community's choice"). A descriptive string accommodates this without forcing a gp-only parse that would fail on hybrid costs. If the calculator needs a numeric gp figure, it can be added as `cost_gp` in a future pass. |

---

## AC coverage map

| AC item | Status | Evidence |
|---------|--------|----------|
| System-1: ritual system accepts new entries without structural changes | ✅ | RITUALS is a flat constant; new entries can be appended without schema migration |
| Catalog-1: complete stat block per ritual | ✅ | All 26 entries have all 11 required fields |
| Catalog-2: multiple secondary casters pattern | ✅ | `secondary_checks` is an array; community-gathering has 3 different secondary checks |
| Catalog-3: rare/uncommon GM-approval gate | ✅ | `rarity` field on all entries; `gm_approval: TRUE` on rare/evil-uncommon entries |
| Integration-1: primary check stored per-ritual | ✅ | `primary_check['skill']` and `primary_check['min_proficiency']` stored per entry |
| Integration-2: secondary checks as arrays | ✅ | `secondary_checks` is always an array (empty or multi-element) |
| Integration-3: cost tracked per entry | ✅ | `cost` field on all entries |
| Edge-1: 0 secondary casters valid | ✅ | 4 rituals have `secondary_casters: 0` (sanctify-water, divination, commune, bless-the-hearth) |
| Edge-2: name collision differentiated by book_id | ✅ | All entries carry `book_id`; lookup key is `(id, book_id)` |
| Edge-3: rare material cost flagged, not blocking | ✅ | Descriptive cost string + `gm_approval` flag; no hard block in data |

---

## Verification
- PHP lint: `No syntax errors detected`
- `drush cr`: `Cache rebuild complete`
- Git commit: `3abb9cc8f`

## KB references
- None found for ritual implementation pattern in `knowledgebase/`.
- Pattern note: the `secondary_checks` array-of-objects approach follows the same multi-entry array pattern used in `ORACLE_MYSTERIES` curse stages.
