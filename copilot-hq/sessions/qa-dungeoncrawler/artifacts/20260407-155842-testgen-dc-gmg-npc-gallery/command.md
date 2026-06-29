# Test Plan Design: dc-gmg-npc-gallery

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T15:58:42+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-gmg-npc-gallery/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-gmg-npc-gallery "<brief summary>"
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

# Acceptance Criteria: GMG NPC Gallery System

## Feature: dc-gmg-npc-gallery
## Source: PF2E Gamemastery Guide, Chapter 2 (sub-feature of dc-gmg-hazards)

---

## NPC Gallery Prebuilt Stat Blocks

- [ ] NPC Gallery entries are prebuilt creature stat blocks tagged with an NPC archetype classifier
- [ ] Gallery includes common archetypes: Guard, Soldier, Bandit, Merchant, Assassin, Informant, Noble, Cultist, Innkeeper, Sailor
- [ ] Each gallery entry has a level range (e.g., 1, 3, 5, 7...) for encounter-building selection
- [ ] NPC stat blocks follow the standard creature stat block format (same schema as Bestiary creatures)

## Elite and Weak Adjustments

- [ ] Elite template: +2 to all modifiers, attack bonus, DC, saves; +HP based on level tier
- [ ] Weak template: –2 to all modifiers, attack bonus, DC, saves; –HP based on level tier
- [ ] Elite/Weak applies as a modifier overlay — base stat block unchanged
- [ ] Elite/Weak adjustments stack with the standard creature level adjustment rules

## GM Usage

- [ ] GM can select a Gallery NPC from creature selector during scene setup
- [ ] Gallery NPCs can be used as allies, enemies, or neutral parties
- [ ] Selected NPCs can be renamed and given custom descriptions without altering mechanical stats
- [ ] HP tracking, condition tracking, and action management function identically to standard creatures

## Integration Checks

- [ ] NPC Gallery entries appear in creature selector alongside Bestiary creatures (filterable by "NPC" tag)
- [ ] Elite/Weak overlay correctly recalculates all derived statistics at point of application
- [ ] Depends on dc-cr-npc-system being implemented; if not yet active, Gallery entries use creature framework as fallback

## Edge Cases

- [ ] Elite + Weak applied simultaneously: disallowed (mutually exclusive templates)
- [ ] Custom-renamed NPC: rename persists in session log and encounter history; does not affect stat block identity
- Agent: qa-dungeoncrawler
- Status: pending
