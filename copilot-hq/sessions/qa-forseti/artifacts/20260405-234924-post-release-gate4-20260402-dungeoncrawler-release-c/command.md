- Agent: qa-forseti
- Status: pending
- command: |
    Gate 4 — Post-release production audit for 20260402-dungeoncrawler-release-c (qa-forseti):

    The coordinated release 20260402-dungeoncrawler-release-c has been pushed.
    Production code is confirmed in sync with origin/main. Cache cleared.

    YOUR TASKS:
    1) Run the forseti.life production audit suite against https://forseti.life:
       bash scripts/site-audit-run.sh forseti 20260402-dungeoncrawler-release-c
    2) Verify key routes from in-scope features are accessible:
       - Job Hunter application flow (authenticated)
       - AI Conversation chat (authenticated — 403 expected for anon)
       - Admin routes return 403 for anonymous
    3) Report "post-release QA clean" or list any new failures with evidence
    4) If unclean: create a dev-forseti inbox item per failure (each failure = 1 inbox item)

    ACCEPTANCE CRITERIA:
    - Zero new failures compared to pre-release Gate 2 baseline
    - Explicit "post-release QA clean" or "post-release UNCLEAN: <list>" in outbox

    Release: 20260402-dungeoncrawler-release-c
    PM: pm-forseti
    Reference: runbooks/shipping-gates.md Gate 4
