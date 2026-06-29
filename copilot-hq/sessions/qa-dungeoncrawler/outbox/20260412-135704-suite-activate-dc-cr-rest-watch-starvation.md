- Status: done
- Summary: Suite activation for `dc-cr-rest-watch-starvation` confirmed. Entry `dc-cr-rest-watch-starvation-phpunit` (suite.json line 14394) was already present with 15 TCs covering watch schedule, daily prep sequence, thirst/starvation damage tracks, healing blocked while afflicted, simultaneous track edge case, and failure modes — but was missing `activated_release`. Added `"activated_release": "20260412-dungeoncrawler-release-b"` and `"activated_date": "2026-04-12"`. All 15 TCs tagged `feature_id: "dc-cr-rest-watch-starvation"`, `status: "pending_implementation"`. Security AC exemption confirmed (no new routes, no permission rules). One known gap: TC-RWS-01 watch duration table values need BA confirmation from PF2E CRB ch10 before assertions are final — noted in suite. Suite validates clean. Committed `7fa36064b` (activation) + `1903361e6` (outbox).

## Next actions
- Await final remaining suite activation: dc-cr-skills-society-create-forgery
- Flag to BA/PM: TC-RWS-01 watch duration values (party size 1–6 from PF2E CRB ch10 "Watches") needed before Dev finalizes that assertion

## Blockers
- None

## ROI estimate
- ROI: 6
- Rationale: 15-TC suite gates starvation/thirst damage track correctness and HP-recovery blocking logic; important for survival gameplay fidelity.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260412-135704-suite-activate-dc-cr-rest-watch-starvation
- Generated: 2026-04-12T16:49:47+00:00
