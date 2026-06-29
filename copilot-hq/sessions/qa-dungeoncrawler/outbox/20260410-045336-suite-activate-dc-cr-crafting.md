The suite exists and has `feature_id` at the suite level, but each `test_case` entry is missing the `feature_id` field and is also missing `suite`, `expected`, `status` fields per the command spec. I need to replace all 12 TCs with the full structure from the test plan.

- Status: done
- Summary: Suite activation for dc-cr-crafting complete. All 12 TCs (TC-CRF-01–12) now tagged with `feature_id: "dc-cr-crafting"` and include full fields (suite, expected, ac_ref, status). The suite shell existed but TCs were missing feature_id — corrected. No new permission rules needed (crafting is character-scoped, covered by existing `api-game-routes`). Suite validation: **OK**. Committed `cb4f4b23d`.

## ROI estimate
- ROI: 8
- Rationale: Proper feature_id tagging lets Dev distinguish new-feature failures from regressions at Stage 4; TC-CRF-12 covers atomic gold/item security regression.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-045336-suite-activate-dc-cr-crafting
- Generated: 2026-04-10T17:25:50+00:00
