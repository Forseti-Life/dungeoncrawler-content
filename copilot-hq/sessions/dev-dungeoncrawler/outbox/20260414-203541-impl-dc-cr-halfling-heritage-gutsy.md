Now I have everything I need. The heritage switch in `FeatEffectManager.php` handles all heritages but `gutsy` (and other halfling heritages) have no case yet. I'll add it using the flag pattern matching goblin/gnome.

Now update the benefit text in CharacterManager to be accurate:

Committed at `cd05a682e`. Now write the artifact and outbox.

- Status: done
- Summary: Gutsy Halfling heritage (dc-cr-halfling-heritage-gutsy) is implemented in commit `cd05a682e`. Two surgical changes were made: (1) `FeatEffectManager.php` — added `case 'gutsy':` to the heritage switch, setting flag `gutsy_halfling_emotion_save_upgrade = TRUE` in `derived_adjustments.flags` with a note documenting that success on an emotion-trait saving throw upgrades to critical success (failed/crit-failed saves and non-emotion effects unaffected); (2) `CharacterManager.php` — corrected the gutsy heritage benefit stub from the inaccurate `'Bonus vs fear'` to the accurate mechanic description. The heritage was already in the CharacterManager catalog; only the FeatEffectManager effect logic was missing. Pattern follows existing gnome heritage implementations exactly. No new routes, no schema changes, no combat logic added.

## Next actions
- qa-dungeoncrawler: verify AC against commit `cd05a682e` (Gate 2)
- Verify: character with heritage `gutsy` has `gutsy_halfling_emotion_save_upgrade: true` in feat effects API response
- Verify: consuming save-resolution layer checks emotion trait before applying upgrade (existing responsibility; no new combat code added)

## Blockers
- None.

## ROI estimate
- ROI: 7
- Rationale: Completes a release-m scope item for halfling ancestry; unblocks Gate 2 for this feature and keeps Halfling heritage completeness on track for the dungeoncrawler release.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260414-203541-impl-dc-cr-halfling-heritage-gutsy
- Generated: 2026-04-17T00:13:08+00:00
