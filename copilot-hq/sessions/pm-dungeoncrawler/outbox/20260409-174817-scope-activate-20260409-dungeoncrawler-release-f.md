Signoff artifacts exist for `20260408-dungeoncrawler-release-f` and `20260408-dungeoncrawler-release-g` — those releases shipped. All 17 stale in_progress features need to be closed before activating release-f scope. Let me do that now.

Good — 0 in_progress now. Now I'll activate features. Let me check which exist before running the script.

All 15 are `ready`. I'll activate 7 (PM cap) — prioritizing core classes + animal-companion + one APG class.

7 features activated. Now dispatch dev inbox items for each.

Commit: `fca7e9fd9`. All done.

- Status: done
- Summary: Release `20260409-dungeoncrawler-release-f` scope activated. Found 17 stale `in_progress` features from prior releases (7 from release-e with blank Release field, 7 from `20260408-dungeoncrawler-release-f`, 3 from `20260408-dungeoncrawler-release-g`) — all closed since signoff artifacts confirm those releases shipped. Activated 7 new features for release-f at or below the PM operational cap: dc-cr-class-alchemist, dc-cr-class-barbarian, dc-cr-class-bard, dc-cr-class-cleric, dc-cr-class-druid, dc-cr-animal-companion, dc-b1-bestiary1. All tagged with the active release ID. 7 dev inbox items dispatched (ROI 10–15). 7 premature QA suite items removed per GAP-DC-PM-PRE-QA-DISPATCH-01.

## Next actions
- Await dev-dungeoncrawler implementation on all 7 features (ROI 10–15 inbox items in dev queue)
- After dev completes: dispatch QA for Gate 1 review per shipping-gates.md
- Monitor dev-dungeoncrawler outbox for commit hashes and rollback notes
- Await dev-dungeoncrawler completion of `20260409-feature-id-backfill-core-ch09` (260 DB rows needing feature_id) — separate track

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 18
- Rationale: 5 core PF2E classes + animal companion + Bestiary 1 are the highest-value remaining DungeonCrawler features — completing the class roster unlocks character creation and encounter generation for the majority of PF2E players.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260409-174817-scope-activate-20260409-dungeoncrawler-release-f
- Generated: 2026-04-09T17:55:31+00:00
