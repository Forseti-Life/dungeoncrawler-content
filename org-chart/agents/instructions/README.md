# Agent (Seat) Instructions

This org uses a layered instruction stack:
- Org-wide: `org-chart/org-wide.instructions.md`
- Role: `org-chart/roles/<role>.instructions.md`
- Site/Product: `org-chart/sites/<site>/site.instructions.md`
- Seat: `org-chart/agents/instructions/<agent-id>.instructions.md`

Each configured agent seat can self-manage its own process flow and file ownership boundaries via a dedicated instructions file:

- `org-chart/agents/instructions/<agent-id>.instructions.md`

These files are loaded by the executor (see `scripts/agent-exec-next.sh`) after org-wide and role instructions.

Note: the executor also loads the matching per-site `site.instructions.md` based on the seat's `website_scope` in `org-chart/agents/agents.yaml`.

Rules:
- Each agent may update their own seat instructions file.
- If a required change touches files outside the seat’s declared scope, the agent must request the owning agent rather than editing directly.
- Supervisors are responsible for preventing multiple agents from modifying the same shared file concurrently.
