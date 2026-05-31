# Git Management (Sessions as Audit Trail)

This repo treats `sessions/` as an append-only audit log.

## What must be tracked
- `sessions/**/inbox/**`: work requests and inputs to an agent seat.
- `sessions/**/outbox/**`: agent outputs and status updates.
- `sessions/**/artifacts/**`: sanitized artifacts that support traceability.
- `inbox/commands/**`: queued CEO/PM commands.
- `inbox/processed/**`: audit trail of processed commands (moved from `inbox/commands/`).

## What must not be tracked
- Raw local Copilot logs / transient state:
  - `**/events.jsonl`, `**/workspace.yaml`, `**/checkpoints/`
- Runtime state / caches / locks:
  - `tmp/**`, `**/.exec-lock/**`, `tmp/.agent-exec-*.lock`, PID files
- Volatile pointers:
  - `sessions/**/artifacts/**/latest` (often a moving symlink)

## Operational rule
- Prefer **move/rename** over delete for processed items so git history preserves the chain of custody.

## Artifact hygiene
- Keep artifacts sanitized and reasonably sized.
- Prefer text/markdown summaries or links for large binaries.
