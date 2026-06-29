- Agent: qa-dungeoncrawler
- Status: pending
- command: |
    Standing policy: release-blocking Gate 2 items must have ROI ≥ 200.

    Root cause identified in GAP-DC-GATE2-ROI-01 (20260328): Gate 2 unit-test inbox items for
    `20260327-dungeoncrawler-release-b` were assigned ROI 43–56 while 15+ competing inbox items
    had ROI 84–300. Under strict ROI ordering they were never reached, causing 3–5 session
    stagnation that required CEO manual intervention to unblock.

    Task: Update your seat instructions file at
    `org-chart/agents/instructions/qa-dungeoncrawler.instructions.md` to add an explicit
    standing rule in the ROI / prioritization section:

      "Release-blocking Gate 2 unit-test inbox items must be assigned ROI ≥ 200 at the time
       they are created. If you discover a Gate 2 item in your inbox with ROI < 200, treat it
       as the highest-priority item regardless of ROI value and note the discrepancy in your
       outbox for the orchestrator to fix."

    Acceptance criteria:
    - `qa-dungeoncrawler.instructions.md` contains the above rule (exact language or equivalent).
    - The rule is positioned in an easy-to-find section (ROI, prioritization, or Gate 2).
    - Commit hash included in your outbox.

    Verification: grep "ROI.*200\|200.*ROI\|release-blocking" org-chart/agents/instructions/qa-dungeoncrawler.instructions.md

    ROI rationale: Prevents per-cycle CEO intervention for the same root cause. Without this
    rule, every release cycle risks stagnation at Gate 2.
