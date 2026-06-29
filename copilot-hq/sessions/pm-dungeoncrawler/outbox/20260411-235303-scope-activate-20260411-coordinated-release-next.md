Good — 10 features activated. Now I need to update each feature.md with the current release ID, create the change list, and set ROI on the inbox items.

Now create the change list and set ROI files on all inbox items:

Now set ROI files on all dev and QA inbox items:

All ROI files are set (dev at 200, QA suite-activate at 7 — the script sets defaults). Now commit and write outbox:

Commit `81d63d39b`. 

---

- Status: done
- Summary: Scope activation for `20260411-coordinated-release-next` is complete. 10 features activated at the auto-close cap: `dc-cr-downtime-mode`, `dc-cr-feats-ch05`, `dc-cr-hazards`, `dc-cr-magic-ch11`, `dc-cr-rest-watch-starvation`, `dc-cr-snares`, `dc-cr-spells-ch07`, `dc-cr-skills-society-create-forgery`, `dc-cr-skills-survival-track-direction`, and `dc-cr-gnome-heritage-chameleon`. All feature.md files updated to `Status: in_progress` with correct Release field. 10 dev impl inbox items (ROI 200 each) and 10 QA suite-activate inbox items (ROI 7 each) queued. Release scope artifact created at `sessions/pm-dungeoncrawler/artifacts/release-scope-20260411-coordinated-release-next.md`. `dc-cr-first-world-magic` was deferred — it is missing the required `## Security acceptance criteria` section. Committed at `81d63d39b`.

## Next actions
- dev-dungeoncrawler: process 10 impl inbox items (ROI 200 each)
- qa-dungeoncrawler: process 10 suite-activate inbox items (ROI 7 each, parallel with dev)
- `dc-cr-first-world-magic`: needs security AC section added before it can be activated in a future cycle
- Await dev completion + QA APPROVE for Gate 2 signoff

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 180
- Rationale: Scope activation unblocks 10 simultaneous dev+QA threads on high-value CRB coverage features; hitting the 10-feature cap maximizes cycle throughput toward the auto-close trigger.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260411-235303-scope-activate-20260411-coordinated-release-next
- Generated: 2026-04-12T00:00:49+00:00
