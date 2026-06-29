# Copilot Instructions — Forseti HQ Startup Profiles

Use the canonical startup profile definitions under `org-chart/agents/instructions/`, not ad hoc copies.

## Canonical HQ root

- **Live HQ root:** `/home/ubuntu/forseti.life`
- The nested `copilot-hq/` directory is a subtree/export artifact only. Do **not** treat it as the live repo root for CEO or architect session startup.

## Canonical profile entrypoint

- Shared startup profile shortcuts live at:
  - `org-chart/agents/instructions/copilot-user-startup.instructions.md`

## CEO profile

When the user asks for the CEO persona/session, load this instruction stack from `/home/ubuntu/forseti.life`:

1. `org-chart/org-wide.instructions.md`
2. `org-chart/roles/ceo.instructions.md`
3. `org-chart/agents/instructions/ceo-copilot-2.instructions.md`

Primary session paths:
- `sessions/ceo-copilot-2/current-session-state.md`
- `sessions/ceo-copilot-2/inbox/`
- `sessions/ceo-copilot-2/outbox/`

## Architect profile

When the user asks for the architect persona/session, load this instruction stack from `/home/ubuntu/forseti.life`:

1. `org-chart/org-wide.instructions.md`
2. `org-chart/roles/architect.instructions.md`
3. `org-chart/agents/instructions/architect-copilot.instructions.md`

Primary session paths:
- `sessions/architect-copilot/current-session-state.md`
- `sessions/architect-copilot/outbox/`
- `sessions/architect-copilot/artifacts/`

## Notes

- Treat the seat instruction files above as the source of truth for persona-specific startup behavior.
- If this file and a seat instruction file ever disagree, the seat instruction file wins.
