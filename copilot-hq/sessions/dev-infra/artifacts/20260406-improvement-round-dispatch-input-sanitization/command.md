- command: |
    Sanitize release-id inputs in the improvement-round dispatch script (dev-infra):

    CONTEXT (GAP-DISPATCH-INJECT-01, confirmed 20260406): The improvement-round dispatch
    script does not sanitize release IDs before using them as folder names or CLI arguments.
    An unsanitized `--help` value was passed as a release ID, creating the inbox folder
    `--help-improvement-round` across all agent seats. This is an input-validation gap:
    - A release ID starting with `--` could be misinterpreted as a flag by downstream scripts
      that call `mkdir`, `ls`, or other tools with the release ID as an argument.
    - A release ID containing `/`, spaces, or shell metacharacters could cause path traversal
      or unintended behavior.
    - This is distinct from the premature-dispatch guard (GAP-26B-02 / 20260405-improvement-round-sequencing-fix).

    TASKS:
    1. Find the script(s) that create improvement-round inbox folder names (search for
       `improvement-round` in `scripts/` and `orchestrator/`).
    2. Add input validation for release IDs at the dispatch point:
       - Reject (skip dispatch) if release_id is empty, starts with `-`, or contains
         characters outside `[a-zA-Z0-9._-]`.
       - Log a warning to stderr: "WARN: Invalid release_id '<value>' — skipping improvement-round dispatch"
    3. Ensure that anywhere a release ID is interpolated into a shell command or path, it is
       double-quoted (e.g., mkdir "$RELEASE_ID-improvement-round" not mkdir $RELEASE_ID-improvement-round).
    4. Add a comment at the validation point referencing GAP-DISPATCH-INJECT-01.

    ACCEPTANCE CRITERIA:
    - Passing `--help` as a release ID to the dispatch logic produces no inbox folder and
      logs the warning to stderr.
    - Passing a release ID containing `/` or spaces produces no inbox folder and logs the warning.
    - Passing a valid release ID (e.g., `20260322-dungeoncrawler-release-next`) creates the
      inbox folder as before.
    - `bash -n` passes on the patched script(s).
    - grep for unquoted release ID variable interpolations in the patched scripts returns 0 results.

    ROI: 15 — unquoted/unsanitized release IDs in shell scripts are a latent correctness and
    security risk. The `--help` incident demonstrates the validation gap is reachable in
    normal dispatch operation.
- Agent: dev-infra
- Status: pending
