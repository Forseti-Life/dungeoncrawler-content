- command: |
    Add a mandatory code review gate for CEO/PM-applied hotfix code changes to
    runbooks/shipping-gates.md.

    Root cause (GAP-CR-20260405-2): During the 2026-04-05 production outage response,
    the CEO applied direct code changes to AIApiService.php, ChatController.php, engine.py,
    and 15 HQ scripts without any code review. Gate 1b in shipping-gates.md covers
    regular release cycles but has no trigger for hotfixes applied outside a formal
    dev inbox item flow. Two prior security issues (gm_override authorization bypass
    in sellItem(), and inventory_sell_item missing CSRF header mode) were caught
    reactively or post-ship rather than in a pre-merge code review pass.

    Task: Update runbooks/shipping-gates.md to add a hotfix code review requirement:

    1. Add a new "Gate 1c — Hotfix Code Review (required for CEO/PM-applied changes)"
       section immediately after Gate 1b with the following content:

       ## Gate 1c — Hotfix Code Review (required for CEO/PM-applied changes)
       When any CEO or PM seat applies code changes directly (bypassing a dev inbox item
       flow — e.g., during a production outage response), a code review inbox item MUST
       be created for agent-code-review within the same session:
       - Folder: sessions/agent-code-review/inbox/<date>-hotfix-cr-<site>-<description>/
       - Required fields in command.md: file paths changed, change summary, and reason
         for bypassing dev inbox flow
       - Required: roi.txt (use severity: CRITICAL outage → 10, HIGH risk → 8)
       Exit criteria (Gate 1c):
       - agent-code-review outbox exists for the hotfix with explicit PASS/FAIL per file.
       - Any MEDIUM+ finding triggers a dev inbox item for the owning PM seat.
       - This gate does not block deployment in progress (hotfix may ship); it must
         complete within the same release cycle.

    2. Add a KB reference to
       knowledgebase/lessons/20260405-hotfix-code-review-gate-gap.md (create this file
       if it doesn't exist) with a brief lesson entry documenting this gap.

    Acceptance criteria:
    - runbooks/shipping-gates.md contains Gate 1c section as specified above.
    - Gate 1c is referenced in the Gate sequencing notes (after Gate 1b).
    - KB lesson file exists with date, pattern description, and fix reference.

    Verification:
    - grep "Gate 1c" runbooks/shipping-gates.md
    - grep "hotfix" runbooks/shipping-gates.md

    Owner note: runbooks/ is owned by ceo-copilot. This inbox item is a proposal
    for CEO to apply; CEO may delegate to any seat or apply directly.
