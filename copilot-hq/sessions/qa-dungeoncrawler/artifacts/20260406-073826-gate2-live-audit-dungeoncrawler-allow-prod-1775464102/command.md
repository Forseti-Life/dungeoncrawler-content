- Agent: qa-dungeoncrawler
- Status: pending
- command: |
    Gate 2 live-audit: upgrade provisional APPROVEs to full live APPROVE.

    ## Context
    Production site `https://dungeoncrawler.forseti.life` is confirmed reachable (HTTP 200).
    ALLOW_PROD_QA=1 is now authorized. Previous suite-activate items issued provisional
    code-level APPROVEs because the live URL was thought to be localhost:8080 — that was
    incorrect. This server IS production.

    ## Required action
    Run the live site audit against production:
    ```bash
    cd /home/ubuntu/forseti.life/copilot-hq
    ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh dungeoncrawler
    ```

    ## Acceptance criteria
    1. `site-audit-run.sh` exits 0 with 0 violations (or all violations documented as
       accepted risk).
    2. Audit output saved to `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/`.
    3. `permissions-validation.json` in audit output has `"base_url": "https://dungeoncrawler.forseti.life"`.
    4. All provisional code-level APPROVEs from prior suite-activate items are upgraded to
       full live APPROVE in the audit report.
    5. Outbox confirms: "Live audit PASS — N violations — base_url: https://dungeoncrawler.forseti.life".

    ## Verification
    ```bash
    latest=$(ls -1d sessions/qa-dungeoncrawler/artifacts/auto-site-audit/*/ 2>/dev/null | sort | tail -1)
    python3 -c "import json; d=json.load(open('${latest}permissions-validation.json')); print('base_url:', d['base_url'])"
    # Must print: base_url: https://dungeoncrawler.forseti.life
    ```

    ## Notes
    - Do NOT run recursive crawls or destructive probes.
    - If audit returns violations: document each, classify as APPROVE (accepted risk) or BLOCK (must fix).
    - If site returns non-200 during run: stop, escalate to pm-dungeoncrawler as production incident.
