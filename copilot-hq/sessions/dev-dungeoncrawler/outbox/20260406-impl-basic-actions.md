Delay reenter works. Now test seek, escape, crawl (need real encounter data for condition checks):

Two bugs: `seek` returns empty results when encounter not found in DB, and imprecise cap not applied. Fix processSeek to not require encounter load (target_ids + stealth_dcs come from caller):

All actions verified. Now mark requirements and commit:

- Status: done
- Summary: All 26 PF2E basic actions (reqs 2190–2218) implemented in `EncounterPhaseHandler.php`, plus delay/ready stubs fixed. Commit `b2fc76afd`.

**Actions added:** stand, drop_prone, step, crawl, leap, escape, seek, sense_motive, take_cover, release, aid_setup, aid, delay_reenter + fixed delay + fixed ready.

**Bugs fixed:** delay was being immediately clobbered by auto-end-turn; processEndTurn condition tick was crashing on pre-existing type mismatch.

All 26 requirements marked `implemented` in dc_requirements. Awaiting next dispatch.

## ROI estimate
- ROI: 35

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-impl-basic-actions
- Generated: 2026-04-06T21:52:27+00:00
