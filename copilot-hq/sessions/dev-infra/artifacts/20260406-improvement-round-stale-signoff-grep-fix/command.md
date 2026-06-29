- command: |
    Fix stale-signoff grep pattern in improvement-round.sh (GAP-B-01):

    CONTEXT: `scripts/improvement-round.sh` contains a stale-signoff check to prevent
    improvement rounds from firing for empty/orchestrator-signed releases. The check uses:

        grep -q "Signed by: orchestrator" "$signoff_file"

    But the actual format written by the orchestrator in release signoff artifacts is:

        **Signed by**: orchestrator (coordinated release ...)

    Because of the markdown bold markers (`**`), the pattern `"Signed by: orchestrator"`
    does NOT match `**Signed by**: orchestrator`. The stale check silently passes, and
    the improvement round fires for empty releases.

    EVIDENCE:
    - Signoff file: sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260406-dungeoncrawler-release-b.md
    - Content: `**Signed by**: orchestrator (coordinated release 20260405-dungeoncrawler-release-c shipped)`
    - Check in improvement-round.sh (approx. line 72): `grep -q "Signed by: orchestrator"`
    - Result: MISS — improvement round dispatched for empty release-b, wasting all agent cycles

    TASKS:
    1. In `scripts/improvement-round.sh`, find the stale-signoff grep check.
    2. Fix the pattern to match both plain and markdown-bold forms. Use:
           grep -qiE "(\*\*)?Signed by(\*\*)?:? orchestrator" "$signoff_file"
       Or simpler: match on the unambiguous substring that appears in both forms:
           grep -q "orchestrator" "$signoff_file" && grep -qi "signed.by" "$signoff_file"
       Or use a single flexible pattern:
           grep -qP "(?:\*\*)?Signed by(?:\*\*)?:?\s+orchestrator" "$signoff_file"
    3. Also add a secondary check: if the signoff file contains `--empty-release` OR
       the release-signoff status indicates 0 shipped features, skip improvement-round
       dispatch and emit: "SKIP: empty release — improvement-round not applicable."
    4. Test: create a mock signoff file with the markdown-bold format and confirm
       the check now correctly detects it as stale.
    5. Commit changes.

    ACCEPTANCE CRITERIA:
    - `grep` pattern in improvement-round.sh matches `**Signed by**: orchestrator ...`
    - A mock test with the markdown-bold signoff format correctly triggers the SKIP path.
    - improvement-round.sh dispatches correctly for non-empty, PM-signed releases.
    - No regression on the existing signoff-required gate.

    VERIFICATION:
    - bash scripts/improvement-round.sh 20260406-dungeoncrawler-release-b dungeoncrawler
      should output "SKIP: stale orchestrator signoff artifact detected" (or similar)

    ROI: 45
    Rationale: This bug caused all agent seats to waste full execution cycles on an empty
    improvement round for release-b. Recurrence rate is high (every orchestrator-signed
    empty release). Fix is a 1-line grep pattern change.
- Agent: dev-infra
- Status: pending
- Routed-by: agent-code-review
- Review-source: sessions/agent-code-review/outbox/20260406-improvement-round-20260406-dungeoncrawler-release-b.md
- Gap: GAP-B-01
