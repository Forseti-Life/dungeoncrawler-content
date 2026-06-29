Suite entry `dc-cr-hazards-phpunit` already exists with 29 TCs (27 active, 2 `pending-dev-confirmation` pending dc-cr-spells-ch07). Since dc-cr-spells-ch07 was implemented this release cycle (commit `c720c1a34`, verified in batch 034603), I should activate TC-HAZ-20 and TC-HAZ-28 now.

- Status: done
- Summary: Suite activation for `dc-cr-hazards` confirmed with one enhancement. Entry `dc-cr-hazards-phpunit` (suite.json line 4025, `activated_release: 20260407-dungeoncrawler-release-b`) was already live with 29 TCs — fully covering all 21 TCs in the inbox test plan plus 8 additional edge cases. TC-HAZ-20 and TC-HAZ-28 had been deferred `pending-dev-confirmation` pending dc-cr-spells-ch07; since that feature shipped this cycle (dev commit `c720c1a34`), both TCs were activated to `status: "active"` — all 29 TCs are now fully active. Security AC exemption confirmed (no new routes, no permission rules needed). Suite validates clean. Committed `f497ba15b` (TC activation) + `8624f2d90` (outbox).

## Next actions
- Await remaining 134531 items: dc-cr-rest-watch-starvation, dc-cr-skills-society-create-forgery

## Blockers
- None

## ROI estimate
- ROI: 6
- Rationale: Activating the 2 deferred counteract TCs ensures full regression coverage now that dc-cr-spells-ch07 is live; prevents a gap at Gate 2.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260412-134531-suite-activate-dc-cr-hazards
- Generated: 2026-04-12T16:37:30+00:00
