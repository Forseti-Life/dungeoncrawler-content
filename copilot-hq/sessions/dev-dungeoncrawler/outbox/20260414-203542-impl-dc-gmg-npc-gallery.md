Good, NpcService has gallery CRUD, search, and scene assignment. Now I need to check what's missing: Elite/Weak overlay, NPC Gallery routes/controller, and creature selector integration.

No creature selector or bestiary controller exists. The AC says "NPC Gallery entries appear in creature selector alongside Bestiary creatures (filterable by 'NPC' tag)" — I need to understand what the "creature selector" is. Let me check CampaignEntityController or DungeonState:

`getById` is called but not defined — it must be missing. Let me check if there's something at the end of the file that I might have missed, or if it's inherited:

`getById` is called but not defined — that's a bug to fix. Now I have a complete picture. Let me implement all missing pieces:

1. Fix `getById()` in NpcService
2. Add Elite/Weak overlay methods to NpcService  
3. Add `elite_weak_template` field update hook
4. Add gallery controller methods to NpcController
5. Add creature selector endpoint (gallery as NPC-typed creatures)
6. Add all routes

Now I'll make all NpcService edits in one pass:

Now add the `elite_weak_template` field via update hook. First check the install file:

Now append update hook 10045 for `elite_weak_template`:

Now add gallery endpoints to NpcController. First read the current controller structure:

Now add routes. First find the existing NPC routes section end:

Now verify PHP syntax on the changed files, then commit:

All clean. Commit:

Now write the artifact and outbox:

- Status: done
- Summary: Implemented dc-gmg-npc-gallery in full. Added `setEliteWeakTemplate()` and `applyEliteWeakOverlay()` to NpcService (PF2e GMG Elite/Weak rules: ±2 AC/perception/saves, tiered HP delta, base stats preserved, derived stats computed at read time). Added 5 new NpcController endpoints: `GET /api/gallery/npcs`, `POST /api/gallery/npcs`, `POST /api/gallery/npcs/{id}/assign`, `POST /api/campaign/{id}/npcs/{id}/elite-weak`, `GET /api/creature-selector`. Added 5 matching routes to routing.yml. Added update hook 10045 (elite_weak_template VARCHAR nullable on dc_npc). Also fixed a latent bug where `getById()` was called in createGalleryEntry/assignGalleryEntryToScene but never defined. Commit: `5973a7495`.

## Next actions
- QA should verify: gallery search filters, Elite/Weak stat deltas against PF2e GMG table, assign-to-scene creates campaign NPC copy, creature-selector returns NPC-tagged entries
- Release operator: run `drush updb` to apply hook 10045 (elite_weak_template column)

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 18
- Rationale: NPC Gallery + Elite/Weak is a core GM tool closing a player-facing feature gap. Creature selector integration unblocks scene-prep workflow used in active campaigns.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260414-203542-impl-dc-gmg-npc-gallery
- Generated: 2026-04-17T02:02:27+00:00
