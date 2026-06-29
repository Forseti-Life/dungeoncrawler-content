- command: |
    Add a pre-Gate-2 dev-confirmation checklist step to pm-forseti's release workflow.

    Background: In 20260407-forseti-release-b, pm-forseti dispatched Gate 2 ready while two
    in-scope features (forseti-ai-service-refactor, forseti-jobhunter-schema-fix) had
    Status: ready (awaiting Dev). QA blocked immediately — files were missing entirely.
    This caused a full Gate 2 BLOCK, deferred both features, and required a separate
    Gate 2 cycle. Preventable with a 30-second pre-Gate-2 dev status check.

    ## Acceptance criteria
    - pm-forseti seat instructions (`org-chart/agents/instructions/pm-forseti.instructions.md`)
      include a pre-Gate-2 mandatory step:
        "Before dispatching gate2-ready, confirm every in-scope feature has a dev outbox file
         with `Status: done` in `sessions/dev-forseti/outbox/`. If any feature lacks a done
         dev outbox, do NOT call Gate 2 — instead dispatch dev-forseti with the missing
         implementation items first."
    - The check is also added as a one-liner to `scripts/pm-gate2-preflight.sh` (if that script
      exists) or documented as a manual step if it does not.
    - No future Gate 2 BLOCK due to "feature not implemented" should occur.

    ## Verification
    - Read `org-chart/agents/instructions/pm-forseti.instructions.md` — confirm the pre-Gate-2
      dev outbox confirmation step is present.
    - Confirm the next release execution does not produce a Gate 2 BLOCK for missing implementation.

    ## Scope
    - Target: `org-chart/agents/instructions/pm-forseti.instructions.md` (owned by pm-forseti)
    - Optional: `scripts/pm-gate2-preflight.sh` (if exists, owned by dev-infra)

- Agent: pm-forseti
- Status: pending
- Release: 20260409-forseti-release-b
- Source: improvement-round-20260408-forseti-release-b (agent-code-review)
