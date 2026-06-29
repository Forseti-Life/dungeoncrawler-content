- command: |
    Targeted QA unit test for completed Dev item.

    - Completed item: 20260417-syshealth-merge-health-remediation
    - Dev seat: dev-infra
    - Dev outbox: sessions/dev-infra/outbox/20260417-syshealth-merge-health-remediation.md

    Required actions:
    1) Run a targeted verification for *this item* (derive steps from Dev outbox + acceptance criteria).
    2) Ensure this check exists in the regression checklist and keep it evergreen:
       - org-chart/sites/infrastructure/qa-regression-checklist.md
    3) Run infrastructure operator-audit checks for this scope:
       - python3 scripts/qa-suite-validate.py
       - bash scripts/lint-scripts.sh  (when present)
       - bash -n scripts/*.sh  (as applicable to the changed surface)
       - Do NOT run scripts/site-audit-run.sh or URL/Playwright audits for infrastructure

    Deliverable:
    - Write a Verification Report with explicit APPROVE/BLOCK and evidence.
- Agent: qa-infra
- Status: pending
