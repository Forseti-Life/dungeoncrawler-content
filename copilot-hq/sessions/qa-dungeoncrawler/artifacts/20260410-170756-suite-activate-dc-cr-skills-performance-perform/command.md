- Status: done
- Completed: 2026-04-11T02:53:39Z

# Suite Activation: dc-cr-skills-performance-perform

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-10T17:07:56+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-skills-performance-perform"`**  
   This links the test to the living requirements doc at `features/dc-cr-skills-performance-perform/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-skills-performance-perform-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-skills-performance-perform",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-skills-performance-perform"`**  
   Example:
   ```json
   {
     "id": "dc-cr-skills-performance-perform-<route-slug>",
     "feature_id": "dc-cr-skills-performance-perform",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-skills-performance-perform",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-skills-performance-perform

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Performance skill — Perform (encounter 1-action + downtime Earn Income)
**KB reference:** Earn Income table shared with dc-cr-skills-lore-earn-income (LRE) — see that feature's AC for task-level DC table, Trained cap (level–1), Crit Fail employer-block. No duplicate TCs for the table itself; cross-reference LRE TCs for Income table validation.
**Dependency note:** Perform (encounter) result-passing depends on a class-feature hook system (e.g., Bard Inspire Courage). TCs that assert hook delivery are conditional on that hook system existing. All other TCs (DC resolution, degrees, art-type gate, Earn Income path) are immediately activatable on dc-cr-skill-system.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Performance business logic: action cost, art-type gate, 4 degrees, hook notification, Earn Income path, multi-art independence |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing encounter/downtime handler routes only |

---

## Test Cases

### Perform — core action

### TC-PER-01 — Perform: 1-action cost in encounter context
- **Suite:** module-test-suite
- **Description:** Perform costs 1 action when used in encounter mode (not as a downtime activity).
- **Expected:** action_cost = 1 in encounter context.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-PER-02 — Perform: character must have an art type assigned
- **Suite:** module-test-suite
- **Description:** Perform requires the character to have at least one art type assigned (chosen at character creation or via feat/training). Attempting to Perform without an art type is blocked.
- **Expected:** character.art_types.length >= 1 → Perform allowed; character.art_types.length = 0 → Perform blocked or returns untrained-no-art error.
- **Notes to PM:** AC lists art types as "acting, comedy, dance, oratory, singing, keyboards, percussion, strings, winds, etc." Confirm whether the list is open (free-text tag) or closed (enum). Automation needs a deterministic set for fixture setup.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (gate logic independent of art-type list completeness)

### TC-PER-03 — Perform: 4 degrees of success in encounter
- **Suite:** module-test-suite
- **Description:** A Perform check in encounter produces one of four degree values communicated back to the caller: critical_success, success, failure, critical_failure. The degree must not be silently swallowed.
- **Expected:**
  - roll >= dc + 10 → degree = critical_success
  - roll >= dc → degree = success
  - roll < dc → degree = failure
  - roll <= dc - 10 → degree = critical_failure
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-PER-04 — Perform Crit Success: "crowd loves it" outcome
- **Suite:** module-test-suite
- **Description:** On Critical Success, the system records/returns a positive crowd-reaction tag (e.g., `crowd_reaction: loved`). This is the trigger for maximum class-feature effects.
- **Expected:** result = critical_success → outcome_tag = loved (or equivalent positive enum).
- **Notes to PM:** Confirm the authoritative outcome enum for crowd reaction (loved/polite/poor/embarrassing or narrative text). Automation needs a deterministic expected value.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (outcome tagging independent of hook delivery)

### TC-PER-05 — Perform Success: "polite reception" outcome
- **Suite:** module-test-suite
- **Description:** On Success, the system records a neutral/polite crowd-reaction outcome.
- **Expected:** result = success → outcome_tag = polite (or equivalent).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-PER-06 — Perform Failure: "poor reaction" outcome
- **Suite:** module-test-suite
- **Description:** On Failure, the system records a poor crowd-reaction outcome.
- **Expected:** result = failure → outcome_tag = poor (or equivalent).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-PER-07 — Perform Crit Failure: "embarrassing" outcome — not silently succeeded
- **Suite:** module-test-suite
- **Description:** On Critical Failure, the system returns a critical_failure degree and an embarrassing outcome tag — it must NOT silently succeed or up-grade to failure. The degree must be observable to the caller.
- **Expected:** result = critical_failure → outcome_tag = embarrassing; degree returned to caller is explicitly critical_failure.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Perform — class-feature hook delivery

### TC-PER-08 — Perform result delivered to class-feature hooks (e.g., Bard Inspire Courage)
- **Suite:** module-test-suite
- **Description:** When a Perform check resolves in encounter, the result (degree) is passed to any registered class-feature hooks. A mock Bard Inspire Courage hook should receive the correct degree.
- **Expected:** hook_system.receive_perform_result(character_id, degree) called with correct degree after Perform resolves.
- **Notes to PM:** This TC depends on a class-feature hook registration/dispatch system. If the hook system is not yet implemented, this TC is conditional. Recommend confirming which release includes hook infrastructure.
- **Roles covered:** authenticated player
- **Conditional:** depends on class-feature hook system (Bard module or generic hook dispatcher)

### TC-PER-09 — Perform result not delivered if no hooks registered
- **Suite:** module-test-suite
- **Description:** If no class-feature hooks are registered, Perform still resolves cleanly (no error). The absence of hooks is not a failure mode.
- **Expected:** no hooks registered → Perform completes normally; degree returned to caller; no exception thrown.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (negative hook path; no hook system required)

---

### Perform — Earn Income (downtime)

### TC-PER-10 — Perform Earn Income: downtime activity, follows standard table
- **Suite:** module-test-suite
- **Description:** Perform used as a downtime Earn Income activity follows the same table as dc-cr-skills-lore-earn-income (task-level DC 1–20, income by level/degree, Trained cap at level–1, Crit Fail employer-block). The skill used is Performance.
- **Expected:** earn_income(skill: performance, task_level: N) → DC, income amount, and degree outcomes match the standard Earn Income table from dc-cr-skills-lore-earn-income AC.
- **Cross-reference:** TC-LRE-06 through TC-LRE-16 (Earn Income table validation). Do not duplicate those TCs; assert that Performance uses the same resolution path.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (same table; just confirm skill routing)

### TC-PER-11 — Perform Earn Income: Performance skill check used (not Crafting or Lore)
- **Suite:** module-test-suite
- **Description:** When Earn Income is invoked with Performance as the skill, the check uses the character's Performance modifier (not Crafting or Lore), and Trained requirement is verified against Performance rank.
- **Expected:** earn_income(skill: performance) → check = character.performance_modifier + roll; performance_rank < trained → blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Perform — art types

### TC-PER-12 — Art type tracked per character; multiple art types independent
- **Suite:** module-test-suite
- **Description:** If a feat grants a second art type, both are tracked independently. A Perform check may specify which art type is used; the system does not merge or collapse them.
- **Expected:** character.art_types = [singing, oratory] → Perform(art: singing) resolves independently from Perform(art: oratory); each art type has its own record.
- **Notes to PM:** Confirm whether the art type is selected at check time (player chooses which art to Perform) or whether all assigned arts apply simultaneously. Automation needs a deterministic selection model.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (data model independence; no cross-art interaction logic required)

### TC-PER-13 — Perform with art type not assigned to character: blocked or degraded
- **Suite:** module-test-suite
- **Description:** Edge case — attempting to Perform with an art type the character has not trained/assigned should be blocked or treated as no-art (untrained).
- **Expected:** Perform(art: dance) where character.art_types does not include dance → blocked or returns untrained-no-art error.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### ACL regression

### TC-PER-14 — ACL regression: no new routes introduced by Performance/Perform
- **Suite:** role-url-audit
- **Description:** Performance and Perform implementation adds no new HTTP routes; existing encounter and downtime handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing encounter/downtime handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

| TC | Dependency | Reason conditional |
|---|---|---|
| TC-PER-08 | Class-feature hook system | Hook dispatch infrastructure (Bard module or generic hook dispatcher) |

13 TCs immediately activatable at Stage 0 (TC-PER-01 through TC-PER-07, TC-PER-09 through TC-PER-14).
1 TC conditional (TC-PER-08).

---

## Notes to PM

1. **TC-PER-02 (art type list):** Confirm whether the art-type list is open (free-text tag, anything goes) or closed (strict enum). Automation needs a deterministic set for fixture setup (to test "no art type assigned" vs "assigned a known type").
2. **TC-PER-04 through TC-PER-07 (outcome enum):** Confirm the authoritative outcome tag enum for crowd reaction: `loved / polite / poor / embarrassing` (or alternative values). These are used as assertion targets.
3. **TC-PER-08 (hook infrastructure):** Confirm which release includes the class-feature hook dispatch system. If it lands in this release, TC-PER-08 activates at Stage 0. If not, it remains conditional and should be flagged in the next release's grooming batch.
4. **TC-PER-12 (multi-art selection):** Confirm whether art type is selected at Perform check time (player picks which art to perform) or whether all assigned arts are treated identically. If identical, TC-PER-12 simplifies to a data-model check.
5. **Earn Income cross-reference:** TC-PER-10/11 deliberately cross-reference LRE TCs rather than duplicating them. If the Earn Income table changes, both features are affected. Recommend shared table fixture.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-skills-performance-perform

## Gap analysis reference
- DB sections: core/ch04/Performance (Cha) (REQs 1705–1708)
- Depends on: dc-cr-skill-system ✓

---

## Happy Path

### Perform [1 action or Downtime]
- [ ] `[NEW]` Perform can be used as a 1-action in encounter (to support abilities) or downtime to Earn Income.
- [ ] `[NEW]` Art types: acting, comedy, dance, oratory, singing, keyboards, percussion, strings, winds, etc. (character chooses one art type at character creation or training).
- [ ] `[NEW]` Earn Income via Performance: follows standard Earn Income table (see dc-cr-skills-lore-earn-income AC).

### Perform (Encounter — Inspire / Class Feature Support)
- [ ] `[NEW]` Perform check result (Crit Success / Success / Failure / Crit Failure) communicated to class-feature hooks (e.g., Bard Inspire Courage).
- [ ] `[NEW]` Crit Success: crowd loves it; Success: polite reception; Failure: poor reaction; Crit Failure: embarrassing.

---

## Edge Cases
- [ ] `[NEW]` Multiple art types: each tracked independently if feat grants additional art.

## Failure Modes
- [ ] `[TEST-ONLY]` Perform Crit Fail in encounter does not silently succeed — returns fail degree to caller.

## Security acceptance criteria
- Security AC exemption: skill action logic; no new routes beyond existing encounter/downtime handlers
- Agent: qa-dungeoncrawler
- Status: pending
