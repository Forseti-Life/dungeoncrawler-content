# Test Plan: dc-cr-xp-award-system

## Feature
XP award and level advancement system from PF2E Core Rulebook Chapter 10.

## KB references
- Cross-reference: dc-cr-encounter-creature-xp-table (TC-XPT series) — creature XP uses Table 10–2; this feature consumes that output.
- Cross-reference: dc-cr-hazards (TC-HAZ-21–23) — hazard XP uses Table 10–14; this feature consumes that output.

## Dependencies
- dc-cr-encounter-creature-xp-table (required — creature XP table lookup)

## Suite mapping
All TCs target the `dungeoncrawler-content` suite (game-mechanic data/logic).
No new routes or auth surfaces — Security AC exemption confirmed.

---

## Test Cases

### TC-XPA-01: XP threshold — standard level-up at 1,000 XP
- **Description:** A character with XP ≥ 1,000 gains a level; XP is reduced by 1,000 (not reset to 0) and accumulation continues.
- **Suite:** dungeoncrawler-content
- **Expected:** `award_xp(current_xp=950, amount=100)` → `{new_xp: 50, level_gained: true}` (1050 - 1000 = 50).
- **Roles:** N/A

### TC-XPA-02: XP carryover — excess XP preserved on level-up
- **Description:** XP beyond the 1,000 threshold carries over; XP is not reset to 0 after leveling.
- **Suite:** dungeoncrawler-content
- **Expected:** `award_xp(current_xp=980, amount=200)` → `{new_xp: 180, level_gained: true}` (1180 - 1000 = 180, not 0).
- **Roles:** N/A

### TC-XPA-03: XP threshold — standard mode 1,000 XP
- **Description:** In Standard advancement mode, the level-up threshold is exactly 1,000 XP.
- **Suite:** dungeoncrawler-content
- **Expected:** `advancement_threshold(mode="standard")` → `1000`.
- **Roles:** N/A

### TC-XPA-04: Advancement speed — Fast mode (800 XP)
- **Description:** In Fast advancement mode, the level-up threshold is 800 XP.
- **Suite:** dungeoncrawler-content
- **Expected:** `advancement_threshold(mode="fast")` → `800`.
- **Roles:** N/A

### TC-XPA-05: Advancement speed — Slow mode (1,200 XP)
- **Description:** In Slow advancement mode, the level-up threshold is 1,200 XP.
- **Suite:** dungeoncrawler-content
- **Expected:** `advancement_threshold(mode="slow")` → `1200`.
- **Roles:** N/A

### TC-XPA-06: Advancement speed — mode is configurable
- **Description:** The advancement mode can be switched between fast/standard/slow; threshold changes to match.
- **Suite:** dungeoncrawler-content
- **Expected:** Setting mode to each variant produces the correct threshold; switching modes updates the threshold used for subsequent awards.
- **Roles:** N/A

### TC-XPA-07: Party-wide XP award — all members receive equal XP
- **Description:** Any XP award from an encounter or accomplishment is distributed equally to all party members.
- **Suite:** dungeoncrawler-content
- **Expected:** `award_encounter_xp(party_size=4, xp=40)` → each of 4 PCs receives 10 XP.
- **Roles:** N/A
- **Note to PM:** AC states "all party members receive the same XP." This confirms flat equal distribution (not divided). Automation asserts all members receive the full amount, not a split.

### TC-XPA-08: Trivial encounter — 0 XP returned
- **Description:** Trivial encounters return 0 XP by default.
- **Suite:** dungeoncrawler-content
- **Expected:** `encounter_xp(threat_tier="trivial")` → `0`.
- **Roles:** N/A

### TC-XPA-09: Trivial encounter — minor accomplishment XP may override
- **Description:** GM may award minor accomplishment XP for a trivial encounter; the system permits this override without error.
- **Suite:** dungeoncrawler-content
- **Expected:** `award_accomplishment_xp(type="minor", encounter_tier="trivial")` succeeds and returns the minor accomplishment XP amount.
- **Roles:** N/A

### TC-XPA-10: Story-based leveling — XP not tracked
- **Description:** When story-based leveling mode is active, XP is not tracked; level advancement is milestone-based.
- **Suite:** dungeoncrawler-content
- **Expected:** `award_xp(mode="story")` → `{xp_tracked: false}`; no XP stored or accumulated.
- **Roles:** N/A

### TC-XPA-11: Accomplishment XP table — minor/moderate/major tiers present
- **Description:** Accomplishment XP table has entries for minor, moderate, and major tiers with defined XP values.
- **Suite:** dungeoncrawler-content
- **Expected:** Three accomplishment records returned; each has a non-null `xp` field; `xp_minor < xp_moderate < xp_major`.
- **Roles:** N/A
- **Note to PM:** Exact XP values for minor/moderate/major accomplishments are not specified in AC. BA must extract from PF2E CRB to populate the table; automation asserts structure and ordering until exact values are confirmed.

### TC-XPA-12: Hero Point flag — moderate accomplishment
- **Description:** A moderate accomplishment awards XP and flags that an instrumental PC should receive a Hero Point.
- **Suite:** dungeoncrawler-content
- **Expected:** `award_accomplishment_xp(type="moderate")` → `{xp: <moderate_amount>, hero_point_flag: true}`.
- **Roles:** N/A

### TC-XPA-13: Hero Point flag — major accomplishment
- **Description:** A major accomplishment awards XP and flags that an instrumental PC should receive a Hero Point.
- **Suite:** dungeoncrawler-content
- **Expected:** `award_accomplishment_xp(type="major")` → `{xp: <major_amount>, hero_point_flag: true}`.
- **Roles:** N/A

### TC-XPA-14: Hero Point flag — minor accomplishment does NOT flag
- **Description:** A minor accomplishment awards XP but does not flag a Hero Point award.
- **Suite:** dungeoncrawler-content
- **Expected:** `award_accomplishment_xp(type="minor")` → `{xp: <minor_amount>, hero_point_flag: false}`.
- **Roles:** N/A

### TC-XPA-15: Creature XP source — uses Table 10–2 (dc-cr-encounter-creature-xp-table)
- **Description:** Creature XP lookups route through the encounter creature XP table (Table 10–2), not a separate table.
- **Suite:** dungeoncrawler-content
- **Expected:** `creature_xp_source()` → `"dc-cr-encounter-creature-xp-table"` (or equivalent table identifier); hazard XP source returns a different identifier.
- **Roles:** N/A

### TC-XPA-16: Hazard XP source — uses Table 10–14 (separate from creature XP)
- **Description:** Hazard XP lookups use Table 10–14, which is distinct from the creature XP table.
- **Suite:** dungeoncrawler-content
- **Expected:** `hazard_xp_source()` → `"table-10-14"` (distinct from creature XP source); requesting hazard XP does not invoke the creature XP table.
- **Roles:** N/A

### TC-XPA-17 (Edge): PCs behind party level — double XP
- **Description:** A PC who is behind the party's level receives double XP until they catch up.
- **Suite:** dungeoncrawler-content
- **Expected:** `award_xp(pc_level=3, party_level=5, base_xp=40)` → PC receives `80`; once PC level equals party level, returns to `40`.
- **Roles:** N/A
- **Note to PM:** AC says "PCs behind party level" — no threshold defined for how many levels behind triggers this. Assuming any level difference qualifies. Confirm if there is a minimum gap (e.g., must be 2+ levels behind).

### TC-XPA-18 (Failure): Story-based leveling — XP not tracked is not an error
- **Description:** In story-based leveling mode, calling the XP award function does not raise an error; it silently no-ops or returns a "not tracked" status.
- **Suite:** dungeoncrawler-content
- **Expected:** `award_xp(mode="story", amount=40)` → `{success: true, xp_tracked: false, xp_awarded: 0}` (not an exception/error).
- **Roles:** N/A

### TC-XPA-19 (Failure): Trivial encounter — 0 XP is not an error state
- **Description:** Receiving 0 XP from a trivial encounter is a valid outcome, not an error or null response.
- **Suite:** dungeoncrawler-content
- **Expected:** `encounter_xp(threat_tier="trivial")` → `{xp: 0, error: null}` (returns 0, not null or error).
- **Roles:** N/A

---

## Notes to PM

1. **Accomplishment XP values (TC-XPA-11):** Minor/moderate/major XP amounts are not specified in AC. BA must extract from PF2E CRB (likely Table 10–8 or equivalent). Automation value-correctness assertions blocked until confirmed.

2. **Double-XP behind-level threshold (TC-XPA-17):** AC says "PCs behind party level" without specifying whether any level gap qualifies or a minimum gap (e.g., 2+ levels) is required. PM clarification needed for precise automation assertion.

3. **Hero Point assignment (TC-XPA-12, TC-XPA-13):** The flag indicates an instrumental PC should receive a Hero Point, but the assignment of which PC is flagged is a GM decision. Automation asserts the flag is raised; GM selection is not automated.

4. **Dependency chain:** This feature is the upstream dependency for dc-cr-encounter-creature-xp-table TCs (TC-XPT series). PM should sequence dc-cr-xp-award-system into scope before or alongside dc-cr-encounter-creature-xp-table.

5. **Security AC exemption confirmed:** No new routes or user-facing input surfaces. All TCs are logic/data assertions only.
