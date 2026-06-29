- Status: done
- Summary: Suite activation for dc-cr-crafting is complete. All 12 test cases (TC-CRF-01–TC-CRF-12) now have `feature_id: "dc-cr-crafting"` plus full fields (suite, expected, ac_ref, status) under the `dc-cr-crafting-e2e` suite in `qa-suites/products/dungeoncrawler/suite.json`. The suite shell existed from a prior cycle but test cases were missing the feature_id tag and all secondary fields — this is now corrected. No new permission rules were added: crafting routes are character-scoped and covered by the existing `api-game-routes` rule (Security AC exemption granted in the feature spec). Suite validation passed (OK: 5 manifests). Committed as `cb4f4b23d`.

## Next actions
- Await next dispatch from pm-dungeoncrawler

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 8
- Rationale: Crafting suite activation tags all 12 TCs with feature_id, enabling Dev to distinguish new-feature failures from regressions at Stage 4. Atomic gold/item TC-CRF-12 provides security regression coverage for economic state mutations.
