- Status: done
- Summary: Activated the `dc-ui-token-readability` feature test suite by adding five tagged test entries to `qa-suites/products/dungeoncrawler/suite.json` and one ACL rule to `org-chart/sites/dungeoncrawler/qa-permissions.json`, then validated the suite with `python3 scripts/qa-suite-validate.py` (exit 0, no errors). All entries carry `"feature_id": "dc-ui-token-readability"` as required. Committed all changes; commit hash recorded below.

## Next actions
- Monitor Dev implementation progress; run targeted retests against TC-UI-TOKEN-01 through TC-UI-TOKEN-05 once Dev marks the feature ready for QA.
- At Stage 4 regression, confirm all five entries in the suite return PASS before Gate 2 sign-off.
- Update `qa-permissions.json` if Dev adds additional routes beyond the player-payload endpoint during implementation.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 40
- Rationale: Activating the suite now unblocks Dev from implementing against live test targets this release cycle and keeps Gate 2 on track; delay here directly delays release readiness.

---

*Work detail (for audit trail):*

### Files changed

**`qa-suites/products/dungeoncrawler/suite.json`** — added 5 entries:

```json
{
  "id": "dc-ui-token-readability-tc01",
  "label": "Team and selection state are visible on-map (rings/outlines/selection cues render)",
  "type": "e2e",
  "feature_id": "dc-ui-token-readability",
  "command": "npx playwright test tests/dungeoncrawler/token-readability.spec.ts --grep 'TC-UI-TOKEN-01'",
  "artifacts": ["test-results/dungeoncrawler/tc-ui-token-01/"],
  "required_for_release": true
},
{
  "id": "dc-ui-token-readability-tc02",
  "label": "HP state is legible on-map — marker updates after damage/healing",
  "type": "e2e",
  "feature_id": "dc-ui-token-readability",
  "command": "npx playwright test tests/dungeoncrawler/token-readability.spec.ts --grep 'TC-UI-TOKEN-02'",
  "artifacts": ["test-results/dungeoncrawler/tc-ui-token-02/"],
  "required_for_release": true
},
{
  "id": "dc-ui-token-readability-tc03",
  "label": "Condition badges render for conditioned tokens without obscuring token body",
  "type": "e2e",
  "feature_id":

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260418-133559-suite-activate-dc-ui-token-readability
- Generated: 2026-04-18T13:44:18+00:00
