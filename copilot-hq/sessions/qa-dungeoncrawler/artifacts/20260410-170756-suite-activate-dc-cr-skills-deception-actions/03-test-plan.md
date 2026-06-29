# Test Plan: dc-cr-skills-deception-actions

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Deception skill actions — Create a Diversion, Impersonate, Lie, Feint
**KB reference:** Condition dependency pattern follows dc-cr-skills-athletics-actions (conditions module); flat-footed/hidden/observed states require dc-cr-conditions. Disguise kit inventory check follows dc-cr-skills-crafting-actions repair-kit pattern (dc-cr-equipment-system).
**Dependency note:** dc-cr-conditions (in-progress Release B) — TCs requiring hidden/flat-footed/observed state tracking are conditional. dc-cr-equipment-system — disguise kit inventory check is conditional.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Deception action business logic: action types, trait gates, proficiency gates, diversion bonus, degree outcomes, condition application, lie resistance tracking, feint melee-reach gate |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing encounter/exploration handler routes only |

---

## Test Cases

### Create a Diversion

### TC-DEC-01 — Create a Diversion is a 1-action
- **Suite:** module-test-suite
- **Description:** Create a Diversion is classified as a 1-action encounter action (not 2-action, not exploration, not downtime).
- **Expected:** action_cost = 1; action_type = encounter.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-DEC-02 — Create a Diversion traits: manipulate OR auditory+linguistic+mental
- **Suite:** module-test-suite
- **Description:** Diversion via physical gesture carries the manipulate trait; diversion via vocalization carries auditory, linguistic, and mental traits. The two methods are mutually exclusive per attempt.
- **Expected:** method = gesture → traits = [manipulate]; method = vocalization → traits = [auditory, linguistic, mental].
- **Notes to PM:** AC specifies "manipulate OR auditory+linguistic+mental depending on method." Confirm whether method selection is a player input at resolution time or is determined by AC type on the character sheet. Automation needs a deterministic input.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (trait assignment logic; no condition dependency)

### TC-DEC-03 — Create a Diversion: +4 circumstance bonus to Stealth vs targeted observers
- **Suite:** module-test-suite
- **Description:** On any result (attempt), character gains a +4 circumstance bonus to Stealth checks against all targeted observers' Perception DCs. Bonus lasts up to 1 minute (10 rounds).
- **Expected:** diversion_bonus_applied = +4 circumstance to Stealth vs targeted observers; duration = 10 rounds.
- **Notes to PM:** AC says "for 1 minute after attempting" — confirm whether this is 1 minute of real time (10 rounds of 6-second combat) or 1 minute of exploration time. Automation needs a round-count or time-window scalar.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (bonus tracking; no condition module needed for the numeric bonus itself)

### TC-DEC-04 — Create a Diversion success: character becomes Hidden (not Undetected)
- **Suite:** module-test-suite
- **Description:** On a successful Diversion, character gains the Hidden condition (not Undetected) against targeted observers.
- **Expected:** diversion_success → character condition = hidden (vs targeted observers); NOT undetected.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (hidden condition tracking)

### TC-DEC-05 — Hidden from Diversion reverts to Observed on most actions (except Hide, Sneak, Step)
- **Suite:** module-test-suite
- **Description:** After a successful Diversion, taking any action other than Hide, Sneak, or Step causes the character to revert from Hidden to Observed.
- **Expected:** while_hidden_from_diversion AND action NOT IN [hide, sneak, step] → character condition reverts to observed.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (hidden/observed state transition logic)

### TC-DEC-06 — Striking while Hidden from Diversion: target flat-footed; character becomes Observed
- **Suite:** module-test-suite
- **Description:** If the character makes a Strike while Hidden from a Diversion, the target is flat-footed for that attack. After the Strike resolves, the character becomes Observed regardless of outcome.
- **Expected:** strike_while_hidden_from_diversion → target condition = flat-footed (for that attack); after strike → character = observed.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (flat-footed and hidden/observed state)

### TC-DEC-07 — Create a Diversion bonus applies only to the current attempt (edge case)
- **Suite:** module-test-suite
- **Description:** Edge case — the +4 circumstance bonus from Create a Diversion does NOT carry over to future Diversion attempts; each attempt stands alone.
- **Expected:** second diversion attempt resets the bonus window; no stacking from prior diversion.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (bonus-reset logic; no condition module needed)

---

### Impersonate

### TC-DEC-08 — Impersonate is an exploration activity
- **Suite:** module-test-suite
- **Description:** Impersonate is classified as an exploration activity (not encounter, not downtime).
- **Expected:** action_type = exploration.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-DEC-09 — Impersonate requires 10 minutes and a disguise kit
- **Suite:** module-test-suite
- **Description:** Impersonate requires 10 uninterrupted minutes and the presence of a disguise kit in the character's inventory.
- **Expected:** time_cost = 10 minutes; disguise_kit_available = false → action blocked.
- **Roles covered:** authenticated player
- **Conditional:** disguise_kit inventory check depends on dc-cr-equipment-system

### TC-DEC-10 — Impersonate without disguise kit: –2 penalty (not hard block by default)
- **Suite:** module-test-suite
- **Description:** Edge case — per AC, Impersonate without a disguise kit proceeds at a –2 circumstance penalty rather than a hard block (GM discretion; AC specifies –2 or blocked per GM). For automation, default behavior should be –2 penalty applied.
- **Expected:** disguise_kit_available = false AND gm_override = false → check proceeds at –2 circumstance penalty to Deception.
- **Notes to PM:** AC says "at –2 or blocked per GM" — for deterministic automation, recommend defining the system default as –2 penalty and allowing a GM-configurable hard-block flag. Please confirm.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (disguise kit presence check)

### TC-DEC-11 — Impersonate: passive observers use Perception vs character's Deception DC
- **Suite:** module-test-suite
- **Description:** Passive observers check their Perception against the character's Deception DC; the system rolls for observers automatically (secret check on character's side).
- **Expected:** impersonate_active → system rolls observer Perception vs character Deception DC; result determines recognition state.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (check resolution logic; no condition dependency)

### TC-DEC-12 — Impersonate: active searchers use Seek action
- **Suite:** module-test-suite
- **Description:** A creature actively searching the impersonated character uses the Seek action rather than a passive Perception check.
- **Expected:** observer using seek action → Seek check (not passive Perception) vs character Deception DC.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (check routing logic)

### TC-DEC-13 — Impersonate Critical Failure: reveals true identity to recognizing observers
- **Suite:** module-test-suite
- **Description:** Failure mode — on Critical Failure of the Impersonate check, the character's true identity is revealed to any observer who would have recognized them; this is distinct from a plain Failure (which may just fail to convince).
- **Expected:** impersonate_result = critical_failure → recognizing_observers informed of true identity; NOT a generic "check failed" message.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (outcome type logic; no condition dependency)

---

### Lie

### TC-DEC-14 — Lie is a secret check
- **Suite:** module-test-suite
- **Description:** Lie is resolved as a secret check; the player does not see the outcome directly (GM/system mediates).
- **Expected:** check_type = secret; result not surfaced directly to player.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-DEC-15 — Lie: single roll compared to each target's Perception DC individually
- **Suite:** module-test-suite
- **Description:** A single Deception roll is made and compared separately to each target's Perception DC; different targets may get different outcomes from the same roll.
- **Expected:** single_roll_value compared to Perception DC of each targeted listener independently; per-target result recorded.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (multi-target comparison logic)

### TC-DEC-16 — Lie Failure: +4 circumstance bonus to resist future lies (same conversation)
- **Suite:** module-test-suite
- **Description:** Failure mode — if a Lie check fails against a target, that target gains a +4 circumstance bonus to Perception when resisting any future Lie from the same character for the rest of the current conversation.
- **Expected:** lie_result = failure vs target X → target_X.lie_resistance_bonus = +4 circumstance; bonus scoped to current conversation; bonus not permanent.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (bonus tracking logic; no condition module)

### TC-DEC-17 — Lie failure resistance is per-conversation, not permanent
- **Suite:** module-test-suite
- **Description:** Failure mode / edge case — the +4 resistance bonus from a failed Lie expires when the conversation ends; it does not persist to future encounters.
- **Expected:** conversation_end → target lie_resistance_bonus cleared; no residual bonus in new conversation.
- **Notes to PM:** Confirm what constitutes "conversation end" in the system model (explicit conversation-end event? Scene/encounter transition? Time threshold?). Automation needs a deterministic conversation-scope boundary.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (scope-reset logic)

### TC-DEC-18 — Lie: GM delayed recheck on contradicting evidence
- **Suite:** module-test-suite
- **Description:** The GM may allow a delayed recheck if a target later encounters evidence contradicting the Lie. This is a GM-triggered event (not automated by default); the system should support a recheck trigger.
- **Expected:** gm_triggers_recheck = true → a new Lie vs Perception check is resolved for the affected target.
- **Notes to PM:** This is flagged as not fully automatable without a GM action trigger. Recommend documenting "GM recheck trigger" as a GM-facing UI action rather than an automated system rule. The automated TC should validate that the recheck mechanism resolves correctly when triggered.
- **Roles covered:** authenticated player, GM
- **Status:** immediately activatable for the resolution path; recheck trigger depends on GM-action implementation

---

### Feint

### TC-DEC-19 — Feint is a 1-action with mental trait
- **Suite:** module-test-suite
- **Description:** Feint is classified as a 1-action encounter action with the mental trait.
- **Expected:** action_cost = 1; action_type = encounter; traits = [mental].
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-DEC-20 — Feint requires Trained Deception
- **Suite:** module-test-suite
- **Description:** Feint is blocked for characters with Untrained Deception.
- **Expected:** deception_rank = untrained → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-DEC-21 — Feint requires melee reach
- **Suite:** module-test-suite
- **Description:** Feint is blocked when the character is not in melee reach of the target.
- **Expected:** target_in_melee_reach = false → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (reach check; no condition module needed)

### TC-DEC-22 — Feint Critical Success: target flat-footed for full turn of attacker's attacks
- **Suite:** module-test-suite
- **Description:** On Critical Success, the target is flat-footed against all of the attacker's attacks for the attacker's full current turn.
- **Expected:** feint_result = critical_success → target condition = flat-footed vs attacker (duration: remainder of attacker's turn, all attacks).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (flat-footed condition and turn-scoped duration)

### TC-DEC-23 — Feint Success: target flat-footed for next one attack only
- **Suite:** module-test-suite
- **Description:** On Success, the target is flat-footed only against the attacker's next single attack (not the full turn).
- **Expected:** feint_result = success → target condition = flat-footed vs attacker (duration: next one attack only); clears after that attack resolves.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (flat-footed condition and single-attack scope)

### TC-DEC-24 — Feint Critical Failure: attacker becomes flat-footed
- **Suite:** module-test-suite
- **Description:** Failure mode — on Critical Failure of Feint, the attacker (not the target) becomes flat-footed.
- **Expected:** feint_result = critical_failure → attacker condition = flat-footed (duration per conditions module).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (flat-footed applied to attacker)

### TC-DEC-25 — Feint blocked outside melee reach (failure mode)
- **Suite:** module-test-suite
- **Description:** Failure mode — attempting Feint against a target outside melee reach returns an error and does not roll the check.
- **Expected:** target_range > attacker_melee_reach → action blocked; no check made; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (same as TC-DEC-21 but tests the explicit error path without a check roll)

---

### ACL regression

### TC-DEC-26 — ACL regression: no new routes introduced by Deception actions
- **Suite:** role-url-audit
- **Description:** Deception action implementation adds no new HTTP routes; existing encounter/exploration handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Conditional dependency summary

| TC | Dependency feature | Reason conditional |
|---|---|---|
| TC-DEC-04 | `dc-cr-conditions` | Hidden condition application |
| TC-DEC-05 | `dc-cr-conditions` | Hidden → Observed state transition on actions |
| TC-DEC-06 | `dc-cr-conditions` | Flat-footed (target) + Hidden → Observed (attacker) |
| TC-DEC-09 | `dc-cr-equipment-system` | Disguise kit inventory check |
| TC-DEC-10 | `dc-cr-equipment-system` | Disguise kit presence for –2 penalty path |
| TC-DEC-22 | `dc-cr-conditions` | Flat-footed (full turn scope) |
| TC-DEC-23 | `dc-cr-conditions` | Flat-footed (single attack scope) |
| TC-DEC-24 | `dc-cr-conditions` | Flat-footed applied to attacker |

19 TCs immediately activatable at Stage 0.
7 TCs conditional on `dc-cr-conditions` (4) or `dc-cr-equipment-system` (2) or both (1).

---

## Notes to PM

1. **TC-DEC-02 (Diversion method):** AC specifies traits vary by method (gesture = manipulate; vocalization = auditory+linguistic+mental). Confirm whether method is a player-selected input at action resolution, or determined by an AC type on the character sheet (e.g., a class feature or action variant). Automation requires a deterministic selection model.
2. **TC-DEC-03 (Diversion duration):** AC says "1 minute." Confirm whether this is 10 combat rounds (6 seconds each) or 1 minute of exploration time, and how the system tracks expiry.
3. **TC-DEC-10 (Impersonate without kit):** AC says "–2 or blocked per GM." For automation, recommend a system default of –2 circumstance penalty with an optional GM-flag to hard-block. Please confirm the default.
4. **TC-DEC-17 (Lie conversation scope):** Confirm what marks "conversation end" in the system model (explicit GM event? Scene transition? Time trigger?).
5. **TC-DEC-18 (GM delayed recheck):** This requires a GM-initiated action trigger in the UI. Recommend treating this as a GM-facing control rather than automated game logic; the automated TC validates the recheck resolution path, not the trigger.
6. **Staging recommendation:** The 19 immediately activatable TCs cover all action/trait/proficiency gates, check resolution types, multi-target logic, bonus/resistance tracking, and ACL. The 7 condition/equipment-dependent TCs activate when dc-cr-conditions and dc-cr-equipment-system ship in Release B. This mirrors the Athletics staging pattern.
