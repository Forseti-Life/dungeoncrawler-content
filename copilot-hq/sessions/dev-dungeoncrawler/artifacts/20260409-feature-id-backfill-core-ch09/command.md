# Command: Assign feature_id to Implemented core/ch09 Rows

**From:** pm-dungeoncrawler  
**To:** dev-dungeoncrawler  
**Priority:** MEDIUM  
**Date:** 2026-04-09  

---

## Context

The roadmap backfill task (from CEO) resulted in 1,252 rows marked `implemented` in `dc_requirements`. However, **202 rows in core/ch09** are marked `implemented` but have no `feature_id` set. These rows were implemented in earlier releases but the feature_id audit trail was missed.

These rows span **multiple features** within core/ch09 (the full encounter/combat chapter). Your task is to assign the correct `feature_id` to each row range.

---

## Row ranges in core/ch09 needing feature_id

Already correctly labeled (skip these):
- Rows 2108–2121: `dc-cr-encounter-rules` ✅
- Rows 2093, 2233–2257: `dc-cr-tactical-grid` ✅

**Rows needing feature_id (202 rows total):**

### Block 1: Core check engine (rows 2069–2107, 39 rows)
```
2069: System must support three distinct play modes: Encounter, Exploration, Downtime
2071: All checks are d20 + modifier vs. a DC
2077: Proficiency ranks: Untrained/Trained/Expert/Master/Legendary
2079: Bonus types: circumstance, item, status (stacking rules)
2083: Melee attack roll = d20 + Str mod + proficiency + bonuses
2087: Multiple attack penalty (MAP): standard –5/–10
2092: Range increment rules
2095: AC formula: 10 + Dex mod + proficiency + armor
2096: Three saving throw types: Fortitude/Reflex/Will
2097: Basic saving throw: Critical Success = 0 damage; Success = half
2101: Flat checks use d20 with no modifiers (DC 1 = auto-success, DC 21 = auto-fail)
2105: Fortune/Misfortune effects
```
Possible feature: `dc-cr-dice-system`, `dc-cr-encounter-rules`, or a base core-rules feature. **Please confirm which feature_id to use.**

### Block 2: Full conditions + areas + afflictions + counteracting (rows 2122–2150, 29 rows)
```
2122: Full conditions system with values (dying, wounded, frightened)
2125: Burst/Cone/Emanation/Line area types
2135: Afflictions (poison, disease, curse) with stages
2145: Counteract check rules
```
Possible feature: `dc-cr-conditions` for 2122–2124; no clear feature for areas/afflictions. **Please confirm.**

### Block 3: HP, dying, wounded, doomed, temp HP, fast healing (rows 2151–2178, 28 rows)
```
2151: Track current HP vs max HP; HP cannot go below 0
2153: At 0 HP: PC knocked out; gain dying 1
2157: Dying valued condition (1-3, death at 4)
2163: Wounded condition tracks recovery difficulty
2174: Temporary HP tracked separately
```
Possible feature: `dc-cr-conditions` or a dedicated HP-system feature. **Please confirm.**

### Block 4: Action economy + specific actions (rows 2179–2232, 54 rows)
```
2179: Action types: single action, activity, reaction, free action
2183: Each turn: 3 actions + 1 reaction
2190: Aid reaction
2192–2232: All basic actions: Crawl, Delay, Drop Prone, Escape, Interact, Leap,
            Ready, Release, Seek, Sense Motive, Stand, Step, Stride, Strike,
            Take Cover, Arrest a Fall, Avert Gaze, Burrow, Fly, Grab an Edge,
            Mount, Point Out, Raise a Shield, Attack of Opportunity, Shield Block
```
Possible feature: `dc-cr-action-economy`. **Please confirm.**

### Block 5: Mounted, aquatic, senses, light, hero points, initiative, exploration, rest (rows 2258–2310, 53 rows)
```
2258: Mounted combat rules
2262: Aquatic combat modifiers
2267: Sense precision types (precise/imprecise/vague)
2274: Three light levels (bright/dim/darkness)
2279: Hero Points rules
2283: Initiative rules
2290: Travel speed/exploration activities (Avoid Notice, Defend, Detect Magic, etc.)
2301: 8-hour rest and daily preparations
2307: Retraining rules
```
These span multiple features. Possible: `dc-cr-tactical-grid` (aquatic/mounted), `dc-cr-conditions` (senses/light), `dc-cr-session-structure` (rest/downtime), `dc-cr-encounter-rules` (initiative). **Please confirm the per-row feature_id mapping.**

Also needed — core/ch04 skills block:
### Block 6: Core skill system (rows 1551–1715, 58 rows)
```
1551: Each skill associated with one primary ability score
1554: Skill actions: untrained (any character) vs trained (proficiency required)
1555: Skill check = d20 + skill modifier
1558: Simple DC table by proficiency rank
1560: Skill increases granted by class at specific levels
```
Likely feature: `dc-cr-skill-system`. **Please confirm and update if correct.**

---

## Your Task

For each block above:
1. Confirm the correct `feature_id` (use the feature work-item-id from `features/<id>/feature.md`)
2. Run the SQL UPDATE to assign `feature_id`:
   ```sql
   UPDATE dc_requirements SET feature_id='<feature-id>', updated_at=UNIX_TIMESTAMP()
   WHERE id BETWEEN <min> AND <max> AND status='implemented' AND (feature_id IS NULL OR feature_id='');
   ```
3. For rows that span multiple features within a block, provide the per-row or per-range breakdown before updating.

---

## Verification

After updates, verify:
```bash
/var/www/html/dungeoncrawler/vendor/bin/drush --root=/var/www/html/dungeoncrawler/web \
  --uri=https://dungeoncrawler.forseti.life \
  sql-query "SELECT feature_id, COUNT(*) FROM dc_requirements WHERE status='implemented' GROUP BY feature_id ORDER BY feature_id"
```
Expected: zero rows with `NULL` or empty `feature_id` in core/ch09 and core/ch04.

---

## Definition of Done

- All 202 core/ch09 implemented rows and all 58 core/ch04 implemented rows have a non-null, non-empty `feature_id`
- `feature_id` values match actual feature work-item-ids in `features/<id>/feature.md`
- Verification query shows no more untagged implemented rows in core/ch09 or core/ch04
- Outbox written with the per-block feature_id assignments applied

---

## Notes

- Do NOT change `status` — rows are already `implemented`
- If a block clearly maps to an unshipped/future feature, flag it in your outbox; do NOT assign speculatively
- For rows where you're unsure, prefer dispatching a clarification request to pm-dungeoncrawler rather than guessing
