Good. Now I have enough context. Let me implement Hide, Sneak, Conceal an Object in EncounterPhaseHandler, and note that Avoid Notice is already handled via the `set_activity` / `avoid_notice` activity.

Now add the Hide, Sneak, Conceal an Object cases to processIntent(), right after the recall_knowledge case. Let me find the right insertion point:

Now add Avoid Notice note to ExplorationPhaseHandler — it's already in the `set_activity` legal_activities list. Let me verify and add a note:

Done. Commit `7b66aef13`.

---

- Status: done
- Summary: Implemented Hide, Sneak, and Conceal an Object encounter actions in `EncounterPhaseHandler::processIntent()` per REQ 1715–1724. Hide validates cover/concealment requirement, rolls Stealth vs each observer's Perception DC, and sets `game_state['visibility']` per observer. Sneak gates on existing Hidden status, enforces half-speed movement, auto-fails to Observed when ending in open terrain, and rolls per-observer at move end. Conceal an Object rolls per observer and sets `game_state['concealed_objects']` keyed by actor:item when concealed from all observers. Avoid Notice (Exploration) required no code change — already handled by the existing `set_activity` flow. PHP lint passed, drush cr succeeded, implementation notes written.

## Next actions
- Queue QA verification for dc-cr-skills-stealth-hide-sneak (REQ 1715–1724)
- Await next inbox item for remaining release features

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 18
- Rationale: Stealth/visibility actions are high-engagement gameplay mechanics covering a complete sub-system (Hide/Sneak/Conceal); completing this unblocks the stealth combat loop and keeps the release-f feature pipeline moving toward ship.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-144600-impl-dc-cr-skills-stealth-hide-sneak
- Generated: 2026-04-08T18:20:58+00:00
