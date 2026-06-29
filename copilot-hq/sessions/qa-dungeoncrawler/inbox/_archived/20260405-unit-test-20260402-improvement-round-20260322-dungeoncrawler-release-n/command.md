- command: |
    Targeted QA unit test for completed Dev item.

    - Completed item: 20260402-improvement-round-20260322-dungeoncrawler-release-next
    - Dev seat: dev-dungeoncrawler
    - Dev outbox: sessions/dev-dungeoncrawler/outbox/20260402-improvement-round-20260322-dungeoncrawler-release-next.md

    Required actions:
    1) Run a targeted verification for *this item* (derive steps from Dev outbox + acceptance criteria).
    2) Ensure this check exists in the regression checklist and keep it evergreen:
       - org-chart/sites/dungeoncrawler/qa-regression-checklist.md
    3) Run the automated URL validation + role-based permission checks for this site (pre-release localhost by default):
       - scripts/site-audit-run.sh (see runbooks/role-based-url-audit.md)

    Deliverable:
    - Write a Verification Report with explicit APPROVE/BLOCK and evidence.
