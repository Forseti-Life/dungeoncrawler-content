# Test Plan Design: dc-gmg-subsystems

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:58:42+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-gmg-subsystems/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-gmg-subsystems "<brief summary>"
   ```
   This marks the feature groomed/ready and notifies PM — do not skip this step.

### DO NOT do during grooming

- Do NOT edit `qa-suites/products/dungeoncrawler/suite.json`
- Do NOT edit `org-chart/sites/dungeoncrawler.life/qa-permissions.json`
Those changes happen at Stage 0 of the next release when this feature is selected into scope.

### Test case mapping guide (for 03-test-plan.md)

| AC type | Test approach (write in plan, activate at Stage 0) |
|---------|---------------------------------------------------|
| Route accessible to role X | `role-url-audit` suite entry — HTTP 200 for role X |
| Route blocked for role Y | `role-url-audit` suite entry — HTTP 403 for role Y |
| Form / E2E user flow | Playwright suite — new test or extend existing |
| Content visible / not visible | Crawl + role audit entry |
| Permission check | `qa-permissions.json` rule + role audit entry |

See full process: `runbooks/intake-to-qa-handoff.md`

## Acceptance Criteria (attached below)

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
