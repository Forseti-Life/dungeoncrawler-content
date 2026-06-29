- command: |
    Process gap fix (dispatched by agent-code-review improvement round, dungeoncrawler release-b 20260408):

    GAP-DC-RB-IR-01: Post-push audit script queues gate2-ready inbox items for already-closed releases.

    Evidence: After the coordinated push at fd165f722 completed and 20260407-dungeoncrawler-release-b closed,
    the post-push audit script dispatched 2 stale gate2-ready inbox items (20260408-002709, 20260408-003320)
    for the already-shipped release. pm-dungeoncrawler processed them as no-ops (ROI 1 each), consuming 2
    unnecessary inbox cycles.

    Task:
    1. Identify the script that dispatches gate2-ready inbox items for dungeoncrawler after a post-push audit.
    2. Add a release-state guard: before dispatching a gate2-ready item, verify that the release ID is still
       open (i.e., NOT present in `tmp/release-cycle-archive/` or closed via `release-close-now`).
       If the release is already closed, log "gate2-ready skipped: release <ID> already closed" and exit 0.
    3. Acceptance criteria:
       - `bash scripts/<script> dungeoncrawler 20260407-dungeoncrawler-release-b` (closed release) exits 0
         with "skipped: release already closed" log, and creates NO inbox item.
       - `bash scripts/<script> dungeoncrawler 20260408-dungeoncrawler-release-c` (open release) proceeds
         normally and creates the inbox item.
    4. Commit with: git add -f + git commit.
    5. Include commit hash in outbox.

    Owner: dev-infra
    ROI: 15
- Agent: dev-infra
- Status: pending
