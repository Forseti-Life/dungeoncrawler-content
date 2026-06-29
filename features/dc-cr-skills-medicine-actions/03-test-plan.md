# Test Plan: dc-cr-skills-medicine-actions

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Medicine skill actions — Administer First Aid, Treat Disease, Treat Poison, Treat Wounds
**KB reference:** Healer's tools inventory gate follows same pattern as repair kit (Crafting) and disguise kit (Deception) — conditional on dc-cr-equipment-system. Dying condition read/write requires dc-cr-conditions (in-progress Release B). Treat Wounds 1-hour cooldown timer follows same pattern as Coerce immunity timer (dc-cr-skills-diplomacy-actions).
**Dependency note:** dc-cr-conditions (in-progress Release B) — dying condition read/write, persistent bleeding, disease/poison save modifiers. dc-cr-equipment-system — healer's tools inventory check.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Medicine action business logic: action costs, proficiency gates, tool gates, modes (Stabilize/Stop Bleeding), degrees of success, HP restoration formulas, DC-by-proficiency tiers, cooldown timers, one-per-round limits |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing exploration/encounter handler routes only |

---

## Test Cases

### Administer First Aid

### TC-MED-01 — Administer First Aid costs 2 actions
- **Suite:** module-test-suite
- **Description:** Administer First Aid is an encounter action costing 2 actions.
- **Expected:** action_cost = 2; action_type = encounter.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-MED-02 — Administer First Aid requires Trained Medicine
- **Suite:** module-test-suite
- **Description:** Administer First Aid is blocked for characters with Untrained Medicine.
- **Expected:** medicine_rank = untrained → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-MED-03 — Administer First Aid requires healer's tools
- **Suite:** module-test-suite
- **Description:** Administer First Aid is blocked if healer's tools are not present in inventory.
- **Expected:** healers_tools_available = false → action blocked (or –2 penalty if improvised tools present).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (tool inventory check)

### TC-MED-04 — Administer First Aid without tools: improvised at –2
- **Suite:** module-test-suite
- **Description:** Edge case — if the character has improvised tools (not full healer's tools), the action proceeds with a –2 circumstance penalty. If no tools at all, blocked.
- **Expected:** improvised_tools_present = true → –2 circumstance penalty applied; no_tools = true → action blocked.
- **Notes to PM:** Confirm what constitutes "improvised tools" in the inventory model. Is it a flag on the item, or any non-healer-kit tool type?
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system

### TC-MED-05 — Administer First Aid: Stabilize mode removes dying condition on Success
- **Suite:** module-test-suite
- **Description:** In Stabilize mode, a Success sets the target's dying value to 0 (no longer dying).
- **Expected:** mode = stabilize AND result = success → target.dying = 0.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (dying condition write)

### TC-MED-06 — Administer First Aid Stabilize: Failure reduces dying by 1
- **Suite:** module-test-suite
- **Description:** In Stabilize mode, a Failure decreases the target's dying value by 1 (e.g., dying 3 → dying 2).
- **Expected:** mode = stabilize AND result = failure → target.dying = max(0, current_dying - 1).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (dying condition write)

### TC-MED-07 — Administer First Aid Stabilize: Critical Failure advances dying by 1
- **Suite:** module-test-suite
- **Description:** Failure mode — in Stabilize mode, a Critical Failure increases the target's dying value by 1 (e.g., dying 3 → dying 4). This is distinct from plain Failure.
- **Expected:** mode = stabilize AND result = critical_failure → target.dying = current_dying + 1.
- **Notes to PM:** Confirm behavior when dying reaches 4 (dead threshold) after Crit Fail — does the system immediately apply the "dead" state transition?
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (dying condition write + dead threshold)

### TC-MED-08 — Administer First Aid: Stop Bleeding mode stops persistent bleeding
- **Suite:** module-test-suite
- **Description:** In Stop Bleeding mode, a Success removes the persistent bleeding condition from the target.
- **Expected:** mode = stop_bleeding AND result = success → target.persistent_bleed = 0 (removed).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (persistent bleeding condition)

### TC-MED-09 — Stabilize blocked on non-dying target
- **Suite:** module-test-suite
- **Description:** Edge case — attempting to Stabilize a target that does not have the dying condition is blocked before the check.
- **Expected:** target.dying = 0 → stabilize action blocked; error returned; no check rolled.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (dying condition read)

### TC-MED-10 — Administer First Aid: cannot apply same mode to same target twice in one round
- **Suite:** module-test-suite
- **Description:** Edge case — once Administer First Aid has been attempted for a specific mode (e.g., Stabilize) on a specific target in a round, a second attempt of the same mode on the same target in the same round is blocked.
- **Expected:** first_aid_stabilize_used_this_round[target] = true → blocked; no check; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (round-scoped block logic; does not require condition system for the attempt-tracking flag itself)

---

### Treat Disease

### TC-MED-11 — Treat Disease is a downtime activity (8 hours)
- **Suite:** module-test-suite
- **Description:** Treat Disease is classified as a downtime activity with a time cost of 8 hours (a full rest period).
- **Expected:** action_type = downtime; time_cost = 8 hours.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-MED-12 — Treat Disease requires Trained Medicine and healer's tools
- **Suite:** module-test-suite
- **Description:** Treat Disease is blocked for Untrained Medicine or if healer's tools are absent.
- **Expected:** medicine_rank = untrained → blocked; healers_tools_available = false → blocked.
- **Conditional:** tools check depends on dc-cr-equipment-system; proficiency check immediately activatable
- **Roles covered:** authenticated player

### TC-MED-13 — Treat Disease improves target's next disease save by one degree
- **Suite:** module-test-suite
- **Description:** After successful application, the target's next save against the treated disease is treated as one degree better (e.g., Failure → Success, Success → Critical Success).
- **Expected:** treat_disease_applied = true → next_disease_save_bonus = +1 degree.
- **Notes to PM:** Confirm whether the degree improvement is stored as a flag on the character/disease entity or applied as a numeric modifier to the roll. Automation needs a deterministic check.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (disease save modifier)

### TC-MED-14 — Treat Disease: one application per rest period per disease
- **Suite:** module-test-suite
- **Description:** Edge case — Treat Disease can only be applied once per rest period per disease. A second application on the same disease during the same rest window is blocked.
- **Expected:** treat_disease_used_this_rest[disease_id] = true → second application blocked.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (rest period tracking)

---

### Treat Poison

### TC-MED-15 — Treat Poison costs 1 action
- **Suite:** module-test-suite
- **Description:** Treat Poison is a 1-action encounter action.
- **Expected:** action_cost = 1; action_type = encounter.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-MED-16 — Treat Poison requires Trained Medicine and healer's tools
- **Suite:** module-test-suite
- **Description:** Treat Poison is blocked for Untrained Medicine or if healer's tools are absent.
- **Expected:** medicine_rank = untrained → blocked; healers_tools_available = false → blocked.
- **Conditional:** tools check depends on dc-cr-equipment-system; proficiency check immediately activatable
- **Roles covered:** authenticated player

### TC-MED-17 — Treat Poison improves target's next poison save by one degree
- **Suite:** module-test-suite
- **Description:** After application, the target's next save against the treated poison is one degree better.
- **Expected:** treat_poison_applied = true → next_poison_save_bonus = +1 degree.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (poison save modifier)

### TC-MED-18 — Treat Poison applies to next upcoming save only (one save, one poison)
- **Suite:** module-test-suite
- **Description:** Edge case — Treat Poison applies to the first upcoming poison save only. After that save resolves, the bonus is consumed. It applies to one poison per application (not all active poisons simultaneously).
- **Expected:** treat_poison_applied = true → bonus consumed after next_poison_save; subsequent saves do not carry the bonus.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (poison save modifier scope)

---

### Treat Wounds

### TC-MED-19 — Treat Wounds is an exploration activity (10 minutes)
- **Suite:** module-test-suite
- **Description:** Treat Wounds is classified as an exploration activity with a time cost of 10 minutes.
- **Expected:** action_type = exploration; time_cost = 10 minutes.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-MED-20 — Treat Wounds requires Trained Medicine and healer's tools
- **Suite:** module-test-suite
- **Description:** Treat Wounds is blocked for Untrained Medicine or if healer's tools are absent.
- **Expected:** medicine_rank = untrained → blocked; healers_tools_available = false → blocked.
- **Conditional:** tools check depends on dc-cr-equipment-system; proficiency check immediately activatable
- **Roles covered:** authenticated player

### TC-MED-21 — Treat Wounds DC scales with proficiency rank
- **Suite:** module-test-suite
- **Description:** The DC for Treat Wounds is determined by the healer's proficiency: Trained = DC 15, Expert = DC 20, Master = DC 30, Legendary = DC 40.
- **Expected:** medicine_rank = trained → DC = 15; expert → DC = 20; master → DC = 30; legendary → DC = 40.
- **Notes to PM:** The healer selects which DC tier to attempt (lower DC = lower potential HP). Confirm whether the system auto-selects or requires the player to choose.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (DC table logic; no condition dependency)

### TC-MED-22 — Treat Wounds HP restoration by proficiency rank
- **Suite:** module-test-suite
- **Description:** On Success, HP restored equals: Trained = 2d8, Expert = 2d8+10, Master = 2d8+30, Legendary = 2d8+50.
- **Expected:** result = success AND medicine_rank = trained → hp_restored = 2d8; expert → 2d8+10; master → 2d8+30; legendary → 2d8+50.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (item HP tracking) — actually depends on character HP model; verify with dev whether this requires equipment-system or a separate character-HP module.
- **Notes to PM:** Confirm which module owns character HP (separate from item HP). If character HP is already in dc-cr-character-system, this TC may be immediately activatable.

### TC-MED-23 — Treat Wounds Critical Success: double HP restored
- **Suite:** module-test-suite
- **Description:** On Critical Success, the HP restored is doubled compared to the Success amount for that proficiency tier.
- **Expected:** result = critical_success → hp_restored = 2 × success_amount_for_rank.
- **Roles covered:** authenticated player
- **Conditional:** same dependency as TC-MED-22 (character HP model)

### TC-MED-24 — Treat Wounds Critical Failure: deals 1d8 damage instead of healing
- **Suite:** module-test-suite
- **Description:** Failure mode — on Critical Failure, the target takes 1d8 damage rather than receiving any healing.
- **Expected:** result = critical_failure → target_hp_change = –(1d8_roll); hp_restored = 0; NOT a generic "no healing" result.
- **Roles covered:** authenticated player
- **Conditional:** same dependency as TC-MED-22 (character HP model)

### TC-MED-25 — Treat Wounds: 1-hour cooldown per target
- **Suite:** module-test-suite
- **Description:** After a Treat Wounds attempt, the target cannot benefit from Treat Wounds again for 1 hour. The cooldown is tracked per target, not per healer.
- **Expected:** treat_wounds_cooldown[target_id] active → second Treat Wounds attempt on same target → blocked.
- **Notes to PM:** Confirm whether the cooldown applies after any attempt (success, failure, or crit fail) or only on success. Standard PF2e: cooldown applies after any successful treatment. Please confirm.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (cooldown timer logic; no condition dependency)

### TC-MED-26 — Treat Wounds cooldown is per-target, not per-healer (edge case)
- **Suite:** module-test-suite
- **Description:** Edge case — if Healer A treats Target X, Target X is on cooldown. Healer B attempting to treat Target X during the cooldown window is also blocked. The cooldown belongs to the target.
- **Expected:** target_X.treat_wounds_cooldown = active → any healer attempting Treat Wounds on target_X → blocked; healer_B NOT on cooldown for target_Y (unrelated target).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### ACL regression

### TC-MED-27 — ACL regression: no new routes introduced by Medicine actions
- **Suite:** role-url-audit
- **Description:** Medicine action implementation adds no new HTTP routes; existing exploration/encounter handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Conditional dependency summary

| TC | Dependency feature | Reason conditional |
|---|---|---|
| TC-MED-03 | `dc-cr-equipment-system` | Healer's tools inventory check |
| TC-MED-04 | `dc-cr-equipment-system` | Improvised tools flag and penalty path |
| TC-MED-05 | `dc-cr-conditions` | Dying condition write (dying → 0) |
| TC-MED-06 | `dc-cr-conditions` | Dying condition decrement |
| TC-MED-07 | `dc-cr-conditions` | Dying condition increment + dead threshold |
| TC-MED-08 | `dc-cr-conditions` | Persistent bleeding condition removal |
| TC-MED-09 | `dc-cr-conditions` | Dying condition read (gate check) |
| TC-MED-12 (tools) | `dc-cr-equipment-system` | Healer's tools for Treat Disease |
| TC-MED-13 | `dc-cr-conditions` | Disease save degree modifier |
| TC-MED-14 | `dc-cr-conditions` | Rest-period tracking for disease |
| TC-MED-16 (tools) | `dc-cr-equipment-system` | Healer's tools for Treat Poison |
| TC-MED-17 | `dc-cr-conditions` | Poison save degree modifier |
| TC-MED-18 | `dc-cr-conditions` | Poison save modifier scope |
| TC-MED-20 (tools) | `dc-cr-equipment-system` | Healer's tools for Treat Wounds |
| TC-MED-22 | TBD (character HP model) | HP restoration write |
| TC-MED-23 | TBD (character HP model) | HP doubled on Crit Success |
| TC-MED-24 | TBD (character HP model) | HP damage on Crit Fail |

Immediately activatable (no external dependency): TC-MED-01, TC-MED-02, TC-MED-10, TC-MED-11, TC-MED-12 (proficiency gate only), TC-MED-15, TC-MED-16 (proficiency gate only), TC-MED-19, TC-MED-20 (proficiency gate only), TC-MED-21, TC-MED-25, TC-MED-26, TC-MED-27 = **13 TCs**.
14 TCs conditional on dc-cr-conditions (9), dc-cr-equipment-system (5), or character HP module (3 — TBD).

---

## Notes to PM

1. **TC-MED-04 (improvised tools):** Define what constitutes "improvised tools" in the data model. Is it a separate item type flag, or any non-healer-kit item in the tool slot?
2. **TC-MED-07 (Crit Fail dying threshold):** Confirm whether dying 4 after a Crit Fail triggers an immediate dead-state transition in the same resolution step.
3. **TC-MED-13/14 (Treat Disease degree improvement):** Confirm storage model for the degree improvement bonus (flag vs numeric modifier). Also confirm what counts as a "rest period" boundary (8-hour period, long rest event, GM trigger?).
4. **TC-MED-21 (DC tier selection):** Confirm whether the healer selects a DC tier at action time (choosing to attempt a harder DC for better HP restoration) or whether the system auto-assigns the DC based on proficiency rank. Standard PF2e: player chooses, capped at their proficiency.
5. **TC-MED-22/23/24 (character HP model):** Confirm which module owns character HP (separate from item HP in dc-cr-equipment-system). If dc-cr-character-system already exists and owns HP, these 3 TCs are immediately activatable. This is a new dependency not seen in prior skills batches — flagging for PM awareness.
6. **TC-MED-25 (cooldown trigger):** Confirm whether the 1-hour Treat Wounds cooldown applies after any attempt (including failures) or only after a successful treatment.
7. **Staging recommendation:** 13 TCs activatable at Stage 0. The most valuable immediately-activatable TCs are: proficiency/tool gates, action-cost classification, DC table, cooldown logic (TC-MED-25/26). The character HP dependency (TC-MED-22/23/24) is a new module not previously identified — PM should confirm the owning module before Dev implements Treat Wounds outcomes.
