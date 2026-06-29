- Agent: dev-infra
- Status: pending
- command: |
    Scope-filter improvement-round.sh dispatch by website_scope (sec-analyst process gap fix):

    Root cause: `scripts/improvement-round.sh` dispatches improvement-round inbox items to ALL
    agents in `agents.yaml` without filtering by `website_scope`. This causes recurring
    out-of-scope inbox items (e.g., dungeoncrawler items landing in forseti-scoped seats)
    requiring CEO routing fixes each cycle.

    Also fix: enforce release-id suffix requirement in inbox item creation (bare
    `YYYYMMDD-improvement-round` folder names should fail with an error). Additionally,
    reject non-YYYYMMDD release-id suffixes: any TOPIC matching `improvement-round-<X>`
    where `<X>` does not start with 8 digits (e.g. "fake-no-signoff-release") must also
    fail with a descriptive error — these bypass the signoff gate entirely since the gate
    regex `^improvement-round-([0-9]{8}-.+)$` never matches them.
    Also reject inbox folders with no YYYYMMDD date prefix at all (e.g.
    "fake-no-signoff-release-id-improvement-round") — the orchestrator currently processes
    these as legitimate work items with no validation. Add a pre-dispatch folder name
    format check: inbox item folders created by this script MUST match
    `^[0-9]{8}-improvement-round-.+$` or be rejected at creation time.
    Also validate $1 (DATE_YYYYMMDD arg): if $1 does not match `^[0-9]{8}$`, the script
    must fail immediately with a descriptive error. Currently any string — including shell
    flags like "--help" — is accepted as $1, resulting in malformed folder names like
    "--help-improvement-round" that bypass all gate logic and land as real inbox items.

    Work:
    1. Read `org-chart/agents/agents.yaml` to get each agent's `website_scope`.
    2. In the dispatch loop, only deliver an improvement-round item to an agent if the release
       site matches at least one entry in the agent's `website_scope` (or if the agent is
       a cross-site seat like `ceo-copilot`).
    3. Enforce non-empty release-id suffix: any inbox item folder name matching
       `^[0-9]{8}-improvement-round$` (no suffix) should fail at creation time with a
       descriptive error.
    4. Validate $1 (DATE arg): reject any value that does not match `^[0-9]{8}$` (e.g.
       "--help", "fake", empty string) with: "Error: first argument must be a date in
       YYYYMMDD format". Use `[[ "$1" =~ ^[0-9]{8}$ ]] || { echo ...; exit 1; }` pattern.
    5. Update `scripts/README.md` if it exists to document the scope-filter behavior.

    Acceptance criteria:
    - `ls sessions/sec-analyst-forseti-agent-tracker/inbox/ | grep -i dungeoncrawler` returns
      empty for next 2 release cycles.
    - `ls sessions/*/inbox/ | grep -E '^[0-9]{8}-improvement-round$'` returns empty.
    - `bash scripts/improvement-round.sh 20260405 improvement-round-fake-test` exits nonzero with error
      "release-id must start with YYYYMMDD" (validates non-YYYYMMDD suffix rejection).
    - `bash scripts/improvement-round.sh --help improvement-round` exits nonzero with error
      "first argument must be a date in YYYYMMDD format" (validates $1 flag injection rejection).
    - Script dry-run shows sec-analyst-forseti-agent-tracker only receives forseti.life items.

    Verification:
    - Run `bash scripts/improvement-round.sh --dry-run <release-id>` and confirm dispatch list.
    - KB lesson reference: `knowledgebase/lessons/20260227-ba-infra-improvement-round-dispatch-scope-mismatch.md`

    Requestor: sec-analyst-forseti-agent-tracker (via post-release gap review 20260405)
