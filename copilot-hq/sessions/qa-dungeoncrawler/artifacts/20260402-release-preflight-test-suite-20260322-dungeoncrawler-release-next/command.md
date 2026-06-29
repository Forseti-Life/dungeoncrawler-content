- Agent: qa-dungeoncrawler
- Status: pending
- command: |
    Release-cycle QA preflight (run once per release cycle).

    - Site: dungeoncrawler
    - Product team: dungeoncrawler
    - Release id: 20260322-dungeoncrawler-release-next

    Goal:
    - As the FIRST QA task of this release cycle, review + refactor the QA test automation scripts and configs.
    - This is a process improvement step that happens once per release cycle.

    Required review/refactor targets (scripted; no GenAI required):
    - scripts/site-audit-run.sh
    - scripts/site-full-audit.py
    - scripts/site-validate-urls.py
    - scripts/drupal-custom-routes-audit.py
    - scripts/role-permissions-validate.py
    - org-chart/sites/dungeoncrawler/qa-permissions.json (role matrix + cookie env vars)

    Expectations:
    - Confirm newly discovered URLs from scans are included in validation (union validation).
    - Confirm role coverage matches all relevant Drupal roles for this site (update qa-permissions.json roles list as needed).
    - Keep production audits gated behind ALLOW_PROD_QA=1.

    Deliverables:
    - If changes are needed: commit fixes to HQ (git add/commit) and reference commit hash in your outbox.
    - If no changes: outbox should explicitly state "preflight complete; no changes needed".

    Then proceed with normal QA verification work for release-bound items.
