- Agent: dev-infra
- Status: pending
- command: |
    Add a Gate 2 APPROVE evidence guard to scripts/release-signoff.sh.

    Root cause identified in KB lesson `knowledgebase/lessons/20260328-orchestrator-premature-signoff-artifact.md`:
    The orchestrator pre-populated `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260327-dungeoncrawler-release-b.md`
    with "Signed by: orchestrator" referencing a stale release ID before Gate 2 verification ran.
    `release-signoff-status.sh` showed `dungeoncrawler signoff: true` despite zero Gate 2
    APPROVE evidence existing. This is a near-miss — if pm-forseti's signoff had also been
    pre-populated, a push would have occurred with unverified code.

    Task: Modify `scripts/release-signoff.sh` to validate Gate 2 APPROVE evidence before
    writing the signoff artifact:

    1. Before writing the signoff artifact, scan `sessions/qa-dungeoncrawler/outbox/` for at
       least one file containing both the release-id argument AND the string "APPROVE".
    2. If no matching APPROVE evidence is found: print an error message and exit non-zero.
       Do NOT write the signoff artifact.
    3. If an existing signoff artifact contains "Signed by: orchestrator" with a mismatched
       release ID: overwrite it after the APPROVE guard passes (do not treat it as a real PM
       signoff).

    Acceptance criteria:
    - `scripts/release-signoff.sh <site> <release-id>` exits non-zero with an error message
      when no Gate 2 APPROVE evidence exists in qa-<site>/outbox/ for that release-id.
    - Exits 0 and writes the signoff artifact when APPROVE evidence is present.
    - Existing test: run `bash scripts/release-signoff.sh dungeoncrawler fake-release-id-test`
      → must exit non-zero ("No Gate 2 APPROVE evidence found").
    - Commit hash included in outbox.

    Verification:
      bash scripts/release-signoff.sh dungeoncrawler fake-release-id-test
      echo "Exit code: $?"   # must be non-zero

    ROI rationale: Prevents a class of phantom-Gate-2-pass releases. One change eliminates the
    risk of shipping unverified code when orchestrator-generated signoff artifacts are stale.
