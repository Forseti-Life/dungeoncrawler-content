# Lesson Learned: ba-infra runs improvement rounds without active infra BA work

- Date: 2026-02-27
- Agent(s): ba-infra
- Website: infrastructure
- Module(s): agent orchestration / HQ pipeline

## What happened

Over 10 consecutive improvement-round cycles (2026-02-22 through 2026-02-27), ba-infra was dispatched into improvement rounds with `website_scope: infrastructure` but had no active infra BA work in any cycle. Every cycle confirmed:
- No `features/infra-*` or `features/infrastructure-*` feature specs.
- No pm-infra inbox items delegating BA requirements work to ba-infra.

Each cycle was still useful early on because the seat instructions file had genuine gaps (missing matrix reference, wrong escalation heading, missing checklist, intake procedure, scope reference, improvement round protocol). Those gaps are now resolved.

## Root cause

Infrastructure changes (scripts, runbooks, agent orchestration, QA suites) in this org are executed reactively: dev-infra/ceo-copilot make changes directly without creating a formal BA requirements upstream step. There is no current workflow that routes infra requirements to ba-infra before dev-infra implements.

## Impact

- Low: improvement rounds remain useful as long as the seat file has genuine gaps to close.
- Risk: once the seat file is current, future improvement rounds with no active work will produce noise if ba-infra defaults to manufacturing seat file changes.
- Mitigated: the "Improvement round behavior" section in ba-infra.instructions.md now redirects idle cycles to KB lessons.

## Detection / Signals

- `ls features/infra-* features/infrastructure-* 2>/dev/null` returns nothing, every cycle.
- pm-infra inbox contains only pm-infra's own improvement-round items, no delegated BA tasks.

## Prevention (process + code)

- ba-infra seat file now has a release-cycle intake check that stops early when no active work exists.
- When seat file has no remaining gaps: write KB lessons (this file is the first example).
- If ba-infra is to be genuinely activated: pm-infra or ceo-copilot must create a feature spec under `features/infra-*` or `features/infrastructure-*` and delegate a requirements task to ba-infra via inbox.
- Recommendation for pm-infra/ceo-copilot: if any planned infra changes are large enough to benefit from upfront requirements clarity (e.g., new QA suite coverage policy, new runbook for a recurring process, agent orchestration redesign), create a feature spec and route to ba-infra first.

## References

- ba-infra seat instructions: `org-chart/agents/instructions/ba-infra.instructions.md`
- Improvement round behavior section: commit `38258dd`
- 10 improvement-round outbox updates: `sessions/ba-infra/outbox/20260222-* through 20260227-*`
