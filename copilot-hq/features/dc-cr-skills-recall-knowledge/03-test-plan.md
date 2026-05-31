# Test Plan: dc-cr-skills-recall-knowledge

**Owner:** qa-dungeoncrawler
**Date:** 2026-04-07
**Feature:** Recall Knowledge (all skill routings) + Occultism and Religion skill actions
**KB reference:** None found specific to Recall Knowledge mechanics. The "secret check" model (player does not see true degree result on Crit Fail) is a novel pattern in this batch — no prior TC in the CR skills suite has tested result obfuscation. The Identify Magic wrong-tradition +5 DC penalty cross-references the dc-cr-dc-rarity-spell-adjustment module (same DC-modifier pipeline used by rarity adjustments). The Decipher Writing routing for Occultism and Religion is a domain-scope extension of the dc-cr-skill-system Decipher Writing base; treat as additive to that feature's test surface.
**Dependency note:** dc-cr-skill-system ✓ (in scope). dc-cr-creature-identification: Recall Knowledge creature/hazard DCs are level-based — TCs asserting level-based DC lookup are conditional on this module. dc-cr-dc-rarity-spell-adjustment: rarity DC adjustment and wrong-tradition +5 DC penalty go through this pipeline — TCs asserting those modifiers are conditional on this module.

---

## Suite assignments

| Suite | Type | Description |
|---|---|---|
| `module-test-suite` | PHPUnit functional | All Recall Knowledge business logic: action cost/secret flag, 4 degrees (with Crit Fail obfuscation), skill routing by topic, DC resolution (GM-set, level-based, rarity-adjusted), Occultism/Religion Decipher Writing domain scope, Occultism/Religion Identify Magic tradition routing, Religion/Occultism Learn a Spell routing, wrong-tradition +5 DC, Crit Fail false-info mask |
| `role-url-audit` | HTTP role audit | ACL regression — no new routes per security AC exemption; existing encounter handler routes only |

---

## Test Cases

### Recall Knowledge — Action and Secret Check Model

### TC-RK-01 — Recall Knowledge: 1-action cost, secret check flag
- **Suite:** module-test-suite
- **Description:** Recall Knowledge costs 1 action and is a secret check (the system rolls and resolves the outcome; the player does not see the raw roll result). The secret flag must be stored on the action definition.
- **Expected:** action_cost = 1; action.secret = true; raw roll result not exposed in player-facing output.
- **Notes to PM:** Confirm how "secret check" is modeled in the system — is the secret flag on the action entity, or does the GM/system receive the result and the player only receives the outcome message? Automation needs to verify the player cannot inspect the roll value directly.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-02 — Recall Knowledge Critical Success: accurate info + bonus detail returned
- **Suite:** module-test-suite
- **Description:** On Critical Success, the system returns accurate information about the topic AND an additional bonus detail (extra lore, weakness, special trait, etc.).
- **Expected:** result.degree = critical_success → result.accurate = true AND result.bonus_detail is non-empty.
- **Notes to PM:** Confirm how bonus detail is structured (free-text field, a tagged extra-info record, or a randomly selected trait from a pool). Automation needs a deterministic non-null assertion.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-03 — Recall Knowledge Success: accurate info returned, no bonus detail
- **Suite:** module-test-suite
- **Description:** On Success, the system returns accurate information about the topic. No bonus detail is added.
- **Expected:** result.degree = success → result.accurate = true; result.bonus_detail is null or empty.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-04 — Recall Knowledge Failure: no information returned
- **Suite:** module-test-suite
- **Description:** On Failure, the system returns nothing. No accurate info, no false info.
- **Expected:** result.degree = failure → result.info is null or empty.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-05 — Recall Knowledge Critical Failure: false info returned, player output appears truthful
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — on Critical Failure, the system returns false information. The player-facing output must present this as if it were a success ("you recall…") — it must NOT be flagged as false, uncertain, or error.
- **Expected:** result.degree = critical_failure → result.info is non-empty (false data); player_facing_message does NOT contain "false", "wrong", "failed", or equivalent failure indicator; player_facing_message format matches Success degree output format.
- **Notes to PM:** Confirm whether false info is stored as a flag on the result (is_false = true, hidden from player) or as a completely separate result code. Automation needs to assert the player-facing message text pattern, not the internal result code.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-06 — Recall Knowledge untrained: action is permitted
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — Recall Knowledge does NOT require Trained proficiency. An untrained character may attempt it. The system must not block the action based on proficiency rank.
- **Expected:** character.skill_rank = untrained → Recall Knowledge allowed; result is resolved (not blocked).
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Recall Knowledge — Skill Routing

### TC-RK-07 — Skill routing: Arcana → arcane creatures/magic/planes
- **Suite:** module-test-suite
- **Description:** When the topic is arcane creatures, arcane magic, or planar knowledge, the system routes Recall Knowledge to the Arcana skill.
- **Expected:** topic.category ∈ {arcane_creatures, arcane_magic, planes} → check uses Arcana modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-08 — Skill routing: Crafting → item construction/artifice
- **Suite:** module-test-suite
- **Description:** When the topic is item construction or artifice, the system routes Recall Knowledge to the Crafting skill.
- **Expected:** topic.category ∈ {item_construction, artifice} → check uses Crafting modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-09 — Skill routing: Lore → specific subcategory
- **Suite:** module-test-suite
- **Description:** When the topic matches a Lore subcategory the character has, the system routes Recall Knowledge to that Lore skill.
- **Expected:** topic.category = lore_subcategory AND character.has_matching_lore = true → check uses Lore modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-10 — Skill routing: Medicine → diseases/poisons/anatomy
- **Suite:** module-test-suite
- **Description:** When the topic is diseases, poisons, or anatomy, the system routes Recall Knowledge to the Medicine skill.
- **Expected:** topic.category ∈ {diseases, poisons, anatomy} → check uses Medicine modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-11 — Skill routing: Nature → animals/plants/terrain
- **Suite:** module-test-suite
- **Description:** When the topic is animals, plants, or terrain, the system routes Recall Knowledge to the Nature skill.
- **Expected:** topic.category ∈ {animals, plants, terrain} → check uses Nature modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-12 — Skill routing: Occultism → metaphysics/weird philosophies/occult magic
- **Suite:** module-test-suite
- **Description:** When the topic is metaphysics, weird philosophies, or occult magic, the system routes Recall Knowledge to the Occultism skill.
- **Expected:** topic.category ∈ {metaphysics, weird_philosophies, occult_magic} → check uses Occultism modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-13 — Skill routing: Religion → deities/undead/divine magic
- **Suite:** module-test-suite
- **Description:** When the topic is deities, undead, or divine magic, the system routes Recall Knowledge to the Religion skill.
- **Expected:** topic.category ∈ {deities, undead, divine_magic} → check uses Religion modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-14 — Skill routing: Society → cultures/laws/humanoid organizations
- **Suite:** module-test-suite
- **Description:** When the topic is cultures, laws, or humanoid organizations, the system routes Recall Knowledge to the Society skill.
- **Expected:** topic.category ∈ {cultures, laws, humanoid_organizations} → check uses Society modifier.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Recall Knowledge — DC Resolution

### TC-RK-15 — DC resolution: GM-set simple DC for obscurity-based topics
- **Suite:** module-test-suite
- **Description:** For topics with GM-set DCs (obscurity-based, not creature/hazard), the system uses the DC stored on the topic entity (not a level-based formula).
- **Expected:** topic.dc_source = gm_set → check_dc = topic.dc_value (no level formula applied).
- **Notes to PM:** Confirm how GM-set DCs are stored on topic entities (static field on the topic/knowledge node, or a per-encounter override). Automation needs a fixture mechanism to set the GM DC.
- **Roles covered:** authenticated player (GM-set DC storage: admin/GM)
- **Status:** immediately activatable (fixture assertion; no creature-identification module required)

### TC-RK-16 — DC resolution: level-based DC for creatures and hazards
- **Suite:** module-test-suite
- **Description:** For creature and hazard topics, the check DC is derived from the creature/hazard level using the level-based DC table.
- **Expected:** topic.type ∈ {creature, hazard} → check_dc = level_based_dc(topic.level).
- **Notes to PM:** Confirm whether the level-to-DC lookup table is defined in dc-cr-creature-identification or dc-cr-dc-rarity-spell-adjustment (or a shared utility). Automation needs to assert the exact DC value per creature level for at least 2-3 representative levels.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-creature-identification (creature/hazard level field + DC table)

### TC-RK-17 — DC resolution: rarity adjustment applied on top of base DC
- **Suite:** module-test-suite
- **Description:** Rarity adjustment (from dc-cr-dc-rarity-spell-adjustment) is applied to the base DC before the check. Common = no adjustment; Uncommon, Rare, Unique = increasing DC modifier.
- **Expected:** topic.rarity = common → dc = base_dc; topic.rarity = uncommon → dc = base_dc + rarity_modifier; topic.rarity = rare → dc = base_dc + rare_modifier; topic.rarity = unique → dc = base_dc + unique_modifier.
- **Notes to PM:** Confirm that Recall Knowledge DCs use the same rarity adjustment pipeline as spell/item identification. If not, note where the separate lookup is defined.
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-dc-rarity-spell-adjustment (rarity DC modifier pipeline)

---

### Occultism — Decipher Writing, Identify Magic, Learn a Spell

### TC-RK-18 — Occultism Decipher Writing: covers metaphysics, syncretic principles, weird philosophies
- **Suite:** module-test-suite
- **Description:** When using Decipher Writing (base action from dc-cr-skill-system), Occultism covers metaphysics, syncretic principles, and weird philosophies as valid writing categories.
- **Expected:** writing.category ∈ {metaphysics, syncretic_principles, weird_philosophies} → Occultism Decipher Writing allowed; check uses Occultism modifier.
- **Notes to PM:** Confirm whether "syncretic principles" is a distinct category in the writing-type taxonomy or a subset of metaphysics. Automation needs a deterministic category value.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-19 — Occultism Identify Magic: routes to occult tradition items/effects
- **Suite:** module-test-suite
- **Description:** When using Identify Magic (base action from dc-cr-skill-system), Occultism correctly routes to occult tradition items and effects (not divine, arcane, or primal).
- **Expected:** magic_item.tradition = occult → Identify Magic uses Occultism modifier at base DC; result resolves correctly.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-20 — Occultism Learn a Spell: routes to occult tradition spells
- **Suite:** module-test-suite
- **Description:** When using Learn a Spell (base action from dc-cr-skill-system), Occultism correctly routes to occult tradition spells.
- **Expected:** spell.tradition = occult → Learn a Spell uses Occultism modifier; check proceeds.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Religion — Decipher Writing, Identify Magic, Learn a Spell

### TC-RK-21 — Religion Decipher Writing: covers religious allegories, homilies, proverbs
- **Suite:** module-test-suite
- **Description:** When using Decipher Writing (base action from dc-cr-skill-system), Religion covers religious allegories, homilies, and proverbs as valid writing categories.
- **Expected:** writing.category ∈ {religious_allegory, homily, proverb} → Religion Decipher Writing allowed; check uses Religion modifier.
- **Notes to PM:** Confirm whether "homily" and "proverb" are distinct taxonomy values or grouped under a single "religious_text" category. Automation needs deterministic category values.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-22 — Religion Identify Magic: routes to divine tradition items/effects
- **Suite:** module-test-suite
- **Description:** When using Identify Magic (base action from dc-cr-skill-system), Religion correctly routes to divine tradition items and effects.
- **Expected:** magic_item.tradition = divine → Identify Magic uses Religion modifier at base DC; result resolves correctly.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

### TC-RK-23 — Religion Learn a Spell: routes to divine tradition spells
- **Suite:** module-test-suite
- **Description:** When using Learn a Spell (base action from dc-cr-skill-system), Religion correctly routes to divine tradition spells.
- **Expected:** spell.tradition = divine → Learn a Spell uses Religion modifier; check proceeds.
- **Roles covered:** authenticated player
- **Status:** immediately activatable

---

### Edge Cases and Failure Modes

### TC-RK-24 — Wrong tradition Identify Magic: +5 DC penalty applied, NOT blocked
- **Suite:** module-test-suite
- **Description:** Failure mode (TEST-ONLY) — when a character uses a skill from the wrong tradition to Identify Magic (e.g., using Arcana to identify a divine item), the system must apply a +5 DC penalty — it must NOT block the action entirely. The penalty goes through the dc-cr-dc-rarity-spell-adjustment pipeline.
- **Expected:** skill.tradition ≠ item.tradition → dc = base_dc + 5; action_allowed = true (not blocked).
- **Notes to PM:** Confirm that "wrong tradition" means any mismatch, or only specific pairings. Automation needs: (a) at least one cross-tradition fixture (e.g., Arcana vs divine item), and (b) confirmation that the +5 modifier is applied by dc-cr-dc-rarity-spell-adjustment (not hardcoded in the skill handler).
- **Roles covered:** authenticated player
- **Conditional:** depends on dc-cr-dc-rarity-spell-adjustment (+5 wrong-tradition modifier pipeline)

### TC-RK-25 — ACL regression: no new routes; existing encounter handler routes retain ACL
- **Suite:** role-url-audit
- **Description:** Per the security AC exemption, Recall Knowledge/Occultism/Religion implementation adds no new HTTP routes. Existing encounter handler routes must retain their ACL.
- **Expected:** existing routes: HTTP 200 for authenticated player; HTTP 403/redirect for anonymous.
- **Roles covered:** anonymous, authenticated player, admin
- **Status:** immediately activatable

---

## Dependency summary

| TC | Dependency | Reason conditional |
|---|---|---|
| TC-RK-16 | dc-cr-creature-identification | Level-based DC lookup for creature/hazard topics |
| TC-RK-17 | dc-cr-dc-rarity-spell-adjustment | Rarity DC modifier pipeline |
| TC-RK-24 | dc-cr-dc-rarity-spell-adjustment | Wrong-tradition +5 DC modifier pipeline |

22 TCs immediately activatable.
3 TCs conditional on dc-cr-creature-identification or dc-cr-dc-rarity-spell-adjustment.

---

## Notes to PM

1. **TC-RK-01 (secret check model):** Confirm how the system implements the secret check — does the GM/system receive the true result and the player only receives an outcome message, or is the roll hidden via a flag? Automation needs to verify the player cannot inspect the raw roll result.
2. **TC-RK-02 (bonus detail structure):** Confirm how Crit Success bonus detail is structured (free-text field, tagged extra-info record, random trait from pool). Automation needs a non-null assertion strategy.
3. **TC-RK-05 (false info player message format):** Confirm the exact player-facing message format for Crit Fail false info — does it mirror the Success message template exactly? Provide a string template if possible. This is critical for the obfuscation assertion.
4. **TC-RK-07–14 (skill routing taxonomy):** Confirm the canonical topic category taxonomy (enum values for routing). Automation needs exact string/enum values for each routing category.
5. **TC-RK-15 (GM-set DC storage):** Confirm how GMs set per-topic DCs — static field on the topic entity, per-encounter override, or inline on the Recall Knowledge event. Automation needs a fixture mechanism.
6. **TC-RK-16 (level-based DC table location):** Confirm whether the level-to-DC table lives in dc-cr-creature-identification, dc-cr-dc-rarity-spell-adjustment, or a shared utility. Automation needs to know which service to query.
7. **TC-RK-18/21 (Decipher Writing taxonomy values):** Confirm exact category strings for "syncretic principles" (Occultism) and "homily"/"proverb" (Religion) in the writing-type taxonomy.
8. **TC-RK-24 (wrong-tradition scope):** Confirm whether wrong-tradition penalty applies to all tradition mismatches or only specific pairings (e.g., divine ↔ occult but not arcane ↔ primal). Confirm +5 is routed through dc-cr-dc-rarity-spell-adjustment, not hardcoded.
