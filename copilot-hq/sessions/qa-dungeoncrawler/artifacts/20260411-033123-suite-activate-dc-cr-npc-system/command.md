- Status: done
- Completed: 2026-04-11T03:48:03Z

# Suite Activation: dc-cr-npc-system

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-11T03:31:23+00:00  

## Task

This feature has been selected into the current release scope. Activate its test plan into the live QA suite.

**Now** is when you add tests to `suite.json` and `qa-permissions.json`.
The feature is in scope; Dev will implement it this release. Tests must be live for Stage 4 regression.

### Required actions

1. **Add a suite entry to** `qa-suites/products/dungeoncrawler/suite.json`  
   Use the test plan below as the spec.  
   **CRITICAL: tag every new entry with `"feature_id": "dc-cr-npc-system"`**  
   This links the test to the living requirements doc at `features/dc-cr-npc-system/`.  
   Dev reads this field to know: failing test = new feature to implement, not a regression.  
   Minimum suite entry structure:
   ```json
   {
     "id": "dc-cr-npc-system-e2e",
     "label": "<describe what the test verifies>",
     "type": "e2e",
     "feature_id": "dc-cr-npc-system",
     "command": "<playwright or test command>",
     "artifacts": ["<report path>"],
     "required_for_release": true
   }
   ```

2. **Add permission rules to** `org-chart/sites/dungeoncrawler.life/qa-permissions.json`  
   For any new routes/ACL expectations.  
   **CRITICAL: tag every new rule with `"feature_id": "dc-cr-npc-system"`**  
   Example:
   ```json
   {
     "id": "dc-cr-npc-system-<route-slug>",
     "feature_id": "dc-cr-npc-system",
     "path_regex": "/your-new-route",
     "notes": "Added for feature dc-cr-npc-system",
     "expect": { "anon": "...", "authenticated": "..." }
   }
   ```

3. **Validate the suite:**
   ```bash
   python3 scripts/qa-suite-validate.py
   ```

4. **Write outbox** confirming: how many entries added, feature_id tagged on each, suite validated, any gaps flagged.

### Test plan (written during grooming)

# Test Plan: dc-cr-npc-system

## Coverage summary
- AC items: ~18 (content type, attitude/social mechanics, AI GM integration, creature distinction, campaign tracking)
- Test cases: 11 (TC-NPCS-01–11)
- Suites: playwright (encounter, downtime, character creation)
- Security: NPC data scoped to GM's campaign; standard auth protects CRUD

---

## TC-NPCS-01 — NPC content type: all required fields stored
- Description: NPC stores: npc_id, name, role, attitude, level, perception, AC, HP, saves (Fort/Ref/Will), dialogue/lore notes
- Suite: playwright/encounter
- Expected: NPC record has all required fields; no null required fields; role and attitude use defined enum values
- AC: AC-001

## TC-NPCS-02 — Merchant NPC: has associated item list when inventory system active
- Description: NPC with role="merchant" can have an item list for purchasing
- Suite: playwright/encounter
- Expected: merchant NPC shows inventory panel when inventory system active; item list linked to NPC
- AC: AC-001

## TC-NPCS-03 — NPC with combat stats can act in encounter
- Description: NPC with abbreviated stat block can enter initiative and act using action economy
- Suite: playwright/encounter
- Expected: NPC added to initiative; takes actions on its turn; uses NPC stat block for checks
- AC: AC-001

## TC-NPCS-04 — Diplomacy: improve NPC attitude by one step on success
- Description: Successful Diplomacy Influence vs. NPC Influence DC → attitude improves 1 step
- Suite: playwright/encounter
- Expected: Diplomacy success → npc.attitude steps up (hostile→unfriendly, unfriendly→indifferent, etc.)
- AC: AC-002

## TC-NPCS-05 — Deception detected: worsen NPC attitude by one step
- Description: Deception against NPC detected → attitude worsens 1 step
- Suite: playwright/encounter
- Expected: Deception check detected → npc.attitude steps down; attitude floor = hostile
- AC: AC-002

## TC-NPCS-06 — Attitude changes persist between sessions (campaign mode)
- Description: NPC attitude changes saved to DB; persist to next session in campaign mode
- Suite: playwright/downtime
- Expected: attitude changed in session → DB updated; next session load shows changed attitude
- AC: AC-002

## TC-NPCS-07 — AI GM context includes NPC name, role, attitude, lore notes
- Description: When AI GM loads a scene with an NPC, context includes name, role, current attitude, lore notes
- Suite: playwright/encounter
- Expected: AI context payload contains npc.name, npc.role, npc.attitude, npc.lore_notes for each active NPC
- AC: AC-003

## TC-NPCS-08 — Quest-giver NPC: quest hook text surfaced to AI GM
- Description: NPC with quest-giver flag has quest hook text surfaced from lore notes to AI GM context
- Suite: playwright/encounter
- Expected: quest_giver NPC → quest_hook_text included in AI GM context; prompts narrative introduction
- AC: AC-003

## TC-NPCS-09 — NPC vs. creature distinction: NPC has abbreviated stat block
- Description: NPCs and monsters are separate content types; NPCs have abbreviated stats (level, perception, AC, HP, saves only)
- Suite: playwright/encounter
- Expected: NPC record has no skill/action bloat; creatures have full PF2E stat blocks; distinct types in content query
- AC: AC-004

## TC-NPCS-10 — Campaign NPC tracking: all NPCs listed on campaign load; history logged
- Description: Campaign record lists all associated NPCs; relationship changes logged with session they occurred
- Suite: playwright/downtime
- Expected: campaign.npcs list populated; npc.relationship_log stores session_id + change per event
- AC: AC-005

## TC-NPCS-11 — NPC data scoped to owning campaign; not accessible to other campaigns
- Description: NPC records not accessible to other campaigns without GM sharing; CRUD requires auth
- Suite: playwright/encounter
- Expected: cross-campaign NPC query blocked; NPC mutations require campaign owner auth
- AC: Security AC

### Acceptance criteria (reference)

# Acceptance Criteria: NPC System
# Feature: dc-cr-npc-system

## AC-001: NPC Content Type
- Given an NPC is created, when saved, then it stores: npc_id, name, role (ally|contact|merchant|villain|neutral), attitude (friendly|indifferent|unfriendly|hostile), level, perception, AC, HP, saves (Fort/Ref/Will), and dialogue/lore notes
- Given an NPC has a role of "merchant", when the inventory system is active, then the NPC can have an associated item list for purchasing
- Given an NPC has combat stats (abbreviated stat block), when the NPC enters combat, then the NPC can act using the same initiative/action economy as creatures

## AC-002: Attitude and Social Mechanics
- Given a player makes a Diplomacy check to Influence an NPC, when the check result meets or exceeds the NPC's Influence DC, then the NPC's attitude improves by one step
- Given a player makes a Deception check against an NPC, when detected, then the NPC's attitude worsens by one step
- Given the NPC attitude changes, when the session is saved, then the new attitude persists to the next session (campaign mode)

## AC-003: AI GM Integration
- Given the AI GM is managing a scene with an NPC, when the scene is loaded, then the AI GM context includes the NPC's name, role, current attitude, and lore notes
- Given the AI GM portrays an NPC in dialogue, when the NPC's attitude is hostile, then the AI GM is prompted to reflect hostility in dialogue style
- Given an NPC is a "quest-giver", when the AI GM introduces them, then the quest hook text is surfaced from the NPC's lore notes

## AC-004: Distinction from Creatures (Monsters)
- Given a content type query is made for "creature", when results are returned, then NPCs and monsters are separate content types
- Given monsters have full PF2E stat blocks, when NPCs are shown, then NPCs have abbreviated stat blocks (level, perception, AC, HP, saves only — no skill/action bloat)

## AC-005: Campaign NPC Tracking
- Given a campaign has recurring NPCs, when the campaign record is loaded, then all NPCs associated with that campaign are listed
- Given an NPC's relationship to the party changes over time, when the history is reviewed, then changes are logged with the session they occurred in

## Security acceptance criteria

- Security AC exemption: NPC data is authored by the GM (campaign owner) and scoped to their campaign. NPC records are not accessible to other campaigns without the GM sharing. Standard auth protects all NPC CRUD operations.

## Roadmap section

- Roadmap: Core Rulebook
- Chapter: Chapter 10: Game Mastering
- DB sections: core/ch10/NPC Social Mechanics
