# Test Plan Design: dc-gmg-hazards

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:58:42+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-gmg-hazards/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-gmg-hazards "<brief summary>"
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
- Agent: qa-dungeoncrawler
- Status: pending
