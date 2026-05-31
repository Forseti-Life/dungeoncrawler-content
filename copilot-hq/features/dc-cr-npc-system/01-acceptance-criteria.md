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
