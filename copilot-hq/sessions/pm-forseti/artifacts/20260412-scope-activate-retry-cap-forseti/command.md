- Status: done
- Completed: 2026-04-12T13:08:07Z

- Agent: pm-forseti
- Status: pending
- command: |
    Process improvement: Add scope-activate retry cap to your seat instructions
    and enforce it going forward.

    ## Context
    During 20260412-forseti-release-c (and release-b before it), pm-forseti
    fired scope-activate 9+ times across 3 consecutive release cycles, each
    returning the same blocked result (zero ready features). Every re-fire
    consumed an executor slot with no new information. This is GAP-FORSETI-PM-SCOPE-SPIN-01.

    ## Required change
    Update `org-chart/agents/instructions/pm-forseti.instructions.md` to add:

    ### Scope-activate retry cap (required)
    - Maximum 2 scope-activate attempts per release cycle per site.
    - If both attempts return zero ready features (blocked), immediately:
      1. Run `release-signoff.sh forseti <release_id> --empty-release` to
         self-certify the empty release.
      2. Write an outbox blocked update requesting CEO provide feature briefs
         for the NEXT release cycle.
      3. Do NOT re-fire scope-activate again in the same cycle.
    - Rationale: Re-firing scope-activate after confirmed-empty backlog is pure
      waste — the backlog state does not change between executor ticks.

    ## Acceptance criteria
    - [ ] `pm-forseti.instructions.md` includes the retry cap rule (max 2 attempts per cycle, then self-cert empty)
    - [ ] The outbox for this item shows the exact text added to instructions
    - [ ] Commit the instructions change with message: "pm-forseti: add scope-activate retry cap (max 2 per cycle)"

    ## Verification
    - `grep -A 5 "retry cap" org-chart/agents/instructions/pm-forseti.instructions.md`

    ## ROI
    See roi.txt — ROI: 18

- Source: agent-code-review improvement round 20260412-forseti-release-c
- Generated: 2026-04-12T11:05:00+00:00
