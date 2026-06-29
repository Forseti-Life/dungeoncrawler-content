# Acceptance Criteria — Production Master to Dev Worker Sync

**Feature:** `forseti-dev-master-worker-sync`

## AC-1 — Targeted command envelope exists

- Commands can be created under `inbox/commands/*.md` with:
  - `target: dev-laptop`
  - `target_agent`
  - `work_item`
  - `topic`
- Recommended routing fields (`website`, `module`, `branch`, `roi`, `execute`) are documented.

## AC-2 — HQ orchestrator ignores worker-targeted commands

- Commands targeted to `dev-laptop` are not consumed by the normal HQ orchestrator.
- Worker-targeted commands remain available until claimed by the worker loop.

## AC-3 — Worker claim flow converts commands into normal inbox work

- Worker claim logic creates a normal seat inbox item under `sessions/<target_agent>/inbox/<item>/`.
- Created packet includes:
  - `command.md`
  - `README.md`
  - `00-problem-statement.md`
  - `01-acceptance-criteria.md`
  - `06-risk-assessment.md`
  - `roi.txt`

## AC-4 — Default routing is explicit

- JobHunter work defaults to the worker-node target and the configured target seat according to the current routing registry / runbook.
- Production remains authoritative for priority and sequencing.

## AC-5 — Worker loop execution path is documented and runnable

- One-shot sync path exists for claim/dispatch.
- Loop/service path exists for ongoing polling.
- Operator can install a user service to keep the worker loop running.

## AC-6 — Git-backed transport remains the durable message bus

- Commands survive restart on both sides because transport is repository-backed.
- Claimed commands move to a processed ledger after dispatch.

## AC-7 — Safe operational constraints are explicit

- Worker does not invent backlog priority.
- Auto-push/auto-commit remain opt-in.
- Dirty-worktree and pull/rebase guardrails are documented.

## AC-8 — Diagrams and protocol docs exist

- End-to-end topology, lifecycle, and command flow are documented with mermaid diagrams.
- The runbook is sufficient for infra/dev/QA to reason about the protocol.

## AC-9 — Smoke path is testable

- A smoke script or documented smoke sequence exists to prove:
  1. command creation,
  2. worker claim,
  3. inbox dispatch,
  4. optional seat execution,
  5. processed-ledger movement.
