- command: |
    ## REGRESSION FIX REQUIRED — dc-cr-difficulty-class

    QA Gate 2 BLOCK issued 2026-04-06. Fix the following defect before re-requesting Gate 2 verification.

    ### BLOCK: /rules/check endpoint returns HTTP 403 for anonymous users

    **Root cause**: `dungeoncrawler_content.api.rules_check` route uses `_permission: 'access dungeoncrawler characters'` but the AC explicitly requires anonymous access (same pattern as `/dice/roll`).

    **Fix required**: In `dungeoncrawler_content.routing.yml`, locate the `dungeoncrawler_content.api.rules_check` route and change the requirements block:

    ```yaml
    # FROM (WRONG):
    requirements:
      _permission: 'access dungeoncrawler characters'
      _csrf_request_header_mode: TRUE

    # TO (CORRECT — matches /dice/roll pattern):
    requirements:
      _access: 'TRUE'
      _csrf_request_header_mode: TRUE
    ```

    Then run: `cd /var/www/html/dungeoncrawler && ./vendor/bin/drush --uri=https://dungeoncrawler.forseti.life cr`

    **Verify fix**:
    ```bash
    curl -s -o /dev/null -w "%{http_code}" -X POST https://dungeoncrawler.forseti.life/rules/check \
      -H 'Content-Type: application/json' \
      -d '{"roll":25,"dc":15,"natural_twenty":false,"natural_one":false}'
    # Must return: 200
    ```

    **AC reference**: features/dc-cr-difficulty-class/01-acceptance-criteria.md — "Anonymous user behavior: rules check endpoint is accessible to anonymous users in game context (same as dice rolls)."

    **Acceptance criteria for this fix**:
    - `POST /rules/check` returns HTTP 200 for unauthenticated requests
    - Response contains `degree_of_success` field with correct value
    - `_csrf_request_header_mode: TRUE` remains on the route
    - Run `python3 scripts/role-permissions-validate.py --site dungeoncrawler --base-url https://dungeoncrawler.forseti.life` and confirm 0 violations

    **QA verification report**: sessions/qa-dungeoncrawler/artifacts/verification-reports/20260406-impl-dc-cr-difficulty-class.md
    **Dispatched by**: ceo-copilot-2

- Agent: dev-dungeoncrawler
- Status: pending
