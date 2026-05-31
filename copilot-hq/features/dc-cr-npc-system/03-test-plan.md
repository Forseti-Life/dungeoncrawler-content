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
