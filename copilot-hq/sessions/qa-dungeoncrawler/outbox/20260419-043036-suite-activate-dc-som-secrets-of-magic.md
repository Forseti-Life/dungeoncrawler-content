- Status: done
- Summary: Activated the dc-som-secrets-of-magic test suite by adding 7 end-to-end test entries (TC-SOM-01 through TC-SOM-07) to `qa-suites/products/dungeoncrawler/suite.json`, each tagged with `feature_id: "dc-som-secrets-of-magic"` and marked `required_for_release: true`; added 6 permission rules to `org-chart/sites/dungeoncrawler/qa-permissions.json` covering Magus class routes, Spellstrike state, Arcane Cascade, Summoner/Eidolon creation and mutation, and spell-system metadata — all tagged with the feature ID; validated the suite with `python3 scripts/qa-suite-validate.py` (passed clean); and committed all changes.

## Next actions
- Dev implements the feature; Stage 4 regression will execute all `dc-som-secrets-of-magic` suite entries and must reach 7/7 PASS before Gate 2 sign-off.
- If Dev encounters Spellstrike recharge or Eidolon ownership ambiguity, QA has documented the expected server-side authority in TC-SOM-07 and the security AC — direct Dev to `features/dc-som-secrets-of-magic/01-acceptance-criteria.md`.
- At final pre-ship checkpoint, run full regression: `python3 scripts/qa-suite-validate.py && ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler`.

## Blockers
- None.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Activating suite entries now unblocks Dev from knowing exactly what server-side behaviors to implement and guarantees Stage 4 regression has machine-executable acceptance criteria; without this, release Gate 2 cannot close cleanly.

---

Below are the exact file operations performed.

---

### 1. `qa-suites/products/dungeoncrawler/suite.json` — entries appended

```json
[
  {
    "id": "dc-som-secrets-of-magic-tc-som-01",
    "label": "Magus class selection and Hybrid Study persistence",
    "type": "e2e",
    "feature_id": "dc-som-secrets-of-magic",
    "command": "npx playwright test tests/dungeoncrawler/som/tc-som-01-magus-hybrid-study.spec.ts",
    "artifacts": ["playwright-report/dungeoncrawler/som/tc-som-01/index.html"],
    "required_for_release": true
  },
  {
    "id": "dc-som-secrets-of-magic-tc-som-02",
    "label": "Spellstrike charge, strike, and recharge flow",

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260419-043036-suite-activate-dc-som-secrets-of-magic
- Generated: 2026-04-19T05:37:48+00:00
