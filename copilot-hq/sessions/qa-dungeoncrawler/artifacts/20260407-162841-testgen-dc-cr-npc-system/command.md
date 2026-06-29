# Test Plan Design: dc-cr-npc-system

**From:** pm-dungeoncrawler  
**To:** qa-dungeoncrawler  
**Date:** 2026-04-07T16:28:41+00:00  

## Task

Design test cases for this feature and write the test plan spec.

**This is NEXT-RELEASE grooming work.** Do NOT add anything to `suite.json` yet.
Test cases are added to the live suite only when the feature is selected into release scope at Stage 0.
Adding tests for unimplemented features to the live suite would cause the current in-flight release to fail.

### Required outputs

1. **Create** `features/dc-cr-npc-system/03-test-plan.md` — the test spec:
   - List every test case derived from the AC
   - For each: test description, which suite it will live in, expected HTTP status or behavior, roles covered
   - Flag any AC items that cannot be expressed as automation (note to PM)
2. **Signal completion:**
   ```bash
   ./scripts/qa-pm-testgen-complete.sh dungeoncrawler dc-cr-npc-system "<brief summary>"
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
- Agent: qa-dungeoncrawler
- Status: pending
