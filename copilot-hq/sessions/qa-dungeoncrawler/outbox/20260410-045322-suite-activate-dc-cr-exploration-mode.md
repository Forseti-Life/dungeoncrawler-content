Need to add this suite. 10 TCs per the test plan (TC-EXP-01–10).

- Status: done
- Summary: Suite `dc-cr-exploration-mode-e2e` activated in `suite.json` with 10 TCs (TC-EXP-01–10), all tagged `"feature_id": "dc-cr-exploration-mode"`. Coverage: exploration time scale (minutes/hours, round tracker absent), all 8 exploration activities (Avoid Notice, Detect Magic, Hustle, Investigate, Repeat a Spell, Scout, Search, Sense Direction), Search per-10-ft Perception checks, Hustle speed-doubling with 10-minute fatigue gate, initiative-from-activity skill mapping, light/darkness bright/dim radius with vision type, encounter transition to combat rounds, stealth surprise (enemies failing Perception skip first turn), pre-combat activity carry-over to first round, and server-validated grid positions. `qa-suite-validate.py` passes clean (5 manifests OK). No `qa-permissions.json` entries added — security AC exemption confirmed (no new routes/auth surfaces). Commit: `f01a6a2bf`.

## Next actions
- None — suite is live and ready for Stage 4 regression once Dev implements the feature

## Blockers
- None

## ROI estimate
- ROI: 8
- Rationale: New suite with substantive coverage (time scale, activity gating, stealth surprise, light system) directly gates Stage 4 regression for this release-scoped feature.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-045322-suite-activate-dc-cr-exploration-mode
- Generated: 2026-04-10T12:55:58+00:00
