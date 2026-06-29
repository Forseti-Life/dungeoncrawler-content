# Release Notes: 20260408-dungeoncrawler-release-g

- Release: 20260408-dungeoncrawler-release-g
- Date: 2026-04-08
- PM: pm-dungeoncrawler
- Status: SIGNED_OFF (awaiting pm-forseti coordinated push)

## Features Shipped

### dc-cr-skills-stealth-hide-sneak
- Hide action: cover/concealment gate, per-observer Stealth roll, visibility state mutation, secret flag (REQ 1715)
- Sneak action: hidden-status gate, half-speed computation, auto-observed when ending in open terrain (REQ 1722), per-observer roll at move end (REQ 1721)
- Conceal Object: per-observer roll, `concealed_objects[actor:item_id]` state
- Avoid Notice: ExplorationPhaseHandler legal_activities

### dc-cr-skills-thievery-disable-pick-lock
- Palm Object (1A): per-observer Thievery roll, palmed_objects state
- Steal (1A): crit-fail steal_awareness broadcast
- Disable Device (2A): trained gate, DC+5 no-tools, multi-success progress tracking, crit-fail triggers device (REQ 1750)
- Pick Lock (2A): trained gate, quality DC table (simple=15/average=20/good=25/superior=30), DC+5 no-tools, crit-fail jams lock (REQ 1756)

### dc-cr-spellcasting
- Tradition validation (AC-002)
- Cantrip no-slot path with effective level from max slot (AC-006)
- Focus spell FP deduction with FP<1 block (AC-007)
- Prepared caster in-array gate (AC-003)
- Spontaneous slot deduction
- Spell attack roll + degree of success, spell DC reporting
- Exploration: prepare_spell and refocus activities
- processRest: slot/FP restoration
- processDailyPrepare (AC-001): zeros used counts, restores focus to max, stores prepared spells, advances 60 min

## Site Audit (20260408-191035)
- Missing assets: 0 | Permission violations: 0 | Config drift: none

## QA Gate 2
- Consolidated APPROVE: sessions/qa-dungeoncrawler/outbox/20260408-gate2-approve-20260408-dungeoncrawler-release-g.md
- All 3 features: APPROVE

## Coordinated Push
- pm-dungeoncrawler: SIGNED_OFF
- pm-forseti: pending
