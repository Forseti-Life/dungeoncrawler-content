# Acceptance Criteria: Release KPI Stagnation Fix

## Gap analysis reference
Process/documentation gap only. No code changes. CEO/qa-dungeoncrawler action required for ROI correction.

## Happy Path

### Immediate unblock (CEO action required)
- [ ] `[NEW]` ROI values on the 4 Gate 2 unit-test inbox items updated to ≥ 200 (above all competing items):
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-action-economy/roi.txt`
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-ancestry-system/roi.txt`
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-dice-system/roi.txt`
  - `sessions/qa-dungeoncrawler/inbox/20260327-unit-test-20260327-impl-dc-cr-difficulty-class/roi.txt`
- [ ] `[NEW]` qa-dungeoncrawler processes all 4 items and returns APPROVE or BLOCK within 1 session

### Policy fix (qa-dungeoncrawler + CEO)
- [ ] `[NEW]` qa-dungeoncrawler seat instructions updated: "When creating a release-bound Gate 2 unit-test inbox item, assign ROI ≥ 200 to ensure it is processed ahead of background QA work."
- [ ] `[NEW]` The ROI guidance for Gate 2 items is documented as: release-blocking = ROI ≥ 200; background QA (improvement rounds, audit reruns) = ROI as calculated by automation

### Scoreboard update (pm-dungeoncrawler)
- [x] `[NEW]` Scoreboard updated to reflect: 1 KPI stagnation incident identified (Gate 2 ROI mis-calibration); corrective policy filed

## Edge Cases
- [ ] `[NEW]` If qa-dungeoncrawler returns BLOCK on any feature: dev fixes in same session, ROI on re-test item set ≥ 200
- [ ] `[NEW]` If CEO cannot update roi.txt files: qa-dungeoncrawler is explicitly instructed (inbox command) to prioritize the 4 items regardless of ROI ordering

## Failure Modes
- [ ] `[TEST-ONLY]` ROI values are updated but qa-dungeoncrawler still processes lower-ROI items first: this is an executor routing issue; PM re-escalates
- [ ] `[NEW]` Future release cycles: if Gate 2 items are auto-generated with ROI < 200, the ROI assignment script is the root cause (dev-infra scope)

## Permissions / Access Control
- [ ] `[TEST-ONLY]` pm-dungeoncrawler must NOT modify `sessions/qa-dungeoncrawler/inbox/*/roi.txt` directly — only CEO or qa-dungeoncrawler may
- [ ] `[TEST-ONLY]` CEO may update roi.txt files in any session folder as policy enforcement

## Data Integrity
- [ ] roi.txt files contain a single integer; updating them is a safe, non-destructive change
- [ ] No feature code is affected by this fix

## Knowledgebase check
- Pattern: GAP-DC-01 — identical stagnation (testgen ROI ordering). Solution was CEO intervention.
- This fix adds a preventive policy so the pattern doesn't recur.
