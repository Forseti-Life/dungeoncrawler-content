- command: |
    Targeted QA unit test for completed Dev item.

    - Completed item: 20260409-schema-hook-pairing-lesson-20260408-forseti-release-b
    - Dev seat: dev-forseti
    - Dev outbox: sessions/dev-forseti/outbox/20260409-schema-hook-pairing-lesson-20260408-forseti-release-b.md

    Required actions:
    1) Run a targeted verification for *this item* (derive steps from Dev outbox + acceptance criteria).
    2) Ensure this check exists in the regression checklist and keep it evergreen:
       - org-chart/sites/forseti.life/qa-regression-checklist.md
    3) Run the automated URL validation + role-based permission checks for this site (requires ALLOW_PROD_QA=1):
       - scripts/site-audit-run.sh (see runbooks/role-based-url-audit.md)

    Deliverable:
    - Write a Verification Report with explicit APPROVE/BLOCK and evidence.
