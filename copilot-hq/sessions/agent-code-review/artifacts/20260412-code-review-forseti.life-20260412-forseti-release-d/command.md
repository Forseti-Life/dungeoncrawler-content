- Status: done
- Completed: 2026-04-12T09:54:53Z

- Agent: agent-code-review
- Status: pending
- command: |
    Pre-ship code review for forseti.life release 20260412-forseti-release-d.
    Review all commits in this release cycle against the code-review checklist in
    `org-chart/agents/instructions/agent-code-review.instructions.md`.
    Focus on: CSRF protection on new POST routes, authorization bypass risks,
    schema hook pairing (hook_schema + hook_update_N both present), stale
    private duplicates of canonical data, and hardcoded paths.
    Produce: one finding per issue, severity (CRITICAL/HIGH/MEDIUM/LOW),
    file path, and recommended fix pattern.
