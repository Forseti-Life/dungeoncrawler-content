# Feature Brief

- Work item id: forseti-dev-master-worker-sync
- Website: infrastructure
- Module: copilot-hq
- Status: in-progress
- Priority: P1
- Feature type: implementation
- PM owner: pm-infra
- Dev owner: dev-infra
- QA owner: qa-infra
- Release target: next
- Source: 2026-04-19 production-master local-worker design session

## Goal

Create a production-master to local-worker control-plane where production can
assign work to the development laptop, with JobHunter work routed by default to
`dev-forseti`, using the existing HQ inbox/executor model and git-backed command
transport.

## Non-goals

- Removing human review from production promotion.
- Letting the laptop choose work independently of production.
- Replacing GitHub Issues or the HQ feature registry.

## Acceptance criteria

- `inbox/commands/*.md` supports laptop-targeted commands via `target: dev-laptop`.
- HQ orchestrator leaves laptop-targeted commands untouched.
- A laptop loop can claim one targeted command, convert it into a normal agent
  inbox item, and optionally execute the assigned seat.
- Default routing for JobHunter commands uses `target_agent: dev-forseti`.
- A systemd user service exists to keep the laptop loop running.
- Protocol and integration points are documented with mermaid diagrams.

## Risks

- Auto-pull can conflict with dirty local work if guardrails are disabled.
- Auto-commit/auto-push should remain off until the loop is trusted.
- Production and laptop must both honor the same routing rules.

## Latest updates

- 2026-04-19: Created protocol runbook, dispatch scripts, worker loop, and
  systemd user-service installer.
