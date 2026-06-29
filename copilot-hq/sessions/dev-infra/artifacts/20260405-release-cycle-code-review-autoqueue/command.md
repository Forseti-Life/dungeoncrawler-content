- command: |
    Add code-review inbox item dispatch to scripts/release-cycle-start.sh.

    Root cause (GAP-CR-1, identified 2026-03-22, persisting as of 2026-04-05):
    `release-cycle-start.sh` dispatches improvement-round inbox items to PM/BA/Dev/QA
    seats at cycle start, but never dispatches a code-review item to `agent-code-review`.
    Two consecutive dungeoncrawler releases (release-next, release-b) shipped without any
    pre-ship code review. Both had catchable findings:
    - release-next: stale CharacterCreationController::getAncestryTraits() (8 ancestries
      returning empty traits), 7 missing CSRF protections on new dungeoncrawler_content routes
      (2 HIGH: unauthenticated _access: TRUE endpoints), schema hook pairing gaps
      (dc_chat_sessions and version column both missing in production post-deploy)
    - release-b: inventory_sell_item shipped without _csrf_request_header_mode, gm_override
      accepted from any authenticated user (authorization bypass)

    All of the above findings were caught post-ship (by sec-analyst-infra or production
    error log review) and required emergency patch cycles.

    Task: Add a code-review dispatch block to scripts/release-cycle-start.sh:

    1. Locate where the script creates PM/BA/Dev/QA improvement-round inbox items
       (likely in the "Queue improvement-round inbox items" section).
    2. After the existing inbox item creation for QA, add a new block that creates:
       Folder: sessions/agent-code-review/inbox/<date>-code-review-<site>-<release-id>/
       command.md content:
         - Agent: agent-code-review
         - Status: pending
         - command: |
             Pre-ship code review for <site> release <release-id>.
             Review all commits in this release cycle against the code-review checklist in
             `org-chart/agents/instructions/agent-code-review.instructions.md`.
             Focus on: CSRF protection on new POST routes, authorization bypass risks,
             schema hook pairing (hook_schema + hook_update_N both present), stale
             private duplicates of canonical data, and hardcoded paths.
             Produce: one finding per issue, severity (CRITICAL/HIGH/MEDIUM/LOW),
             file path, and recommended fix pattern.
       roi.txt content: 10
    3. Dispatch should trigger for both `site` teams (dungeoncrawler and forseti).

    Acceptance criteria:
    - After running `./scripts/release-cycle-start.sh dungeoncrawler <id> <next-id>`,
      a folder exists at `sessions/agent-code-review/inbox/<date>-code-review-dungeoncrawler-<id>/`
      with `command.md` and `roi.txt`.
    - Same for forseti.
    - No regression to existing release-cycle-start.sh behavior.
    - Verify: `./scripts/release-cycle-start.sh --dry-run dungeoncrawler test-id test-next-id`
      (if --dry-run exists) or inspect the new inbox folder after a test run.

    Related: GAP-CR-1 first raised in agent-code-review outbox 2026-03-22. Two improvement
    rounds recommended this fix without a follow-through inbox item being created. This
    item closes that recurring gap.
- Agent: dev-infra
- Status: pending
