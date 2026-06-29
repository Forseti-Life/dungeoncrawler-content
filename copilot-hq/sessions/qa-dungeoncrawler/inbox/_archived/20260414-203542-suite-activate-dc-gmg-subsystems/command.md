# Suite Activation: dc-gmg-subsystems

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T20:35:42+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-gmg-subsystems"`**  
   This links the test to the living requirements doc at `features/dc-gmg-subsystems/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-gmg-subsystems-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-gmg-subsystems",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-gmg-subsystems"`**  
   Example:
   ```json
   {
     "id": "dc-gmg-subsystems-<route-slug>",
     "feature_id": "dc-gmg-subsystems",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-gmg-subsystems",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-gmg-subsystems

## Coverage summary
- AC items: ~45 (subsystem architecture, Influence, Research, Infiltration, Chases, Vehicles, Hexploration, Duels, variant rules: Free Archetype, Ancestry Paragon, ABP, Proficiency Without Level)
- Test cases: 18 (TC-SUB-01–18)
- Suites: playwright (encounter, downtime, character creation)
- Security: AC exemption granted (no new routes)

---

## TC-SUB-01 — Subsystem architecture: setup/round/resolution states tracked independently
- Description: Active subsystems tracked independently from main encounter; can be triggered during exploration/social/encounter mode; results feed back to narrative state (XP, items, story beats)
- Suite: playwright/encounter
- Expected: subsystem panel distinct from encounter tracker; multiple subsystems simultaneously tracked; subsystem results update session state
- AC: Architecture-1–4

## TC-SUB-02 — Influence Subsystem: NPC disposition, influence points, thresholds
- Description: Each NPC has preferred/opposed skills; success +influence; crit success +double; failure +none; crit failure –influence; thresholds unlock outcomes; exceeding opposition triggers hostility
- Suite: playwright/encounter
- Expected: influence point changes per outcome tier; threshold detection fires outcomes; hostility triggered at opposition limit
- AC: Influence-1–5

## TC-SUB-03 — Research Subsystem: library entries, Research Points, tiers, time limit
- Description: Research checks vs. library DC generate Research Points; cumulative points unlock information tiers; time limit in rounds/blocks; per-entry cap prevents grinding
- Suite: playwright/downtime
- Expected: each check generates RP; tier unlock at threshold; time-based termination; cap prevents overflow RP
- AC: Research-1–5

## TC-SUB-04 — Infiltration Subsystem: Awareness score, complications, preparation points
- Description: Failed checks raise Awareness; thresholds trigger complications; critical failures trigger immediate complications; preparation points reduce initial Awareness
- Suite: playwright/encounter
- Expected: failed check → awareness += defined amount; threshold crossing triggers complication; crit fail bypasses threshold; prep points deducted from starting awareness
- AC: Infiltration-1–5

## TC-SUB-05 — Chases Subsystem: stages, skill option pools, simultaneous advancement
- Description: Chase = series of stages; 2–3 skill options per stage; each side chooses and attempts independently; obstacle distance tracked; chase ends when one side wins/escapes
- Suite: playwright/encounter
- Expected: stage skill pools selectable; both sides resolve independently (not alternating); distance tracked numerically; end condition fires correctly
- AC: Chases-1–5, Integration-4

## TC-SUB-06 — Vehicles Subsystem: stat blocks, piloting, passengers, collision
- Description: Vehicles have stat blocks (Piloting DC, Speed, Maneuverability, HP, Hardness, AC, Saves); pilot maneuvers via Piloting check; passengers act independently; collision deals damage by size/speed; HP loss degrades Speed/Maneuverability at thresholds
- Suite: playwright/encounter
- Expected: vehicle stat block fields all present; failed piloting → damage/control loss; passenger actions work; collision damage formula applied; threshold degradation
- AC: Vehicles-1–5

## TC-SUB-07 — Hexploration Subsystem: hex discovery status, terrain effects, Reconnoiter
- Description: Hexes = unknown/revealed/explored; enter hex → revealed; full exploration requires Reconnoiter; terrain types affect travel time and actions; party exploration actions per day determined by terrain + speed
- Suite: playwright/downtime
- Expected: hex status transitions correctly; Reconnoiter changes status to explored; terrain modifier on daily exploration actions
- AC: Hexploration-1–5

## TC-SUB-08 — Duels Subsystem: formal/informal, win conditions, honor tracking
- Description: Duel structured as formal/informal; win conditions per type; may use combat or opposed skill checks; deviating from terms applies reputation/honor penalty
- Suite: playwright/encounter
- Expected: duel type selection; win condition fires on correct trigger; honor penalty applied on rule deviation
- AC: Duels-1–3

## TC-SUB-09 — Variant rules: feature-flagged per campaign, compatibility check
- Description: Variant rules enabled/disabled at campaign setup; compatibility check alerts on conflict with active assumptions; each rule has documented precedence level
- Suite: playwright/character-creation
- Expected: variant toggles in campaign settings; conflict alert shows when incompatible rules combined; precedence level displayed
- AC: VariantRules-1–3

## TC-SUB-10 — Free Archetype Variant: bonus class feat at every even level for archetypes only
- Description: Extra class feat at each even level exclusively for archetype feats; does not stack with normal class feat; dedicated/regular feats remain separate
- Suite: playwright/character-creation
- Expected: even levels show archetype-only bonus slot; normal class feat unchanged; archetype slot cannot accept non-archetype feats
- AC: FreeArchetype-1–3

## TC-SUB-11 — Ancestry Paragon Variant: ancestry feats at every even level
- Description: Ancestry feats granted at every even level (double normal rate); ancestry-feats only (not general or class feats)
- Suite: playwright/character-creation
- Expected: even levels show extra ancestry feat slot; slot filters ancestry feats only; total ancestry feat count doubles
- AC: AncestryParagon-1–2

## TC-SUB-12 — Automatic Bonus Progression (ABP): removes rune item bonus dependency
- Description: ABP removes item bonus from fundamental runes; grants attack/damage/AC/saves/perception bonuses by level via ABP table; magic items still exist but don't stack with ABP values
- Suite: playwright/character-creation
- Expected: when ABP active, rune item bonuses suppressed; ABP bonuses applied from table; no stacking
- AC: ABP-1–3

## TC-SUB-13 — ABP migration: prior rune bonuses replaced not stacked
- Description: Characters migrating to ABP get migration warning; prior rune effects replaced (not additive)
- Suite: playwright/character-creation
- Expected: ABP enable on existing character → migration warning shown; rune bonuses removed; ABP table bonuses applied
- AC: ABP-4, Edge-3

## TC-SUB-14 — ABP table is lookup-based and configurable per campaign
- Description: ABP bonus values loaded from table (not hardcoded); configurable per campaign
- Suite: playwright/character-creation
- Expected: ABP values match published table; GM can modify per campaign without code change
- AC: Integration-3

## TC-SUB-15 — Proficiency Without Level: fixed rank-based proficiency bonus
- Description: Proficiency bonus = fixed (Untrained 0, Trained 2, Expert 4, Master 6, Legendary 8); character level not added to checks/DCs/saves
- Suite: playwright/character-creation
- Expected: check modifiers recalculated without level component; each rank has correct fixed bonus
- AC: ProfWithoutLevel-1–2

## TC-SUB-16 — Proficiency Without Level: NPC DCs also updated
- Description: Creature DCs and ACs also strip level component (critical for challenge parity)
- Suite: playwright/encounter
- Expected: when ProfWithoutLevel active, NPC DCs recalculated without level; GM warned if NPC data not migrated
- AC: Edge-4

## TC-SUB-17 — Two simultaneous subsystems tracked independently
- Description: Two active subsystems (e.g., Influence + Research) tracked without interaction unless explicitly defined
- Suite: playwright/encounter
- Expected: both subsystem panels visible; each tracks state independently; no bleed-over between subsystem states
- AC: Edge-1

## TC-SUB-18 — Free Archetype: pre-existing feat in slot unaffected by variant activation
- Description: If a character already has an archetype feat from a class feature, the free archetype slot is not removed or overridden
- Suite: playwright/character-creation
- Expected: activating Free Archetype variant on existing character preserves existing feat; free slot appears for future even levels
- AC: Edge-2

### Acceptance criteria (reference)

# Acceptance Criteria: GMG Subsystems and Variant Rules

## Feature: dc-gmg-subsystems
## Source: PF2E Game Master's Guide, Chapters 3 & 4

---

## Subsystem Framework (ch03 and ch04 Baseline Requirements)

- [ ] GM tooling exposes configurable policies for adjudication, pacing, and scenario construction
- [ ] Subsystem framework supports pluggable mechanics with explicit setup, turn flow, and resolution states
- [ ] Variant rules are feature-flagged with compatibility checks against baseline campaign assumptions
- [ ] Encounter/adventure/map planning artifacts preserve traceability between scene intent and mechanics

---

## Subsystem Architecture

- [ ] Subsystems are self-contained rule modules with: setup phase, round/phase progression, resolution state, and outcome effects
- [ ] Each active subsystem tracked independently from the main encounter state
- [ ] Subsystems can be triggered during exploration, social, or encounter mode without terminating the current mode
- [ ] Subsystem results feed back into the main narrative state (XP, items, story beats)

### Influence Subsystem
- [ ] Tracks individual NPC disposition via secret influence points
- [ ] Each NPC has a set of preferred skills and opposed skills for influence checks
- [ ] Successful interaction adds influence; critical success adds double; failure adds none; critical failure adds negative influence
- [ ] Thresholds: reaching certain influence totals unlocks favorable outcomes per NPC
- [ ] Penalty tracking: NPCs have "resistance" limits; exceeding opposition triggers NPC hostility or suspicion

### Research Subsystem
- [ ] Research library modeled with entries per topic/subject
- [ ] Each research check (appropriate skill, library-defined DC) generates Research Points
- [ ] Cumulative Research Points unlock information tiers
- [ ] Time limit: research constrained to available rounds/time blocks
- [ ] Research points per library entry capped (prevents infinite grinding)

### Infiltration Subsystem
- [ ] Tracks the group's "infiltration" state as a combined Awareness score
- [ ] Each failed check raises Awareness by a defined amount
- [ ] Awareness thresholds trigger complications (suspicion, detection, lockdown)
- [ ] Preparation points earned before the infiltration reduce Awareness at start
- [ ] Critical failures on checks may trigger immediate complications without Awareness threshold

### Chases Subsystem
- [ ] Chase structured as a series of Stages; each side advances by succeeding at checks
- [ ] Checks are drawn from a pool of options per stage (usually 2–3 skill options)
- [ ] Each side chooses which option to attempt each round; choice is independent
- [ ] Catching up/falling behind tracked numerically (obstacle distances)
- [ ] Chase ends when one side reaches the goal or the other side is caught/escapes

### Vehicles Subsystem
- [ ] Vehicles have stat blocks: Piloting check skill/DC, Speed, Maneuverability, HP, Hardness, AC, Save values
- [ ] Pilot action: character uses Piloting skill to maneuver; failure/crit failure causes damage or loss of control
- [ ] Passengers can take actions independently of vehicle movement
- [ ] Collision rules: vehicles deal damage based on their size and Speed when hitting creatures/other vehicles
- [ ] Vehicle HP damage: reduces Speed and Maneuverability at thresholds

### Hexploration Subsystem
- [ ] Overworld map divided into hexagonal cells (each hex = defined area, typically 6 or 12 miles)
- [ ] Each hex has a discovery status: unknown, revealed, explored
- [ ] Exploration activities per day: party earns a number of exploration actions based on terrain and travel speed
- [ ] Hex discovery: entering a hex reveals it; full exploration requires a Reconnoiter activity
- [ ] Terrain types: affects travel time and available actions (difficult, greater difficult, hazardous)

### Duels Subsystem
- [ ] Duel structured as formal or informal agreement; each type has win conditions
- [ ] Duels may use combat rules directly or be resolved via opposed skill checks (for social/honor duels)
- [ ] Honor tracking: deviating from duel terms applies reputation/honor penalties

---

## Variant Rules (ch04)

- [ ] Variant rules feature-flagged per campaign (enabled/disabled at campaign setup)
- [ ] Compatibility check alerts when a variant rule conflicts with active campaign assumptions
- [ ] Each variant rule has a documented precedence level (overrides vs. supplements core rules)

### Free Archetype Variant
- [ ] Characters gain an additional class feat at every even level exclusively for archetype feats
- [ ] Free archetype bonus does not stack with the normal class feat for that level
- [ ] Dedicated feats and regular class feats remain separate (cannot use free archetype slot for normal class feats)

### Ancestry Paragon Variant
- [ ] Characters gain ancestry feats at every even level (double normal rate)
- [ ] Ancestry feats obtained via this variant must be ancestry feats (not general or class feats)

### Automatic Bonus Progression (ABP)
- [ ] Removes item-bonus dependency on fundamental runes
- [ ] Grants automatic attack/damage/AC/saves/perception bonuses by level (ABP table)
- [ ] Magic items still exist but do not provide item bonuses that would stack with ABP values
- [ ] Existing characters migrating to ABP: prior rune bonuses replaced (not stacked)

### Proficiency Without Level Variant
- [ ] Proficiency bonus = fixed value by rank (Untrained 0, Trained 2, Expert 4, Master 6, Legendary 8) — does not add character level
- [ ] All check modifiers, DCs, and saving throws recalculated using this formula
- [ ] Creature DCs and ACs similarly strip level component for consistent challenge parity

---

## Integration Notes (ch03/ch04)

- [ ] Integration points from GMG ch03/ch04 map to existing core rules without duplicate semantics
- [ ] Conflicts between chapter-specific and core rules resolved through explicit precedence notes
- [ ] Subsystems do not replace standard skill check rules — they add structured context around them

---

## Integration Checks

- [ ] Active subsystems shown in a secondary UI panel; main encounter tracker remains primary
- [ ] Variant rule toggles accessible at campaign creation and in campaign settings
- [ ] ABP table values are lookup-based (not hardcoded) and configurable per campaign
- [ ] Chase obstacles correctly handle both sides advancing simultaneously (not alternating)

## Edge Cases

- [ ] Two subsystems active simultaneously: both tracked independently; no interaction unless explicitly defined
- [ ] Free Archetype: if a character already has a feat in the free archetype slot from a class feature, the free archetype slot remains unaffected
- [ ] ABP migration: characters with existing item bonuses get a migration warning; prior rune effects noted
- [ ] Proficiency Without Level: NPC DCs also updated (critical — half-value application would break difficulty)
- Agent: qa-dungeoncrawler
- Status: pending
