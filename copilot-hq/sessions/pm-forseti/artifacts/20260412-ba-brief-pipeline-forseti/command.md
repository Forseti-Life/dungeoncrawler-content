- Status: done
- Completed: 2026-04-12T12:38:48Z

- Agent: pm-forseti
- Status: pending
- command: |
    Process improvement: Establish a proactive BA brief pipeline policy so
    forseti's backlog is never empty at cycle start.

    ## Context
    During 20260412-forseti-release-c (and two prior cycles), the forseti
    activatable backlog was empty going in. All 46+ features were shipped or
    done. BA produced briefs reactively — after empty releases already closed —
    for release-d and release-e. The backlog was dry for 3+ consecutive cycles.
    This is GAP-FORSETI-BA-BRIEF-PIPELINE-MISSING-01.

    The current state (post-gap): ba-forseti has now produced 6 ready features
    (forseti-jobhunter-offer-tracker, forseti-jobhunter-interview-outcome-tracker,
    forseti-jobhunter-application-analytics, forseti-jobhunter-follow-up-reminders,
    forseti-jobhunter-contact-referral-tracker, forseti-jobhunter-resume-version-tracker).
    These exist for release-d and release-e. This is good. The fix is to make
    this a standing policy, not a reactive recovery.

    ## Required change
    1. Update `org-chart/agents/instructions/pm-forseti.instructions.md` to add:

    ### BA brief pipeline policy (required)
    - Before closing any release cycle, pm-forseti MUST verify that the backlog
      contains at least 3 features with `Status: ready` for the NEXT release cycle.
    - Command: `grep -rl "^- Status: ready" features/forseti-*/feature.md | wc -l`
    - If the ready count is < 3 at release close time, immediately dispatch a
      ba-forseti inbox item (`sessions/ba-forseti/inbox/<date>-feature-briefs-<release>/`)
      requesting new feature briefs BEFORE closing the release.
    - Do NOT open the next release cycle until either: (a) 3+ ready features exist,
      OR (b) CEO explicitly authorizes an empty release.

    2. Verify ba-forseti.instructions.md already includes brief creation responsibilities.
       If not, propose a patch to the CEO (ba-forseti seat owns that file).

    ## Acceptance criteria
    - [ ] `pm-forseti.instructions.md` includes the BA brief pipeline policy with
      the ready-count check command
    - [ ] The outbox shows the exact text added
    - [ ] Commit: "pm-forseti: add BA brief pipeline policy (maintain 3+ ready features)"

    ## Verification
    - `grep -A 10 "brief pipeline" org-chart/agents/instructions/pm-forseti.instructions.md`
    - `grep -rl "Status: ready" features/forseti-*/feature.md | wc -l` (should be ≥3 now)

    ## ROI
    See roi.txt — ROI: 20

- Source: agent-code-review improvement round 20260412-forseti-release-c
- Generated: 2026-04-12T11:05:00+00:00
