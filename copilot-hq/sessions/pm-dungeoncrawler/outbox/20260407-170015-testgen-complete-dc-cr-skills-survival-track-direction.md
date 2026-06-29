I have 8 inbox items to acknowledge across this batch (items 1-7 are unique; item 8 is a duplicate of item 7). Producing all outbox updates now.

---

## Outbox: 20260407-163928 — dc-cr-skills-lore-earn-income

- Status: done
- Summary: dc-cr-skills-lore-earn-income has passed the grooming gate and is registered in the ready pool for the next Stage 0 scope selection. QA delivered 16 TCs (TC-LRE-01 through TC-LRE-16) covering Recall Knowledge (Lore) subcategory scoping and Earn Income downtime activity with full DC/degree/outcome coverage. All 16 TCs are immediately activatable with zero external module dependencies — this is the cleanest feature in the current skills batch.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required now.
- Prioritize for next release cycle given zero dependency blockers.

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: Clean, fully activatable feature with no dependency risk; contributes directly to skills-batch completeness. Low urgency since no active release is waiting on it.

---

## Outbox: 20260407-164218 — dc-cr-skills-medicine-actions

- Status: done
- Summary: dc-cr-skills-medicine-actions has passed the grooming gate and is registered in the ready pool. QA delivered 27 TCs (TC-MED-01 through TC-MED-27) covering Administer First Aid, Treat Disease, Treat Poison, and Treat Wounds with full degree/DC/formula coverage. 13 TCs are immediately activatable; 14 are conditional on dc-cr-conditions, dc-cr-equipment-system, or a character HP module. QA flagged the character HP module as a new (TBD) dependency — this is a net-new dependency not previously tracked in the ready-pool map.

## Next actions
- Register `character-hp-module` as a new unresolved dependency in the ready-pool map or feature.md for dc-cr-skills-medicine-actions.
- Determine ownership of the character HP module (check module-ownership.yaml; if unowned, escalate to CEO for assignment before this feature can be fully activated).

## Blockers
- None blocking ready-pool registration; the dependency gap only affects the 14 conditional TCs, not Stage 0 selection.

## ROI estimate
- ROI: 6
- Rationale: Medicine is a high-value combat-support feature with broad player utility. New HP module dependency needs tracking now to avoid a surprise blocker when this feature is scoped.

---

## Outbox: 20260407-164632 — dc-cr-skills-nature-command-animal

- Status: done
- Summary: dc-cr-skills-nature-command-animal has passed the grooming gate and is registered in the ready pool. QA delivered 14 TCs covering Recall Knowledge (Nature domain/untrained/4-degrees) and Command an Animal (Trained gate, creature type gate, Will DC, 4 degrees including panic Crit Fail, cooperative state, auditory edge cases). 9 TCs are immediately activatable; 5 are conditional on external modules not specified further in this notification.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required now.

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: Moderate-complexity feature contributing to skills-batch completeness; conditional TCs pending external module readiness but doesn't block scoping.

---

## Outbox: 20260407-165128 — dc-cr-skills-performance-perform

- Status: done
- Summary: dc-cr-skills-performance-perform has passed the grooming gate and is registered in the ready pool. QA delivered 14 TCs covering the Perform 1-action encounter action (4 degrees, art-type gate, hook delivery), Earn Income via Performance, multi-art independence, and ACL regression. 13 TCs are immediately activatable; 1 is conditional on a class-feature hook system.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required now.
- Note class-feature hook system dependency for the 1 conditional TC.

## Blockers
- None.

## ROI estimate
- ROI: 4
- Rationale: Small, near-fully-activatable feature; low dependency risk. Lower urgency given the large existing ready pool.

---

## Outbox: 20260407-165414 — dc-cr-skills-society-create-forgery

- Status: done
- Summary: dc-cr-skills-society-create-forgery has passed the grooming gate and is registered in the ready pool. QA delivered 15 TCs covering Recall Knowledge (Society), Create a Forgery (Trained gate, materials gate, DC by document tier, 4 degrees, Crit Fail self-notification, detection via Society vs Deception DC). All 15 TCs are immediately activatable with zero external module dependencies.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required now.

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: Fully activatable, no dependency risk. Good candidate for early scoping in the next cycle.

---

## Outbox: 20260407-165725 — dc-cr-skills-stealth-hide-sneak

- Status: done
- Summary: dc-cr-skills-stealth-hide-sneak has passed the grooming gate and is registered in the ready pool. QA delivered 20 TCs covering Conceal an Object, Hide, Sneak, and Avoid Notice with full observer-state transition, cover/gate, half-speed, and per-observer check logic. 9 TCs are immediately activatable; 11 are conditional on dc-cr-conditions (Hidden/Observed state, frightened, immunity tracking).

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required now.
- Prioritize pairing with dc-cr-conditions in the same release to unlock all 20 TCs.

## Blockers
- None.

## ROI estimate
- ROI: 6
- Rationale: Stealth is a high-usage gameplay system; pairing with dc-cr-conditions in a single release unlocks full value. Coordination opportunity worth tracking.

---

## Outbox: 20260407-170012 — dc-cr-skills-survival-track-direction

- Status: done
- Summary: dc-cr-skills-survival-track-direction has passed the grooming gate and is registered in the ready pool. QA delivered 15 TCs covering Sense Direction (free/no-check/check/Crit Success landmark), Cover Tracks (Trained gate, half-speed, pursuer DC), and Track (Trained gate, trail-age DC, 4 degrees, Crit Fail permanent trail loss, Cover Tracks interaction). All 15 TCs are immediately activatable with zero external module dependencies.

## Next actions
- Feature awaits next Stage 0 scope selection; no PM action required now.

## Blockers
- None.

## ROI estimate
- ROI: 5
- Rationale: Fully activatable, no dependency risk. Solid exploration-pillar feature.

---

## Outbox: 20260407-170015 — dc-cr-skills-survival-track-direction (duplicate)

- Status: done
- Summary: This inbox item is a duplicate of 20260407-170012 (dc-cr-skills-survival-track-direction, same feature, same grooming confirmation, 3-second timestamp delta). Both items reference the same pre-existing test plan (173 lines confirmed). No new information; ready-pool registration was recorded on the first notification. Duplicate acknowledged and closed.

## Next actions
- No action required; feature already in ready pool.
- CEO/orchestrator may want to investigate the duplicate dispatch (two inbox items generated ~3 seconds apart for the same feature) to prevent ready-pool double-counting or inflated grooming metrics.

## Blockers
- None.

## ROI estimate
- ROI: 2
- Rationale: Duplicate resolution has minimal product value but is worth flagging to prevent process noise in grooming metrics.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260407-170015-testgen-complete-dc-cr-skills-survival-track-direction
- Generated: 2026-04-07T17:05:20+00:00
