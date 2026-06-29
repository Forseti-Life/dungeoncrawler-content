# Suite Activation: dc-cr-skills-calculator-hardening

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
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-skills-calculator-hardening"`**  
   This links the test to the living requirements doc at `features/dc-cr-skills-calculator-hardening/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-skills-calculator-hardening-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-skills-calculator-hardening",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-skills-calculator-hardening"`**  
   Example:
   ```json
   {
     "id": "dc-cr-skills-calculator-hardening-<route-slug>",
     "feature_id": "dc-cr-skills-calculator-hardening",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-skills-calculator-hardening",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-skills-calculator-hardening

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Skills calculator hardening — trained-only gating, proficiency rank ceiling, armor check penalty
**KB reference:** None found specific to calculator hardening. Trained-only gate pattern is consistent with per-skill trained-gate TCs across the CR skills batch (TC-THI-06, TC-THI-12, TC-MED-*, etc.) — the calculator-level enforcement is the server-side counterpart to those per-action gates. The armor check penalty exclusion for attack-trait actions is a new constraint not previously seen in any other CR batch feature.
**Dependency note:** All TCs depend on dc-cr-skill-system (in scope, ✓). Three TCs depend on dc-cr-character-leveling (proficiency rank ceiling enforcement at level 7/15) — flagged as conditional below. Security AC exemption granted: no new routes or user-facing input surfaces.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All calculator hardening business logic: trained-only action gate, proficiency rank ceiling, armor check penalty apply/exclude, server-side enforcement, error message assertions |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes per security AC exemption; existing character creation and leveling form routes only |

---

## Test Cases

### Trained-Only Action Gating

### TC-CALC-01 — Trained gate: `calculateSkillCheck()` blocks untrained character on trained-only action
- **Suite:** module-test-suite
- **Description:** When `calculateSkillCheck()` is called for an untrained character attempting a trained-only action, the function returns a blocked/error result — not a degraded partial result and not a silent no-op.
- **Expected:** result.status = blocked OR result.error is set; result is NOT a numeric roll result.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CALC-02 — Trained gate error message: explicitly states character is untrained
- **Suite:** module-test-suite
- **Description:** The error returned by TC-CALC-01 must include a message explicitly stating the character is untrained in the required skill (not a generic "action unavailable" message).
- **Expected:** result.error_message contains skill-name AND "untrained" (or equivalent string); not a generic error.
- **Notes to PM:** Confirm the exact error string format expected (localized key vs hardcoded string) so the assertion can match it deterministically.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CALC-03 — Trained gate: trained character not blocked
- **Suite:** module-test-suite
- **Description:** A character with Trained (or higher) rank in the relevant skill is not blocked by the trained-only gate — the check proceeds normally.
- **Expected:** character.skill_rank >= trained → calculateSkillCheck() returns numeric result (not blocked).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Proficiency Rank Ceiling Enforcement

### TC-CALC-04 — Rank ceiling: Expert → Master requires level ≥ 7
- **Suite:** module-test-suite
- **Description:** `submitSkillIncrease()` enforces that a character must be at least level 7 to increase a skill from Expert to Master.
- **Expected:** character.level < 7 AND increase_from = Expert AND increase_to = Master → result.status = blocked; result.error includes level-requirement message.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (character level field)

### TC-CALC-05 — Rank ceiling: Master → Legendary requires level ≥ 15
- **Suite:** module-test-suite
- **Description:** `submitSkillIncrease()` enforces that a character must be at least level 15 to increase a skill from Master to Legendary.
- **Expected:** character.level < 15 AND increase_from = Master AND increase_to = Legendary → result.status = blocked; result.error includes level-requirement message.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (character level field)

### TC-CALC-06 — Rank ceiling: blocked increase returns clear error, does not silently no-op
- **Suite:** module-test-suite
- **Description:** When `submitSkillIncrease()` blocks a rank increase due to level ceiling, it must return an explicit error. Silent no-ops (function returns without change and without error) are a defect.
- **Expected:** result.status = blocked AND result.error_message is non-empty; current rank remains unchanged; NOT a silent no-op.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (character level field)

### TC-CALC-07 — Rank ceiling: Expert → Master allowed at level 7
- **Suite:** module-test-suite
- **Description:** A character at exactly level 7 may increase from Expert to Master — the ceiling boundary is inclusive.
- **Expected:** character.level = 7 AND increase_from = Expert AND increase_to = Master → result.status = success; skill rank updated to Master.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (character level field)

### TC-CALC-08 — Rank ceiling: Master → Legendary allowed at level 15
- **Suite:** module-test-suite
- **Description:** A character at exactly level 15 may increase from Master to Legendary — the ceiling boundary is inclusive.
- **Expected:** character.level = 15 AND increase_from = Master AND increase_to = Legendary → result.status = success; skill rank updated to Legendary.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (character level field)

---

### Armor Check Penalty

### TC-CALC-09 — Armor check penalty applied to Strength-based skill rolls
- **Suite:** module-test-suite
- **Description:** `calculateSkillCheck()` applies the character's armor check penalty to Strength-based skill rolls (e.g., Athletics).
- **Expected:** character.armor_check_penalty > 0 AND skill.key_ability = Strength AND action.trait != attack → roll_total = base_roll + skill_bonus - armor_check_penalty.
- **Notes to PM:** Confirm the list of Strength-based skills in scope (Athletics is the canonical one; are there others?). Automation needs an explicit skill → ability mapping.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CALC-10 — Armor check penalty applied to Dexterity-based skill rolls (non-attack)
- **Suite:** module-test-suite
- **Description:** `calculateSkillCheck()` applies the character's armor check penalty to Dexterity-based skill rolls when the action does NOT have the attack trait.
- **Expected:** character.armor_check_penalty > 0 AND skill.key_ability = Dexterity AND action.trait != attack → roll_total includes penalty.
- **Notes to PM:** Confirm which Dex-based skills are in scope (Acrobatics, Stealth, Thievery are the canonical Dex skills in PF2e). Automation needs an explicit skill → ability mapping.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CALC-11 — Armor check penalty NOT applied to attack-trait actions (e.g., Grapple, Trip, Disarm)
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — when an action has the attack trait (Grapple, Trip, Disarm used as attack actions), the armor check penalty must NOT be applied to the roll, even if the character wears armor.
- **Expected:** character.armor_check_penalty > 0 AND action.trait = attack → roll_total = base_roll + skill_bonus (no penalty subtracted).
- **Notes to PM:** Confirm that "attack trait" is a boolean flag on the action entity, not computed from the skill. Grapple/Trip/Disarm are in scope; are there others that should be tested? Automation needs a deterministic action.trait = attack assertion.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CALC-12 — Zero armor check penalty: unarmored character, no penalty applied
- **Suite:** module-test-suite
- **Description:** When the character is unarmored (armor check penalty = 0), no penalty is subtracted from any skill roll. The roll total equals base_roll + skill_bonus.
- **Expected:** character.armor_check_penalty = 0 → roll_total = base_roll + skill_bonus for all Str/Dex-based skills.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Failure Modes

### TC-CALC-13 — Untrained attempting trained-only: blocked, not silently degraded
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — an untrained character attempting a trained-only action must be explicitly blocked. The system must not silently degrade (e.g., proceed at a penalty, or return a roll without the trained bonus). This is a more targeted variant of TC-CALC-01 focused on the degradation scenario.
- **Expected:** result.status = blocked; result does NOT contain a numeric roll (no silent degradation path).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-CALC-14 — Armor check penalty on Dex attack-trait action: NOT applied
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — explicitly assert that a Dex-based attack-trait action (e.g., Trip) does NOT apply the armor check penalty, even when the character is wearing medium or heavy armor.
- **Expected:** action = Trip (or similar attack-trait action) AND skill.key_ability = Dexterity AND character.armor_check_penalty > 0 → roll_total = base_roll + skill_bonus (no penalty).
- **Notes to PM:** Trip is Str-based in PF2e canonical rules (Athletics). Confirm which Dex-based attack-trait actions exist in scope. If none, this TC may need to use a synthetic test fixture or be scoped to a Str-based attack-trait action for cross-checking.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (pending PM confirmation of canonical Dex attack-trait actions in scope)

### TC-CALC-15 — Server-side enforcement: rank ceiling bypass via direct API call still blocked
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — a direct API call to `submitSkillIncrease()` bypassing the UI cannot circumvent the level ceiling. The ceiling is enforced in the service layer, not only in the form/UI layer.
- **Expected:** POST to skill increase endpoint with below-threshold level → HTTP 400/403 (or service-layer blocked result); rank not updated in DB.
- **Notes to PM:** Confirm whether the enforcement is in the service layer (Drupal service) or only in the form validation layer. If form-only, this is a security gap that needs escalation. Automation needs to call the service method directly (not via form submission) to verify server-side enforcement.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-character-leveling (character level field needed to set up fixture)

---

### ACL regression

### TC-CALC-16 — ACL regression: no new routes; existing forms retain expected ACL
- **Suite:** role-url-audit
- **Description:** Per the security AC exemption, calculator hardening introduces no new HTTP routes. Existing character creation and leveling form routes must retain their ACL (authenticated player: 200; anonymous: 403/redirect).
- **Expected:** existing routes: HTTP 200 for authenticated player; HTTP 403/redirect for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

| TC | Dependency | Reason conditional |
|---|---|---|
| TC-CALC-04 | dc-cr-character-leveling | Character level field needed for ceiling gate |
| TC-CALC-05 | dc-cr-character-leveling | Character level field needed for ceiling gate |
| TC-CALC-06 | dc-cr-character-leveling | Error-not-silent assertion requires level fixture |
| TC-CALC-07 | dc-cr-character-leveling | Boundary-inclusive assertion at exactly level 7 |
| TC-CALC-08 | dc-cr-character-leveling | Boundary-inclusive assertion at exactly level 15 |
| TC-CALC-15 | dc-cr-character-leveling | Server-side enforcement fixture needs level field |

10 TCs immediately activatable.
6 TCs conditional on dc-cr-character-leveling.

---

## Notes to PM

1. **TC-CALC-02 (error string format):** Confirm the exact trained-gate error message format (localized key vs hardcoded string; what the skill-name placeholder looks like). Automation assertions need a deterministic match string.
2. **TC-CALC-09 (Str-based skills list):** Confirm which skills are Str-based in scope. Athletics is canonical; confirm if others exist. Automation needs an explicit skill → ability mapping table.
3. **TC-CALC-10 (Dex-based skills list):** Same as above for Dexterity. Acrobatics, Stealth, Thievery are canonical Dex skills.
4. **TC-CALC-11 (attack-trait flag model):** Confirm that `action.trait = attack` is a stored boolean/enum on the action entity (not computed). List canonical attack-trait actions in scope (Grapple, Trip, Disarm confirmed — others?).
5. **TC-CALC-14 (Dex attack-trait actions):** Trip is Str-based canonically. Confirm which, if any, Dex-based skill actions carry the attack trait — or adjust this TC to use a Str-based attack-trait action for the armor-penalty-exclusion assertion. Risk: if no Dex attack-trait actions exist, this TC needs rework before Stage 0 activation.
6. **TC-CALC-15 (server-side vs form-only enforcement):** Confirm that `submitSkillIncrease()` enforces the ceiling in the service layer, not only in the Drupal form `validate()`. If form-only, this is a security gap requiring escalation before activation.

### Acceptance criteria (reference)

# Acceptance Criteria: dc-cr-skills-calculator-hardening

## Gap analysis reference
- DB sections: core/ch04 General Skill Actions (REQs 1554–1568, 1600, 2323) — enforcement gaps
- Depends on: dc-cr-skill-system ✓, dc-cr-character-leveling

---

## Happy Path

### Trained-Only Action Gating
- [ ] `[EXTEND]` `calculateSkillCheck()` returns an error/blocked result when an untrained character attempts a trained-only action.
- [ ] `[EXTEND]` Error message clearly states the character is untrained in the required skill.

### Proficiency Rank Ceiling Enforcement
- [ ] `[EXTEND]` `submitSkillIncrease()` enforces level ≥ 7 before allowing Expert → Master increase.
- [ ] `[EXTEND]` `submitSkillIncrease()` enforces level ≥ 15 before allowing Master → Legendary increase.
- [ ] `[EXTEND]` Blocked increase returns clear error, does not silently no-op.

### Armor Check Penalty
- [ ] `[EXTEND]` `calculateSkillCheck()` applies the character's armor check penalty to Strength- and Dexterity-based skill rolls.
- [ ] `[EXTEND]` Armor check penalty is NOT applied to attack-trait actions (e.g., Grapple, Trip, Disarm when used as attack actions).
- [ ] `[EXTEND]` Zero armor check penalty for unarmored characters = no penalty applied.

---

## Edge Cases
- [ ] `[EXTEND]` Expert → Master below level 7: blocked with clear level-requirement error.
- [ ] `[EXTEND]` Master → Legendary below level 15: blocked with clear level-requirement error.

## Failure Modes
- [ ] `[TEST-ONLY]` Untrained character attempting trained-only action: blocked (not silently degraded).
- [ ] `[TEST-ONLY]` Armor check penalty on Dex-based attack trait action: NOT applied.
- [ ] `[TEST-ONLY]` Rank ceiling bypass via direct API call: still enforced server-side.

## Security acceptance criteria
- Security AC exemption: internal calculator service hardening; no new routes or user-facing input beyond existing character creation and leveling forms
