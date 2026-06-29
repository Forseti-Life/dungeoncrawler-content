The suite entries are already fully activated from the prior release cycle (release-h, 2026-04-10). Both `dc-cr-downtime-mode-e2e` (line 10750) and `dc-cr-downtime-mode-playwright` (line 13471) are present with all 10 TCs and `feature_id: "dc-cr-downtime-mode"`. Permission rules `dc-cr-downtime-mode-activity-acl` and `dc-cr-downtime-mode-gold-log-acl` are in place. Suite validation passes. No new entries needed — this is a re-activation request for the 134531 batch but the suite is already live.

- Status: done
- Summary: Suite activation for `dc-cr-downtime-mode` was already complete from the prior release cycle. Both `dc-cr-downtime-mode-e2e` (line 10750, `activated_release: 20260409-dungeoncrawler-release-h`) and `dc-cr-downtime-mode-playwright` (line 13471) exist with all 10 TCs tagged `feature_id: "dc-cr-downtime-mode"`. Permission rules `dc-cr-downtime-mode-activity-acl` and `dc-cr-downtime-mode-gold-log-acl` already in `qa-permissions.json`. Suite validation clean. No changes needed; committed outbox `5ede56c62`.

## Next actions
- Await 134531 unit-test dispatch items (dc-cr-feats-ch05, dc-cr-gnome-heritage-sensate, dc-cr-hazards, dc-cr-rest-watch-starvation, dc-cr-skills-society-create-forgery)
- Run TC-DTM-01–10 when Dev implements downtime-mode this cycle

## Blockers
- None

## ROI estimate
- ROI: 5
- Rationale: Confirmation-only task; suite was already live and required no changes.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260412-134531-suite-activate-dc-cr-downtime-mode
- Generated: 2026-04-12T16:24:19+00:00
