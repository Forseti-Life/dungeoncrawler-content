# Suite Activation: dc-cr-skills-stealth-hide-sneak

- Agent: qa-dungeoncrawler
- Status: pending

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-08T13:49:28+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-skills-stealth-hide-sneak"`**  
   This links the test to the living requirements doc at `features/dc-cr-skills-stealth-hide-sneak/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-skills-stealth-hide-sneak-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-skills-stealth-hide-sneak",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-skills-stealth-hide-sneak"`**  
   Example:
   ```json
   {
     "id": "dc-cr-skills-stealth-hide-sneak-<route-slug>",
     "feature_id": "dc-cr-skills-stealth-hide-sneak",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-skills-stealth-hide-sneak",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-skills-stealth-hide-sneak

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Stealth skill — Conceal an Object, Hide, Sneak, Avoid Notice
**KB reference:** None found specific to Stealth. Observed/Hidden/Unnoticed condition states are shared with dc-cr-conditions — TCs that assert persistent visibility states are conditional on dc-cr-conditions being available. Detection pattern (observer Perception DC vs character Stealth) is structurally similar to Command an Animal (NAT) Will DC resolution.
**Dependency note:** All Stealth action mechanics (action cost, gate logic, DC resolution, outcome) are immediately activatable on dc-cr-skill-system. TCs asserting character visibility state (Hidden/Observed/Unnoticed) depend on dc-cr-conditions for state storage and transition. Annotated per TC below.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Stealth business logic: Conceal an Object (Manipulation, Seek gate), Hide (cover gate, multi-observer, visibility transition), Sneak (Hidden gate, half-speed move, end-location gate, multi-observer), Avoid Notice (exploration mode, Unnoticed start) |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing encounter/exploration handler routes only |

---

## Test Cases

### Conceal an Object

### TC-STL-01 — Conceal an Object: 1-action, Manipulation trait, hides carried/worn item
- **Suite:** module-test-suite
- **Description:** Conceal an Object costs 1 action, has the Manipulation trait, and applies to a carried or worn item. It does not apply to items not on the character's person.
- **Expected:** action_cost = 1; trait = manipulation; target_item.carried_or_worn = true → action allowed; target_item.carried_or_worn = false → blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-STL-02 — Conceal an Object Critical Success: item hidden, observers need Seek to find it
- **Suite:** module-test-suite
- **Description:** On Critical Success, the item is hidden. Observers cannot passively detect it — they must actively Seek.
- **Expected:** result = critical_success → item.concealed = true; passive_detection_allowed = false; seek_required = true.
- **Notes to PM:** Confirm whether "concealed" is a flag on the item record or an observer-state flag (item appears hidden to all observers vs only to observers who were present). Automation needs a deterministic model.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (item visibility state flag)

### TC-STL-03 — Conceal an Object Success: item hidden, lower DC to find (vs Crit Success)
- **Suite:** module-test-suite
- **Description:** On Success, the item is hidden (same as Crit Success) but the Seek DC to find it is lower than on a Crit Success. The item is not trivially visible but easier to spot than on a Crit.
- **Expected:** result = success → item.concealed = true; item.seek_dc < crit_success_seek_dc.
- **Notes to PM:** Confirm the exact DC difference between Success and Crit Success tiers for the Seek check. Automation needs deterministic DC values per tier.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (item visibility state flag)

---

### Hide

### TC-STL-04 — Hide: requires cover or concealment to attempt
- **Suite:** module-test-suite
- **Description:** A character may only attempt Hide if they have cover or concealment available in their current position. No cover/concealment → Hide is blocked before the check.
- **Expected:** character.has_cover_or_concealment = true → Hide allowed; character.has_cover_or_concealment = false → blocked.
- **Notes to PM:** Confirm how cover/concealment is modeled: position flag, terrain property, or GM-adjudicated flag on the character's current tile. Automation needs a deterministic gate value.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (gate logic independent of condition state model)

### TC-STL-05 — Hide: Stealth vs each observer's Perception DC
- **Suite:** module-test-suite
- **Description:** The Hide check rolls Stealth against each active observer's Perception DC individually. The check is performed per observer, not as a single group roll.
- **Expected:** for each observer in observer_list: stealth_roll vs observer.perception_dc → individual pass/fail per observer.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-STL-06 — Hide: if ANY observer beats DC, character remains Observed
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — Hide succeeds only if it beats ALL observers. If even one observer beats the character's Stealth DC, the character remains Observed (not Hidden). Partial success does not grant Hidden.
- **Expected:** any observer in observer_list where stealth_roll < observer.perception_dc → character.visibility = observed; only if ALL observers fail → character.visibility = hidden.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (character visibility state: Observed/Hidden)

### TC-STL-07 — Hide: transitions character from Observed to Hidden on full success
- **Suite:** module-test-suite
- **Description:** When the character beats ALL observers' Perception DCs, they transition from Observed → Hidden vs those observers.
- **Expected:** all observers fail → character.visibility_vs_all_observers = hidden.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (Observed/Hidden state transition)

### TC-STL-08 — Hide: Hidden character performing most actions becomes Observed
- **Suite:** module-test-suite
- **Description:** A Hidden character who takes most actions (Attack, Cast, etc.) automatically becomes Observed. Only Hide, Sneak, Step, and other undetected-preserving actions maintain Hidden status.
- **Expected:** hidden_character + action IN [attack, cast_spell, interact_non-stealth] → character.visibility = observed; hidden_character + action IN [hide, sneak, step] → character.visibility = hidden (unchanged).
- **Notes to PM:** Confirm the definitive list of actions that preserve Hidden status beyond Hide/Sneak/Step. Automation needs an enumerated allowlist.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (Hidden state + transition on action)

### TC-STL-09 — Hide in open terrain (no cover): blocked or auto-fail
- **Suite:** module-test-suite
- **Description:** Edge case — attempting Hide in obvious open terrain with no cover or concealment is either blocked (pre-check) or auto-fails. The character cannot become Hidden in a featureless open field.
- **Expected:** character.has_cover_or_concealment = false → Hide blocked (same as TC-STL-04, explicit open-terrain assertion).
- **Roles covered:** authenticated player
- **Status:** immediately activatable (gate logic; no condition state required)

---

### Sneak

### TC-STL-10 — Sneak: requires Hidden status before use
- **Suite:** module-test-suite
- **Description:** Edge case — Sneak is only available to characters who are currently Hidden. Attempting Sneak while Observed is blocked.
- **Expected:** character.visibility = observed → Sneak blocked; character.visibility = hidden → Sneak allowed.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (Hidden state check as gate)

### TC-STL-11 — Sneak: 1-action, Move trait, costs half Speed rounded to 5-ft intervals
- **Suite:** module-test-suite
- **Description:** Sneak costs 1 action, has the Move trait, and allows the character to move up to half their Speed, rounded down to the nearest 5-ft interval.
- **Expected:** action_cost = 1; trait = move; max_distance = floor(character.speed / 2 / 5) * 5.
- **Examples:** Speed 25 → max 10 ft; Speed 30 → max 15 ft; Speed 35 → max 15 ft.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (movement math; no condition state required)

### TC-STL-12 — Sneak speed rounding: half speed rounds DOWN to 5-ft interval (not blocked)
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — when a character's half Speed does not land on a 5-ft boundary, it rounds down (not blocked). E.g., Speed 25 → half = 12.5 → rounds to 10 ft.
- **Expected:** character.speed = 25 → sneak_max_distance = 10; action is not blocked; movement proceeds at 10 ft.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-STL-13 — Sneak: roll Stealth vs each observer's Perception at end of move
- **Suite:** module-test-suite
- **Description:** At the end of a Sneak move, the character rolls Stealth against each active observer's Perception DC (same per-observer pattern as Hide).
- **Expected:** sneak_move_complete → for each observer: stealth_roll vs observer.perception_dc → individual pass/fail.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (roll logic; condition state not required for the check itself)

### TC-STL-14 — Sneak Success: character remains Hidden
- **Suite:** module-test-suite
- **Description:** On Success (beat all observers), the character remains Hidden after the Sneak move.
- **Expected:** all observers fail → character.visibility = hidden (unchanged).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (Hidden state preserved)

### TC-STL-15 — Sneak Failure: character becomes Observed by failing observer
- **Suite:** module-test-suite
- **Description:** On Failure vs a specific observer (that observer beats the Stealth roll), the character becomes Observed by that observer. Partial failure (some observers beat, others don't) makes character Observed.
- **Expected:** observer beats stealth_roll → character.visibility_vs_that_observer = observed.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (Observed state transition)

### TC-STL-16 — Sneak: cannot end move in obvious/open location
- **Suite:** module-test-suite
- **Description:** A character cannot end a Sneak move in an obvious or open location (no cover/concealment). If the chosen destination is open, Sneak is blocked or the character becomes Observed on arrival.
- **Expected:** sneak_destination.has_cover_or_concealment = false → sneak blocked or character.visibility = observed on arrival.
- **Notes to PM:** Confirm whether the open-destination rule blocks the Sneak entirely (must choose a valid end tile) or allows movement but forces Observed on arrival. Automation needs a deterministic outcome.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (visibility state on arrival) — or immediately activatable if implemented as a pre-move block

---

### Avoid Notice

### TC-STL-17 — Avoid Notice: exploration activity, uses Stealth for duration
- **Suite:** module-test-suite
- **Description:** Avoid Notice is an exploration activity (not an encounter action). While active, the character uses their Stealth modifier for all passive detection checks during the exploration phase.
- **Expected:** exploration_mode = true; activity = avoid_notice → character.active_skill_for_detection = stealth.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (activity mode flag; no condition state required)

### TC-STL-18 — Avoid Notice: character starts as Unnoticed
- **Suite:** module-test-suite
- **Description:** When Avoid Notice begins, the character's visibility state is set to Unnoticed (not Observed, not Hidden).
- **Expected:** avoid_notice_start → character.visibility = unnoticed.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (Unnoticed state)

### TC-STL-19 — Avoid Notice: first failed Seek or Perception transitions to Observed
- **Suite:** module-test-suite
- **Description:** During Avoid Notice, the first time a relevant creature succeeds on a Seek or passive Perception check against the character, the character transitions to Observed.
- **Expected:** creature.seek_or_perception succeeds vs character → character.visibility = observed; avoid_notice ends.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (Unnoticed → Observed transition)

---

### ACL regression

### TC-STL-20 — ACL regression: no new routes introduced by Stealth
- **Suite:** role-url-audit
- **Description:** Stealth implementation adds no new HTTP routes; existing encounter and exploration handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing encounter/exploration handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

| TC | Dependency | Reason conditional |
|---|---|---|
| TC-STL-02 | dc-cr-conditions | Item concealed state flag |
| TC-STL-03 | dc-cr-conditions | Item concealed state flag + Seek DC tier |
| TC-STL-06 | dc-cr-conditions | Character Observed/Hidden state |
| TC-STL-07 | dc-cr-conditions | Hidden state transition |
| TC-STL-08 | dc-cr-conditions | Hidden state + action-based transition |
| TC-STL-10 | dc-cr-conditions | Hidden state as Sneak gate |
| TC-STL-14 | dc-cr-conditions | Hidden state preserved |
| TC-STL-15 | dc-cr-conditions | Observed state transition |
| TC-STL-16 | dc-cr-conditions | Visibility state on arrival (or pre-move block) |
| TC-STL-18 | dc-cr-conditions | Unnoticed state |
| TC-STL-19 | dc-cr-conditions | Unnoticed → Observed transition |

9 TCs immediately activatable at Stage 0 (TC-STL-01, TC-STL-04, TC-STL-05, TC-STL-09, TC-STL-11, TC-STL-12, TC-STL-13, TC-STL-17, TC-STL-20).
11 TCs conditional on dc-cr-conditions.

---

## Notes to PM

1. **TC-STL-02/03 (item concealed model):** Confirm whether item concealment is a flag on the item record (visible to all observers equally) or a per-observer flag (each observer may independently not see it). Automation needs a deterministic model.
2. **TC-STL-03 (Seek DC tiers):** Confirm exact Seek DC values for Crit Success vs Success tiers on Conceal an Object. AC says "lower DC to find" for Success but does not specify the magnitude.
3. **TC-STL-04/09 (cover model):** Confirm how cover/concealment is represented in the data model (tile property, character flag, GM-adjudicated boolean). Automation needs a deterministic gate value to set up fixtures.
4. **TC-STL-08 (Hidden-preserving action list):** Confirm the definitive list of actions that preserve Hidden status. AC mentions Hide/Sneak/Step/undetected actions — provide the complete enumeration for automation.
5. **TC-STL-16 (open-destination Sneak):** Confirm whether the open-destination rule blocks the Sneak move before it starts (player must select a valid end tile) or allows movement and forces Observed on arrival. Automation needs a deterministic outcome.
6. **dc-cr-conditions priority:** 11/20 TCs in this feature are conditional on dc-cr-conditions (Observed/Hidden/Unnoticed state transitions). This is the highest conditional-TC ratio in the CR skills batch. Stealth is effectively the primary consumer of the conditions module. Recommend prioritizing dc-cr-conditions in the next release if Stealth is in scope.
7. **Avoid Notice exploration mode:** TC-STL-17/18/19 depend on an exploration mode being a distinct system state. Confirm whether exploration mode is already in scope for the same release or if it's deferred.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-skills-stealth-hide-sneak

## Gap analysis reference
- DB sections: core/ch04/Stealth (Dex) (REQs 1715–1729)
- Depends on: dc-cr-skill-system ✓, dc-cr-conditions

---

## Happy Path

### Conceal an Object [1 action, Manipulation]
- [ ] `[NEW]` Conceal an Object allows hiding a carried/worn item; observers must Seek to find it.
- [ ] `[NEW]` Crit Success: item hidden; observers need Seek to discover it; Success: same as Crit Success but lower DC to find.

### Hide [1 action]
- [ ] `[NEW]` Hide requires cover or concealment to attempt; transitions character from Observed → Hidden vs targeted observers.
- [ ] `[NEW]` Check: Stealth vs each observer's Perception DC.
- [ ] `[NEW]` If any observer beats DC: character remains Observed (not just detected by one).
- [ ] `[NEW]` Hidden character cannot use most actions without becoming Observed; Hide/Sneak/Step/undetected actions preserve Hidden status.

### Sneak [1 action, Move]
- [ ] `[NEW]` Sneak is a 1-action move requiring Hidden status; moves at half Speed (rounded down to 5-ft intervals).
- [ ] `[NEW]` At end of Sneak: roll Stealth vs each observer's Perception.
- [ ] `[NEW]` Success: remain Hidden; Failure: become Observed by failing observer.
- [ ] `[NEW]` Cannot end Sneak in an obvious/open location without becoming Observed.

### Avoid Notice [Exploration]
- [ ] `[NEW]` Avoid Notice (exploration) uses Stealth for the duration of exploration; character starts as Unnoticed.
- [ ] `[NEW]` First failed Seek or Perception by a relevant creature transitions character to Observed.

---

## Edge Cases
- [ ] `[NEW]` Sneak without Hidden status first: blocked — must be Hidden before Sneak.
- [ ] `[NEW]` Hide in open terrain with no cover: blocked or auto-fails.

## Failure Modes
- [ ] `[TEST-ONLY]` Sneak at full speed: rounds down to 5-ft interval (not blocked).
- [ ] `[TEST-ONLY]` Hide vs multiple observers: must succeed against ALL to stay Hidden.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter/exploration handlers
