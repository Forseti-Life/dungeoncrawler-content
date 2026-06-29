# Suite Activation: dc-gmg-npc-gallery

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-14T20:35:42+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-gmg-npc-gallery"`**  
   This links the test to the living requirements doc at `features/dc-gmg-npc-gallery/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-gmg-npc-gallery-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-gmg-npc-gallery",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-gmg-npc-gallery"`**  
   Example:
   ```json
   {
     "id": "dc-gmg-npc-gallery-<route-slug>",
     "feature_id": "dc-gmg-npc-gallery",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-gmg-npc-gallery",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-gmg-npc-gallery

## Coverage summary
- AC items: ~17 (prebuilt stat blocks, Elite/Weak templates, GM usage, integration, edge cases)
- Test cases: 9 (TC-NPC-01–09)
- Suites: playwright (character creation, encounter)
- Security: AC exemption granted (no new routes)

---

## TC-NPC-01 — NPC Gallery: prebuilt entries with level range and archetype tag
- Description: Gallery includes Guard, Soldier, Bandit, Merchant, Assassin, Informant, Noble, Cultist, Innkeeper, Sailor; each has a level range; tagged with "NPC" archetype classifier
- Suite: playwright/character-creation
- Expected: all 10 archetypes present; each has level_range field; NPC tag visible in creature selector
- AC: Gallery-1–4

## TC-NPC-02 — NPC stat blocks follow standard creature stat block schema
- Description: NPC Gallery entries use the same stat block schema as Bestiary creatures
- Suite: playwright/encounter
- Expected: NPC stat blocks render identically to bestiary entries; HP, AC, saves, actions all present
- AC: Gallery-4

## TC-NPC-03 — Elite template: +2 modifiers, +HP by tier
- Description: Elite overlay: +2 to all modifiers, attack bonus, DC, saves; +HP by level tier; base stat block unchanged
- Suite: playwright/encounter
- Expected: elite flag recalculates all modifiers +2; HP increases per tier table; base stats preserved (non-destructive overlay)
- AC: EliteWeak-1

## TC-NPC-04 — Weak template: –2 modifiers, –HP by tier
- Description: Weak overlay: –2 to all modifiers, attack bonus, DC, saves; –HP by level tier
- Suite: playwright/encounter
- Expected: weak flag recalculates all modifiers –2; HP decreases per tier table
- AC: EliteWeak-2

## TC-NPC-05 — Elite/Weak overlay is non-destructive
- Description: Applying Elite/Weak does not alter the base stat block; removing overlay restores original values
- Suite: playwright/encounter
- Expected: toggle Elite on → modified stats; toggle off → original stats restored exactly
- AC: EliteWeak-3

## TC-NPC-06 — GM scene setup: NPC selection and rename
- Description: GM can select Gallery NPC from creature selector during scene setup; rename NPC without altering mechanical stats; rename persists in session log
- Suite: playwright/encounter
- Expected: creature selector shows NPC Gallery entries with NPC filter; custom name saves; mechanical stats unchanged
- AC: GMUsage-1–3

## TC-NPC-07 — NPC HP/condition/action management identical to standard creatures
- Description: HP tracking, condition tracking, and action management function identically for NPC Gallery entries
- Suite: playwright/encounter
- Expected: NPC HP bar, conditions, and actions work the same as bestiary creatures
- AC: GMUsage-4

## TC-NPC-08 — NPC Gallery searchable by level, archetype tag, alignment
- Description: Creature selector filters NPC Gallery by level range, archetype tag, and alignment
- Suite: playwright/encounter
- Expected: filter by "NPC" tag returns only Gallery entries; level range filter; alignment filter all function
- AC: Integration-4

## TC-NPC-09 — Edge: Elite + Weak mutually exclusive; rename identity persists
- Description: Applying both Elite and Weak simultaneously is disallowed; renamed NPC identity preserved in encounter history
- Suite: playwright/encounter
- Expected: applying second template when first active → blocked with error; custom name stored in encounter log with stat block identity
- AC: Edge-1–2

### Acceptance criteria (reference)

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
