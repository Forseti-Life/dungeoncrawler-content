# Test Plan: dc-cr-skills-nature-command-animal

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Nature skill — Recall Knowledge (Nature), Command an Animal
**KB reference:** Command an Animal panic behavior (Crit Fail) follows the same pattern as Demoralize frightened condition output; no direct condition module dependency but requires an NPC/creature behavior model for panic implementation. Recall Knowledge (Nature) shares resolution logic with Arcana/Religion/Occultism pattern.
**Dependency note:** No formal external module dependency beyond dc-cr-skill-system (already ✓). Command an Animal outcome TCs that produce ongoing cooperative state or panic behavior depend on a creature/NPC behavior model (unnamed — see PM flag). All other TCs immediately activatable.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Nature/Command an Animal business logic: RK domain scope, untrained RK, proficiency gate, creature-type gate, action cost, DC resolution, 4 degrees, auditory-trait edge case, lower-DC for bonded animals |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes; existing encounter handler routes only |

---

## Test Cases

### Recall Knowledge (Nature)

### TC-NAT-01 — Recall Knowledge (Nature): domain covers animals, beasts, fey, plants, fungi, weather, terrain
- **Suite:** module-test-suite
- **Description:** A Recall Knowledge check using Nature resolves for queries within Nature's knowledge domain (animals, beasts, fey, plants, fungi, weather, terrain, natural environments, creature categories from nature). Queries outside this domain are out-of-scope.
- **Expected:** query_domain IN [animals, beasts, fey, plants, fungi, weather, terrain, natural_environments] → check resolves; query_domain NOT IN that list → out-of-scope response.
- **Notes to PM:** Confirm the authoritative creature category list for Nature (especially: does "magical beast" count under beasts here? AC says non-magical beast is blocked for Command an Animal but does not restrict Recall Knowledge). Automation needs an enumerated domain list.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-NAT-02 — Recall Knowledge (Nature): untrained use permitted
- **Suite:** module-test-suite
- **Description:** A character with Untrained proficiency in Nature may use Recall Knowledge (Nature) for any Nature-domain query. The untrained modifier applies to the check.
- **Expected:** nature_rank = untrained AND query_domain IN nature_scope → check allowed; rolls at untrained modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Command an Animal

### TC-NAT-03 — Command an Animal: 1-action cost, auditory trait, Trained gate
- **Suite:** module-test-suite
- **Description:** Command an Animal costs 1 action, has the auditory trait, and is blocked for characters with Untrained Nature.
- **Expected:** action_cost = 1; traits = [auditory]; nature_rank = untrained → action blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-NAT-04 — Command an Animal: target must be an animal type
- **Suite:** module-test-suite
- **Description:** Command an Animal only works on creature-type = "animal." Targeting a magical beast, undead, or mindless creature is blocked.
- **Expected:** target.creature_type = animal → action allowed; target.creature_type IN [magical_beast, undead, mindless] → action blocked; error returned.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-NAT-05 — Command an Animal: command must be simple and within animal capabilities
- **Suite:** module-test-suite
- **Description:** The command issued must be a simple command within an animal's capabilities (e.g., "move here," "attack," "stay"). Complex or impossible commands (e.g., "open the locked door") are rejected before the check.
- **Expected:** command_type = simple_animal_capability → check proceeds; command_type = complex_or_impossible → blocked before check.
- **Notes to PM:** Confirm how command complexity/capability is validated in the data model. Options: (a) enumerated command set, (b) free-text with a capability flag on the animal's stat block. Automation needs a deterministic gate.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (gate logic independent of creature model outcome handling)

### TC-NAT-06 — Command an Animal DC = target animal's Will save
- **Suite:** module-test-suite
- **Description:** The DC for Command an Animal is the Will save DC of the target animal (not a fixed DC or skill DC). Untrained animals use a default passive DC.
- **Expected:** dc = target_animal.will_save_dc; untrained_animal = true → dc = passive_default_dc.
- **Notes to PM:** Confirm what the "default passive DC" is for untrained animals (absolute value vs formula). Automation needs a deterministic fallback value.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-NAT-07 — Command an Animal Critical Success: two actions this turn + cooperative 1 minute
- **Suite:** module-test-suite
- **Description:** On Critical Success, the animal performs up to two actions on the commander's turn AND enters a cooperative state for 1 minute (subsequent commands have lower DC or auto-succeed during that window).
- **Expected:** result = critical_success → animal.actions_granted_this_turn = 2; animal.cooperative_state_duration = 1 minute.
- **Notes to PM:** Confirm how "cooperative for 1 minute" is stored (NPC state flag with timer vs repeated lower-DC commands). If it requires an NPC state model, these TCs are conditional.
- **Roles covered:** authenticated player
- **Conditional:** depends on NPC/creature behavior model (cooperative state timer)

### TC-NAT-08 — Command an Animal Success: one action this round
- **Suite:** module-test-suite
- **Description:** On Success, the animal performs one action on the commander's current turn.
- **Expected:** result = success → animal.actions_granted_this_round = 1.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (single-turn outcome; no persistent state required)

### TC-NAT-09 — Command an Animal Failure: no action
- **Suite:** module-test-suite
- **Description:** On Failure, the animal performs no action. No panic, no compliance.
- **Expected:** result = failure → animal.actions_granted = 0; animal.panic = false.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-NAT-10 — Command an Animal Critical Failure: animal panics (runs or attacks)
- **Suite:** module-test-suite
- **Description:** Failure mode — on Critical Failure, the animal enters a panic state: it either flees or attacks the nearest creature (implementation choice). The system must record this as a panic behavior, not as a simple failure.
- **Expected:** result = critical_failure → animal.state = panic; animal.behavior IN [flee, attack_nearest]; animal.actions_granted = 0.
- **Notes to PM:** Confirm whether panic produces "flee" or "attack nearest" — and whether it is a system choice, random, or animal-specific. Automation needs a deterministic expected value.
- **Roles covered:** authenticated player
- **Conditional:** depends on NPC/creature behavior model (panic state implementation)

### TC-NAT-11 — Command an Animal: trained/bonded animals use lower DC
- **Suite:** module-test-suite
- **Description:** Animals with a trained/bonded status (from Handle Animal, Animal Companion, or similar rules) use a reduced DC compared to wild/untrained animals of the same type.
- **Expected:** animal.bonded = true → dc < animal.will_save_dc (reduced value); animal.bonded = false → dc = animal.will_save_dc.
- **Notes to PM:** Confirm the exact DC reduction for bonded animals (flat modifier vs separate DC table). Automation needs a deterministic value.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (DC lookup; no behavior model needed for the DC selection itself)

### TC-NAT-12 — Command an Animal: non-animal creature blocked
- **Suite:** module-test-suite
- **Description:** Edge case — targeting a beast (magical), undead, or mindless creature with Command an Animal is blocked. Only animal type is valid.
- **Expected:** target.creature_type = magical_beast → blocked; target.creature_type = undead → blocked; target.creature_type = mindless → blocked.
- **Roles covered:** authenticated player
- **Status:** immediately activatable (creature type gate; same as TC-NAT-04 but from the failure-mode perspective with explicit type enumeration)

### TC-NAT-13 — Command an Animal: auditory trait — language barrier or deafness may apply
- **Suite:** module-test-suite
- **Description:** Edge case — Command an Animal has the auditory trait. If the character cannot produce sound (muted) or the animal cannot hear (deaf), the action should be blocked or penalized per implementation policy.
- **Expected:** character.muted = true OR animal.deaf = true → action blocked or penalized (implementation-specific).
- **Notes to PM:** AC says "mute or deaf characters may have penalty/blocked depending on implementation." Please confirm the authoritative behavior: (a) hard block if either party has the auditory-trait barrier, or (b) circumstance penalty applied. Automation needs a deterministic outcome.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-conditions (muted/deaf condition flags if those exist) or implementation decision from PM

---

### ACL regression

### TC-NAT-14 — ACL regression: no new routes introduced by Nature/Command an Animal
- **Suite:** role-url-audit
- **Description:** Nature and Command an Animal implementation adds no new HTTP routes; existing encounter handler routes retain their ACL.
- **Expected:** HTTP 200 for authenticated player on existing encounter handler routes; HTTP 403 for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

| TC | Dependency | Reason conditional |
|---|---|---|
| TC-NAT-07 | NPC/creature behavior model | Cooperative state timer (1 minute) |
| TC-NAT-10 | NPC/creature behavior model | Panic state (flee/attack nearest) |
| TC-NAT-13 | dc-cr-conditions OR PM decision | Muted/deaf condition flags or implementation rule |

11 TCs immediately activatable at Stage 0 (TC-NAT-01/02/03/04/05/06/08/09/11/12/14).
3 TCs conditional.

---

## Notes to PM

1. **TC-NAT-01 (Nature domain list):** Confirm the authoritative creature category list for Recall Knowledge (Nature). Specifically: is "magical beast" covered by RK-Nature for knowledge purposes (even though it's blocked for Command an Animal)? Automation needs an enumerated domain list.
2. **TC-NAT-05 (command capability gate):** Confirm how command complexity/capability is validated: enumerated simple-command set vs free-text with a capability flag on the animal stat block. Automation needs a deterministic gate rule.
3. **TC-NAT-06 (default passive DC):** Confirm the "default passive DC" for untrained/wild animals in Command an Animal (absolute value or formula). Automation needs a deterministic fallback.
4. **TC-NAT-07 (cooperative state model):** Confirm how "cooperative for 1 minute" is stored and checked. If it requires a persistent NPC state model not yet implemented, TC-NAT-07 becomes conditional. If it's a simple modifier flag, it may activate at Stage 0.
5. **TC-NAT-10 (panic behavior):** Confirm whether animal panic produces "flee" or "attack nearest" (or random/animal-specific). Automation needs a deterministic expected value for the panic behavior assertion.
6. **TC-NAT-11 (bonded DC reduction):** Confirm the exact DC reduction for bonded/trained animals (flat modifier vs separate DC table).
7. **TC-NAT-13 (auditory trait policy):** Confirm the authoritative outcome when the auditory trait cannot be met (muted character or deaf animal): hard block or circumstance penalty. This is the only TC with implementation-policy ambiguity in this feature.
8. **NPC/creature behavior module:** TC-NAT-07 and TC-NAT-10 both depend on an NPC behavior model. This module is not named in the current Release B dependency list. Recommend PM confirm whether this is covered by an existing module (dc-cr-npc-system, dc-cr-bestiary) or needs a new stub before these TCs can activate.
