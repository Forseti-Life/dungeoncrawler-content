# Test Plan: dc-cr-skills-thievery-disable-pick-lock

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Thievery skill — Palm an Object, Steal, Disable a Device, Pick a Lock
**KB reference:** None found specific to Thievery mechanics. Thieves' tools tri-state (standard/improvised/none) is structurally similar to healer's tools tri-state in dc-cr-skills-medicine-actions (TC-MED-03/04). Jammed lock state is a persistent entity state — similar pattern to dc-cr-equipment-system item durability.
**Dependency note:** Palm an Object and Steal are immediately activatable on dc-cr-skill-system. Disable a Device and Pick a Lock require dc-cr-equipment-system (thieves' tools inventory gate). Jammed lock state requires a per-lock persistent state model (may be part of dc-cr-equipment-system or a separate encounter object model). Annotated per TC.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Thievery business logic: Palm an Object (hidden item flag), Steal (awareness gate, Crit Fail observer notification), Disable a Device (2-action, Trained gate, tools gate, multi-success complex devices, Crit Fail triggers trap), Pick a Lock (2-action, Trained gate, tools gate, DC by lock quality, improvised +5 DC, Crit Fail jammed) |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing encounter handler routes only |

---

## Test Cases

### Palm an Object

### TC-THI-01 — Palm an Object: 1-action, Manipulation trait, applies to small concealable items
- **Suite:** module-test-suite
- **Description:** Palm an Object costs 1 action, has the Manipulation trait, and applies only to small, easily concealable items. It does not apply to large or obviously visible items.
- **Expected:** action_cost = 1; trait = manipulation; item.size = small → allowed; item.size = large → blocked.
- **Notes to PM:** Confirm how "small, easily concealed" is modeled: item size tier, a concealable flag, or a weight threshold. Automation needs a deterministic gate condition.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-THI-02 — Palm an Object Success: item hidden on person, observers must Seek to notice
- **Suite:** module-test-suite
- **Description:** On Success, the item is considered hidden on the character's person. Passive observers cannot notice it; they must actively Seek to find it.
- **Expected:** result = success → item.palmed = true; passive_detection = false; seek_required = true.
- **Notes to PM:** Confirm whether "palmed" is a flag on the item record or on the character's inventory slot. Automation needs a deterministic data model.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (item flag; no condition state required beyond item model)

---

### Steal

### TC-THI-03 — Steal: 1-action, Manipulation trait
- **Suite:** module-test-suite
- **Description:** Steal costs 1 action, has the Manipulation trait.
- **Expected:** action_cost = 1; trait = manipulation.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-THI-04 — Steal: requires target to be unaware of attempt (Observed to character or distracted)
- **Suite:** module-test-suite
- **Description:** Steal requires the target to be unaware of the theft attempt — either the target is not observing the character, or the target is distracted. An alert target who is watching the character blocks Steal.
- **Expected:** target.awareness_of_character = unaware OR target.distracted = true → Steal allowed; target.awareness_of_character = aware AND target.distracted = false → blocked.
- **Notes to PM:** Confirm how "unaware of attempt" is modeled: is this the target's visibility state (Observed/Unobserved/Hidden from Stealth mechanics) or a separate distraction flag? Automation needs a deterministic gate value.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (gate logic; awareness flag independent of full condition model)

### TC-THI-05 — Steal Success: item transferred from target to character
- **Suite:** module-test-suite
- **Description:** On Success, the small item is removed from the target's inventory and added to the character's inventory without the target noticing.
- **Expected:** result = success → target.inventory no longer contains item; character.inventory contains item; target.awareness = unalerted.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (inventory transfer)

### TC-THI-06 — Steal Critical Failure: target AND nearby observers become aware of attempt
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — on Critical Failure, not just the target but also nearby observers become aware that the character attempted to steal. The awareness is broadcast to the nearby observer set, not just the direct target.
- **Expected:** result = critical_failure → target.aware_of_steal_attempt = true; nearby_observers.each.aware_of_steal_attempt = true; item not transferred.
- **Notes to PM:** Confirm the radius or definition of "nearby observers." Automation needs a deterministic observer set (all creatures in encounter, within N feet, or within line of sight).
- **Roles covered:** authenticated player
- **Status:** immediately activatable (awareness flag; no condition state or equipment system required)

---

### Disable a Device

### TC-THI-07 — Disable a Device: 2-action cost, Manipulation trait, Trained gate
- **Suite:** module-test-suite
- **Description:** Disable a Device costs 2 actions per attempt, has the Manipulation trait, and requires Trained Thievery.
- **Expected:** action_cost = 2; trait = manipulation; thievery_rank = untrained → blocked; thievery_rank >= trained → allowed (subject to tools gate).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-THI-08 — Disable a Device: requires thieves' tools
- **Suite:** module-test-suite
- **Description:** Disable a Device requires thieves' tools in inventory. Without tools, the action is blocked (or improvised with penalty per system policy).
- **Expected:** character.has_thieves_tools = true → action allowed; character.has_thieves_tools = false → blocked or improvised-penalty path.
- **Notes to PM:** AC says "improvised penalty or blocked per GM (system flags)" for Disable Device without tools. Confirm the authoritative system behavior: (a) hard block, or (b) improvised attempt allowed with a +DC penalty (like Pick a Lock +5). Automation needs a deterministic outcome.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (thieves' tools inventory flag)

### TC-THI-09 — Disable a Device: complex devices require multiple successes
- **Suite:** module-test-suite
- **Description:** A complex device (trap, alarm, security mechanism) requires multiple successful Disable a Device checks before it is disabled. Each success advances a progress counter.
- **Expected:** device.complexity = complex → device.successes_required > 1; each success → device.successes_remaining decrements; device.successes_remaining = 0 → device.disabled = true.
- **Notes to PM:** Confirm how device complexity tiers are defined and how the required success count is stored on the device entity. Automation needs deterministic tier values (simple = 1, complex = N).
- **Roles covered:** authenticated player
- **Status:** immediately activatable (counter logic; no equipment system required beyond device entity model)

### TC-THI-10 — Disable a Device Critical Failure: trap/device triggers
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — on Critical Failure, the device is triggered (trap fires, alarm sounds, etc.) rather than simply failing to disable.
- **Expected:** result = critical_failure → device.triggered = true; device.disabled = false.
- **Notes to PM:** Confirm what "triggered" means in the system: does it fire the trap effect immediately, or does it set a triggered flag that the encounter engine processes? Automation needs a deterministic assertion (flag vs immediate effect).
- **Roles covered:** authenticated player
- **Status:** immediately activatable (triggered flag assertion; no equipment system required for the flag itself)

### TC-THI-11 — Disable a Device untrained: blocked
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — attempting Disable a Device with Untrained Thievery is blocked.
- **Expected:** thievery_rank = untrained → blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Pick a Lock

### TC-THI-12 — Pick a Lock: 2-action cost, Manipulation trait, Trained gate
- **Suite:** module-test-suite
- **Description:** Pick a Lock costs 2 actions per attempt, has the Manipulation trait, and requires Trained Thievery.
- **Expected:** action_cost = 2; trait = manipulation; thievery_rank = untrained → blocked; thievery_rank >= trained → allowed (subject to tools gate).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-THI-13 — Pick a Lock: requires thieves' tools; without tools DC increases by 5
- **Suite:** module-test-suite
- **Description:** Pick a Lock requires thieves' tools. Without tools (improvised attempt), the DC increases by 5. This is distinct from Disable a Device — Pick a Lock explicitly allows an improvised attempt (not a hard block).
- **Expected:** character.has_thieves_tools = true → dc = lock_base_dc; character.has_thieves_tools = false → dc = lock_base_dc + 5.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (thieves' tools inventory flag)

### TC-THI-14 — Pick a Lock: DC by lock quality (simple, average, good, superior)
- **Suite:** module-test-suite
- **Description:** The DC for Pick a Lock is determined by the lock's quality tier.
- **Expected:** lock.quality = simple → dc = simple_dc; lock.quality = average → dc = average_dc; lock.quality = good → dc = good_dc; lock.quality = superior → dc = superior_dc.
- **Notes to PM:** Provide the authoritative DC values per lock quality tier. Automation needs deterministic DC values for each of the four tiers.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (lock entity with quality attribute)

### TC-THI-15 — Pick a Lock Success: lock opened
- **Suite:** module-test-suite
- **Description:** On Success, the lock is opened (unlocked state). The lock entity is updated.
- **Expected:** result = success → lock.state = unlocked.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (lock entity state)

### TC-THI-16 — Pick a Lock Critical Failure: lock becomes jammed, no further attempts until repaired
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — on Critical Failure, the lock jams. No further Pick a Lock attempts are allowed on this lock until it is repaired. The jammed state is tracked per lock entity and persists.
- **Expected:** result = critical_failure → lock.state = jammed; retry_allowed = false; lock.jammed = true (persists until repair action clears it).
- **Notes to PM:** Confirm: (a) is "jammed" a lock state enum value, or a separate boolean flag? (b) What action/event clears the jammed state — a Repair action, GM reset, or time? Automation needs a deterministic repair trigger.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (lock entity jammed state)

### TC-THI-17 — Pick a Lock jammed state persists until repaired
- **Suite:** module-test-suite
- **Description:** Edge case — once a lock is jammed (TC-THI-16), repeated Pick a Lock attempts are blocked. The jammed state survives across turns until a repair action clears it.
- **Expected:** lock.state = jammed → Pick a Lock blocked (returns jammed error); lock.state = jammed after turn advance → still blocked; repair_action applied → lock.state = unlocked or normal; pick attempts allowed again.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-equipment-system (lock jammed state persistence + repair action)

---

### ACL regression

### TC-THI-18 — ACL regression: no new routes introduced by Thievery
- **Suite:** role-url-audit
- **Description:** Thievery implementation adds no new HTTP routes; existing encounter handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing encounter handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

| TC | Dependency | Reason conditional |
|---|---|---|
| TC-THI-05 | dc-cr-equipment-system | Inventory transfer (Steal) |
| TC-THI-08 | dc-cr-equipment-system | Thieves' tools inventory flag (Disable) |
| TC-THI-13 | dc-cr-equipment-system | Thieves' tools inventory flag (Pick Lock) |
| TC-THI-14 | dc-cr-equipment-system | Lock entity with quality attribute |
| TC-THI-15 | dc-cr-equipment-system | Lock entity state (unlocked) |
| TC-THI-16 | dc-cr-equipment-system | Lock entity jammed state |
| TC-THI-17 | dc-cr-equipment-system | Lock jammed state persistence + repair |

11 TCs immediately activatable at Stage 0 (TC-THI-01 through TC-THI-04, TC-THI-06 through TC-THI-12, TC-THI-18).
7 TCs conditional on dc-cr-equipment-system.

---

## Notes to PM

1. **TC-THI-01 (small item definition):** Confirm how "small, easily concealed" is modeled for Palm an Object: item size tier, a boolean concealable flag, or a weight threshold. Automation needs a deterministic gate.
2. **TC-THI-02 (palmed item model):** Confirm whether "palmed" is a flag on the item record or an inventory slot property. Data model needed for assertion.
3. **TC-THI-04 (Steal awareness gate):** Confirm how "unaware of attempt" is modeled — is this tied to the Stealth visibility state (Observed/Hidden from dc-cr-conditions) or a separate encounter distraction flag?
4. **TC-THI-06 (Steal Crit Fail observer radius):** Confirm the definition of "nearby observers" for Steal Crit Fail awareness broadcast (all in encounter, within N feet, line of sight). Automation needs a deterministic observer set.
5. **TC-THI-08 (Disable Device — no tools):** Confirm whether Disable a Device without thieves' tools is: (a) hard blocked, or (b) allowed as an improvised attempt with a DC penalty. AC is ambiguous ("improvised penalty or blocked per GM"). Automation needs a deterministic policy.
6. **TC-THI-09 (device complexity):** Provide the authoritative definition of device complexity tiers and their required success counts. Automation needs tier names and success count values.
7. **TC-THI-10 (triggered flag vs immediate effect):** Confirm whether Disable Device Crit Fail sets a triggered flag (processed later) or fires the trap effect immediately. Affects assertion model.
8. **TC-THI-14 (lock DC table):** Provide DC values for simple, average, good, and superior lock quality tiers. Automation needs exact values.
9. **TC-THI-16/17 (jammed lock repair):** Confirm: jammed state is an enum value or boolean flag; and what action clears it (explicit Repair skill/action, GM reset, or time-based). This determines the repair fixture for TC-THI-17.
10. **dc-cr-equipment-system dependency:** 7/18 TCs are conditional on dc-cr-equipment-system (thieves' tools, lock entity, inventory). This is the second-highest equipment dependency in the CR skills batch after Crafting. Recommend PM confirm dc-cr-equipment-system timeline when planning Thievery activation.
