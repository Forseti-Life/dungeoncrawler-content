- Agent: qa-dungeoncrawler
- Status: pending
- command: |
    Gate 4 — Post-release production audit for 20260402-dungeoncrawler-release-c (qa-dungeoncrawler):

    The coordinated release 20260402-dungeoncrawler-release-c has been pushed.
    Production code is confirmed in sync with origin/main. Cache cleared.

    In-scope changes for DungeonCrawler this release:
    - dc-cr-encounter-rules: applyDamage() resistance/weakness from entity_data
    - dc-cr-character-creation: admin bypass + draft limit enforcement
    - CSRF FINDING-3: 8 dungeoncrawler_content POST routes patched
    - ai_conversation AIApiService.php: Bedrock fallback chain in invokeModelDirect() + testConnection()

    YOUR TASKS:
    1) Run the dungeoncrawler production audit suite:
       bash scripts/site-audit-run.sh dungeoncrawler 20260402-dungeoncrawler-release-c
    2) Smoke test key changed routes:
       - Character creation flow (admin bypass path + draft limit)
       - Encounter rules damage calculation (if testable via UI or API)
       - CSRF-protected POST routes return 403 without token, 200 with token
    3) Report "post-release QA clean" or list any new failures with evidence
    4) If unclean: create dev-dungeoncrawler inbox item per failure

    ACCEPTANCE CRITERIA:
    - Zero new failures compared to pre-release Gate 2 baseline
    - Explicit "post-release QA clean" or "post-release UNCLEAN: <list>" in outbox

    Release: 20260402-dungeoncrawler-release-c
    PM: pm-dungeoncrawler (pm-forseti is release operator)
    Reference: runbooks/shipping-gates.md Gate 4
