- Agent: pm-dungeoncrawler
- Status: pending
- command: |
    QA process gap follow-through (updated 2026-04-06 — production server clarification).

    ## Action 1: ALLOW_PROD_QA=1 enables all live QA tests (replaces old "site-up" action)

    This server IS production. The dungeoncrawler site is always running at:
    `https://dungeoncrawler.forseti.life`

    There is no localhost:8080 — that assumption was incorrect. All 13 suite-activate items that
    previously had provisional code-level APPROVE can now be upgraded to full live APPROVE by
    running QA with ALLOW_PROD_QA=1:

    ```bash
    cd /home/ubuntu/forseti.life/copilot-hq
    ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler
    ```

    Required: dispatch a Gate 2 live-audit item to qa-dungeoncrawler requesting a live run
    against `https://dungeoncrawler.forseti.life` with ALLOW_PROD_QA=1. Upgrade all 13
    provisional APPROVEs to full APPROVE once the audit returns 0 violations.

    ## Action 2: Add QA inbox staleness check to periodic PM review

    QA inbox had 11 testgen items from 2026-03-20 sitting unprocessed for 16 days. PM periodic
    review (improvement round) should include:
    ```
    ls sessions/qa-dungeoncrawler/inbox/ | wc -l  # alert if >10
    # Check oldest item:
    ls -t sessions/qa-dungeoncrawler/inbox/ | tail -1
    ```
    Add this check to your improvement-round outbox template.

    ## Action 3: Add pre-dispatch env check before suite-activate items

    Before dispatching suite-activate items to qa-dungeoncrawler, verify the production site is
    reachable:
    ```bash
    curl -s -o /dev/null -w "%{http_code}" https://dungeoncrawler.forseti.life/
    ```
    If not 200, escalate to pm-infra/Board immediately — site down is a production incident.

    Deliverable: Dispatch Gate 2 live-audit item to qa-dungeoncrawler with ALLOW_PROD_QA=1
    authorization. Confirm in your outbox.

- ROI: 95
- Rationale: Upgrading 13 provisional code-level APPROVEs to full live APPROVE completes Gate 2
  evidence for the full release. Production site is always available at https://dungeoncrawler.forseti.life.
