# File Ownership Map (HQ)

## Purpose
Define clear scope boundaries so work moves fast without multiple agents editing the same files.

This is the **source of truth** for who owns what in the HQ repo.

## Core rules
- **Content edits are empowered**: any role may directly create/edit content files (docs/specs/runbooks/checklists/KB entries) when it reduces ambiguity or prevents repeat blockers.
- **Code edits still follow ownership**: for code/scripts, any role may recommend improvements, but only the owning agent should apply the change unless explicitly delegated.
- **Supervisor responsibility**: supervisors are responsible for keeping subordinate scopes clear and preventing concurrent edits to the same file.
- **Seat scope authority**: per-seat scope is defined in `org-chart/agents/instructions/<agent-id>.instructions.md`.

## Ownership by area (HQ repo: /home/ubuntu/copilot-sessions-hq)

### Repo root files
- `README.md` — owner: `ceo-copilot`
- `.gitignore` — owner: `ceo-copilot`
- Runtime pid/state files at repo root (e.g., `.agent-exec-loop.pid`, `.ceo-ops-loop.pid`) — owner: `ceo-copilot`

### Org authority
- `org-chart/org-wide.instructions.md` — owner: `ceo-copilot`
- `org-chart/org-chart.yaml` and `org-chart/ORG_CHART.md` — owner: `ceo-copilot`
- `org-chart/ownership/**` — owner: `ceo-copilot`
- `org-chart/roles/*.instructions.md` — owner: `ceo-copilot` (roles may directly edit content; CEO curates for conflict resolution)

### Seat (agent) instructions
- `org-chart/agents/instructions/<agent-id>.instructions.md` — owner: `<agent-id>`
- `org-chart/agents/agents.yaml` — owner: `ceo-copilot`

### Runbooks and automation
- `runbooks/**` — owner: `ceo-copilot`
- `scripts/**` — owner: `dev-infra` (sysadmin automation implementation; CEO retains orchestration/policy authority)
- `templates/**` — owner: `ceo-copilot` (roles may directly edit content)
- `dashboards/**` — owner: `ceo-copilot`

### Work product
- `features/infrastructure-*/**` — owner: `pm-infra`
- `features/infra-*/**` — owner: `pm-infra`
- `features/**` — owner: owning PM seat for that website/module (as delegated by CEO)
- `knowledgebase/**` — curator: `ceo-copilot`; contributors: any role may add lessons/proposals/reviews

### Inbox/outbox/audit trails
- `sessions/<seat-id>/**` — owner: `<seat-id>` (others read-only unless explicitly delegated)
- `inbox/**` — owner: `ceo-copilot` (command intake and processing)
- `tmp/**` — owner: `ceo-copilot` (operational state)

## Target repo module ownership (reference)
Module ownership for Drupal work is defined in `org-chart/ownership/module-ownership.yaml`.

