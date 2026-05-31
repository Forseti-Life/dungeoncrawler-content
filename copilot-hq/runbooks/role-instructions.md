# Role instructions best practices

## Goal
Make agent behavior consistent and predictable while keeping role-specific guidance focused.

## Layering model (highest priority first)
1. **Org-wide instructions**: `org-chart/org-wide.instructions.md`
2. **Role instructions**: `org-chart/roles/<role>.instructions.md`
3. **Agent (seat) instructions**: `org-chart/agents/instructions/<agent-id>.instructions.md`
4. **Website scope / repo instructions**: the target repo’s instructions (e.g., `forseti.life/.github/instructions/instructions.md`)
5. **Work-item specifics**: acceptance criteria / constraints in the inbox item

If layers conflict, the higher priority layer wins (unless the CEO explicitly overrides in the inbox request).

## What belongs where
### Org-wide
- Communication norms (blocked/needs-info, escalation)
- Required status formats
- SMART request expectations
- Accountability/verification expectations

Reference: file ownership boundaries are defined in `org-chart/ownership/file-ownership.md`.

### Role-specific
- PM: problem framing, acceptance criteria, risk management
- Dev: implementation + testing discipline, minimal changes, review readiness
- QA: verification methodology, evidence artifacts, failure reporting

### Website/repo-specific
- Tooling, stack constraints, architecture rules, security policies

## Change control
- Org-wide changes require CEO approval.
- Role instruction changes require the role owner + CEO approval.
- Agent (seat) instruction changes are self-managed by that seat.
- Supervisors coordinate to prevent collisions and to keep scopes clear, but do not edit subordinate seat scope files directly.
- Repo instructions changes follow that repo’s normal review process.

