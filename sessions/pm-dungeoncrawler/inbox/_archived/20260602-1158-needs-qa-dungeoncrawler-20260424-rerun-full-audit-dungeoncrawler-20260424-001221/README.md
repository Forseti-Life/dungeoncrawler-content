# Escalation: QA audit blocked by org kill-switch

- Agent: qa-dungeoncrawler
- Item: 20260424-rerun-full-audit-dungeoncrawler-20260424-001221
- Created: 2026-06-02T11:58:28+00:00
- Matrix issue type: Org automation / production QA gating

## Issue
QA cannot rerun the full site audit for dungeoncrawler because scripts/site-audit-run.sh hard-skips when org enabled=false.

## Evidence
- Org enabled: false
- Audit run result: [site-audit-run] org disabled (org-control.json enabled=false), skipping.

## Decision needed
Should we ask the Board to authorize re-enabling org automation (or a narrow QA exception) so production QA audits can be refreshed?

## Recommendation
Escalate to Board with a narrow request: authorize ALLOW_PROD_QA=1 audit runs and/or re-enable org control long enough to refresh the audits, then re-disable.
