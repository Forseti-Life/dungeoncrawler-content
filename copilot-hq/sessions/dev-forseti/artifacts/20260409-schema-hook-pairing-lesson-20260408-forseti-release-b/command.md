- command: |
    Add a KB lesson and a dev implementation checklist item for schema hook pairing
    (update hook + hook_schema() must be updated together).

    Background: FR-RB-02 (2026-04-08) — `age_18_or_older` was added to the DB via
    `job_hunter_update_9039` but NOT added to `hook_schema()`. Code review flagged LOW;
    PM accepted the risk; the fix was deferred to forseti-release-d (commit 835d8290c).
    This is a recurring pattern (same gap appeared in dungeoncrawler-release dc_chat_sessions,
    dc_campaign_characters.version in April 2026). Both install paths (fresh + upgrade) must
    be kept in sync.

    ## Acceptance criteria
    1. KB lesson file exists at `knowledgebase/lessons/20260409-schema-hook-pairing-db-columns.md`
       with:
       - Pattern: when adding a DB column via update hook, ALSO add to hook_schema() in same commit
       - Detection: `grep -n 'addField\|changeField\|dropField' <module>.install | head -20` — each
         addField must have a matching entry in hook_schema()
       - Rationale: fresh-install path uses hook_schema(); upgrade path uses update hooks; both must agree
    2. dev-forseti seat instructions (`org-chart/agents/instructions/dev-forseti.instructions.md`)
       include a "DB column checklist" entry under implementation standards:
       "When adding a DB column via `$schema->addField()` in a `hook_update_N()`, always update
        the corresponding `hook_schema()` in the same commit."
    3. No new schema-hook gap findings in the next forseti code review.

    ## Verification
    - Read `knowledgebase/lessons/20260409-schema-hook-pairing-db-columns.md` — exists and contains
      the detection command.
    - Read `org-chart/agents/instructions/dev-forseti.instructions.md` — DB column checklist entry present.

    ## Scope
    - New file: `knowledgebase/lessons/20260409-schema-hook-pairing-db-columns.md`
    - Target: `org-chart/agents/instructions/dev-forseti.instructions.md` (owned by dev-forseti)

- Agent: dev-forseti
- Status: pending
- Release: 20260409-forseti-release-b
- Source: improvement-round-20260408-forseti-release-b (agent-code-review)
