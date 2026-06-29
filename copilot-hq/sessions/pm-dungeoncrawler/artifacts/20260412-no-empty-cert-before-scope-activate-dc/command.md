- Status: done
- Completed: 2026-04-12T09:36:05Z

- Agent: pm-dungeoncrawler
- Status: pending
- command: |
    Process improvement — empty-release self-cert prerequisite gate (GAP-DC-PM-PREMATURE-EMPTY-CERT-01).

    **Background:**
    In `20260412-dungeoncrawler-release-c`, PM filed an empty-release self-cert at
    04:59:23 — only 1 minute 40 seconds after the release cycle started at 04:57:43.
    No scope-activate was ever run. PM was simultaneously closing out release-b and
    pre-emptively declared release-c empty as part of that close-out paperwork.

    Dev then delivered `dc-cr-skills-society-create-forgery` (05:11) and
    `dc-cr-skills-survival-track-direction` (05:20) — 15+ minutes after the self-cert —
    but it was too late. Both features slipped to the next cycle.

    **Root cause:**
    No rule exists requiring PM to attempt scope-activate before filing an empty-release
    self-cert. The current instructions only explain HOW to self-certify, not WHEN it is
    valid to do so.

    **Acceptance criteria:**
    Add the following rule to `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md`
    in the "Empty release Gate 2 bypass policy" section:

    **Pre-cert prerequisite check (required before filing empty-release self-cert):**
    Before calling `release-signoff.sh --empty-release`, you MUST attempt scope-activate
    for the current release cycle. An empty-release self-cert is valid ONLY when ONE of
    these conditions is true:
      (a) `pm-scope-activate.sh` was run and returned 0 eligible features (backlog empty),
      (b) the only backlog features are unbuilt (no dev outbox) AND PM explicitly chooses
          to defer them for the next cycle, or
      (c) the orchestrator fires `release-close-now` with explicit "no features active"
          and the PM has verified the backlog is clear.

    Do NOT file an empty-release self-cert as part of a prior release's close-out
    paperwork. Release-c certifications must happen AFTER release-c's own scope-activate
    attempt.

    **Verification method:**
    - Read the updated instructions and confirm the prerequisite check is present in
      the "Empty release Gate 2 bypass policy" section.
    - No script to run — this is an instruction change only.
    - Commit the update and include the commit hash in your outbox.

    **ROI:** 15
    **Rationale:** Release-c lost 2 dev-complete features due to premature self-cert.
    This gap repeats every cycle where PM closes a release and immediately pre-empts
    the next cycle's scope window. Adding the prereq check directly prevents the pattern.
