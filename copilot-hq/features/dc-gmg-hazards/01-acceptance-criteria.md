# Acceptance Criteria: GMG Hazards and NPC Gallery

## Feature: dc-gmg-hazards (covers GMG ch02 — Hazards and GM Tools)
## Feature: dc-gmg-npc-gallery (sub-feature; umbrella covered here)
## Source: PF2E Game Master's Guide, Chapter 2

---

## GM Tooling Framework (ch02 Baseline Requirements)

- [ ] GM tooling exposes configurable policies for adjudication, pacing, and scenario construction
- [ ] Subsystem framework supports pluggable mechanics with explicit setup, turn flow, and resolution states
- [ ] Variant rules are feature-flagged with compatibility checks against baseline campaign assumptions
- [ ] Encounter/adventure/map planning artifacts preserve traceability between scene intent and mechanics

---

## Hazard System Integration

- [ ] Hazards have complete stat blocks: Stealth/Disable/AC/Saves/HP/Hardness/Immunities/Weaknesses/Resistances/Actions
- [ ] Hazard Stealth DC used for initial detection; Perception check vs. Stealth DC determines awareness
- [ ] Hazard Disable skill DC used for disarming via applicable skill (Thievery, Arcana, etc.)
- [ ] Simple hazards: resolve in one action or trigger; no initiative required
- [ ] Complex hazards: join initiative when triggered; take their own turn(s)
- [ ] Hazards can be complex traps, environmental hazards, or haunts (all follow the same framework)
- [ ] Haunt hazards: not destroyed until underlying supernatural condition is resolved; deactivation is temporary
- [ ] Disabled vs. Destroyed: disabled = inactive until reset; destroyed = removed permanently
- [ ] Reset behavior: some hazards reset after a time interval; others require manual reset
- [ ] Hazard level and XP: awards Experience Points like creatures when overcome (disabled or destroyed)
- [ ] APG hazards (Engulfing Snare, etc.) loaded into the hazard catalog alongside GMG hazards

---

## NPC Gallery Integration

- [ ] NPC stat blocks follow the standard creature stat block format with NPC archetype tag
- [ ] NPC Gallery entries are pre-built stat blocks representing common archetypes (guard, merchant, assassin, etc.)
- [ ] NPCs can be used as allies, enemies, or neutral parties without modification
- [ ] GM can quickly assign an NPC Gallery entry to a scene via creature selector
- [ ] NPC Gallery entries classified by level range for encounter building

---

## Integration Notes (ch02)

- [ ] Integration points from GMG ch02 map to existing core rules without introducing duplicate semantics
- [ ] Conflicts between chapter-specific and core rules resolved through explicit precedence notes in implementation docs
- [ ] Hazard detection integrates with existing Perception and Seek rules
- [ ] Hazard damage applies through existing damage pipeline (typed damage, resistances, immunities respected)

---

## Integration Checks

- [ ] Complex hazard joins initiative tracker when triggered; has own turn with defined actions
- [ ] Haunt hazard UI: shows "deactivated" state (not destroyed) after disable action; re-activates on next trigger
- [ ] Hazard XP award fires when hazard is disabled or destroyed (same trigger as creature death)
- [ ] NPC Gallery entries searchable by level, archetype tag, and alignment

## Edge Cases

- [ ] Hazard triggered before being detected: initiative is rolled for the hazard; PC awareness state depends on Perception vs. Stealth result at trigger point
- [ ] Hazard reset: if a character stands in the reset area when reset triggers, hazard re-activates and may retrigger
- [ ] NPC used as ally: NPC stat block should remain editable for temporary boosts (companion NPC HP tracking)
