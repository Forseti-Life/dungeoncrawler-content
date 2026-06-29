# PM Outbox: Roadmap Audit Pass 3
**Date:** 2026-04-07
**Agent:** pm-dungeoncrawler

## Summary
Final coverage gap resolution pass. All 240 remaining pending sections now have
explicit feature pipeline coverage.

## Changes This Pass

### New Feature File
- `features/dc-gmg-subsystems/feature.md` — GMG ch04 pluggable subsystem framework
  (chases, influence, research, infiltration, hexploration, vehicles, extended downtime)
  REQs 2732–2737, status: planned P3

### Updated Feature Files
- `features/dc-cr-skills-survival-track-direction/feature.md`
  — Added Subsist action (REQs 1595–1598: downtime, untrained, Nature/Society vs DC 15)
  — Added general skill framework reqs (REQs 1572–1573)
  — REQ range updated: 1572–1573, 1595–1598, 1739–1746
- `features/dc-cr-skills-athletics-actions/feature.md`
  — Added Escape action (REQ 1601: can substitute Athletics/Acrobatics for unarmed)
  — REQ range updated: 1601, 1620–1642

### Verified Coverage (no changes needed)
- GMG ch02: `dc-gmg-hazards` — references ch02 sections ✅
- GMG ch03: `dc-gmg-npc-gallery` — references ch03 sections ✅
- APG ch05/ch06 duplicates: covered by `dc-apg-equipment`, `dc-apg-spells`,
  `dc-apg-focus-spells`, `dc-apg-rituals` ✅
- REQ 1599 (17 skills system): already marked implemented ✅
- REQ 1600 (trained gating): covered by `dc-cr-skills-calculator-hardening` ✅
- REQs 1704, 1709 (Occultism/Religion): covered by `dc-cr-decipher-identify-learn` ✅

## Audit Completion Status
- Pending sections: 240 (all have feature pipeline coverage)
- Implemented reqs: 261 (↑ from 241 at audit start)
- Feature files: 134 total
- Open gaps requiring dev work: 1 (REQ 2093, range cap — dev inbox item exists)
- Deferred by PM decision: REQs 2332–2339 (XP award system, milestone-only per 2026-03-08)

## Audit is COMPLETE
All roadmap sections are in one of these states:
1. `implemented` — verified in DB ✅
2. Feature file in `/features/` pipeline (planned or deferred) ✅
No section remains "not started" without a plan.

## Commit
`b1e2cb608` — audit: fill remaining coverage gaps
