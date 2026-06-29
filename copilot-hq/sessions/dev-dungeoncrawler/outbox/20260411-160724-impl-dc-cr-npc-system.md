Now I have full context. Let me implement everything:

Now add the schema update hook and the db tables:

Now register the service and add routes:

Now add routes. Find a good insertion point:

Now update AiGmService::assembleGmContext to include canonical NPC data, and inject NpcService:

Now update `assembleGmContext` to include canonical NPC data:

Now update services.yml to add NpcService as 9th arg to ai_gm:

Now lint, run the update hook, and drush cr:

Tables are live. Update feature.md and commit:

---

- Status: done
- Summary: Implemented the full NPC system (`dc-cr-npc-system`) at commit `e2101f963`. Created `dc_npc` table (via `update_10042`) with all AC-001 fields — name, role (ally/contact/merchant/villain/neutral), attitude, abbreviated stat block (level, perception, AC, HP, Fort/Ref/Will), lore notes, dialogue notes, and `entity_ref` link to `dc_npc_psychology`. Created `dc_npc_history` table for AC-005 audit trail with session_id, trigger text, and old/new values. Built `NpcService` covering full CRUD, Diplomacy/Deception attitude-step social mechanics (AC-002), history logging, and AI-prompt data assembly (AC-003). Built `NpcController` with 7 REST endpoints. Added 7 routes to routing.yml with CSRF enforcement on all write endpoints. Extended `AiGmService::assembleGmContext()` to inject `NpcService` and include a `named_npcs` array (name, role, attitude, lore, dialogue) in the GM context payload so the AI GM can reflect NPC attitudes in dialogue (AC-003). All tables confirmed live in production DB; `drush updb` and `drush cr` succeeded.

## Next actions
- None. Feature complete. Route to qa-dungeoncrawler for Gate 2 verification.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 45
- Rationale: Named NPCs are a core campaign storytelling primitive — quest-givers, merchants, allies — and the social mechanics (Diplomacy/Deception) are central PF2e interaction rules. Completing this unblocks the QA gate for the active release and enables AI GM portrayals with full per-NPC attitude context.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260411-160724-impl-dc-cr-npc-system
- Generated: 2026-04-11T16:33:14+00:00
