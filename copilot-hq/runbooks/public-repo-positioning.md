# Public Repo Positioning

This document defines the value proposition, purpose, and relationship between this public repository and `forseti.life`.

## Purpose
`copilot-sessions-hq` is the open operations framework behind Forseti's agent coordination model. It documents how multi-agent work is structured, governed, monitored, and improved over time.

The public repo exists to provide:
- A reproducible operating model for role-based AI agent orchestration.
- Transparent process artifacts (runbooks, org model, control scripts).
- A practical reference implementation that teams can adapt.

## Value proposition
For operators, engineering teams, and AI platform builders, this repository provides:

1) Operational clarity
- Explicit org structure, responsibilities, and handoff rules.
- Clear command intake, escalation, and conflict-resolution paths.

2) Reliability by design
- Deterministic control-plane behavior (enable/disable state, converge, watchdog).
- Standardized loops for intake, health, QA follow-through, and checkpointing.

3) Auditable process quality
- Written runbooks for repeated workflows and shipping gates.
- Scoreboard/knowledge structures that support continuous improvement.

4) Portability
- Scripted workflows that can run in a local-first environment.
- Public-safe mirror workflow so teams can separate private operations from public documentation/code.

## Relation to forseti.life
`forseti.life` is the product/site ecosystem where this operating model is applied in production workflows.

Relationship model:
- This repo (`copilot-sessions-hq`) is the operations control and governance layer.
- `forseti.life` repositories contain product/runtime implementation details.
- The HQ layer coordinates role-based agents across product workstreams (planning, execution, QA, release hygiene).

In practical terms, this repo answers:
- How decisions are routed.
- How work is delegated to agent seats.
- How release-cycle and QA guardrails are enforced.

And `forseti.life` repos answer:
- What product functionality is built.
- What user-facing behavior ships.

## Public scope boundaries
Public content should emphasize framework and method:
- Organizational model and governance runbooks.
- Automation/control scripts and process docs.
- Sanitized examples and templates.

Private-only content should remain outside the public mirror:
- Session-level operational payloads.
- Runtime logs/responses and environment-specific artifacts.
- Any sensitive integration details.

## Intended audiences
- Teams adopting multi-agent operating practices.
- Technical PMs and operators designing AI-assisted delivery processes.
- Contributors improving the orchestration framework itself.

## Non-goals for the public repo
- It is not a complete deployment package for all private infrastructure.
- It is not a source of raw operational telemetry/history.
- It does not require cron setup for readers evaluating the framework; runtime automation can be adopted later based on operator needs.

## How to use this positioning
- Use this file as the canonical explanation in external communications.
- Keep README concise; link here for deeper context.
- Update this document when the operating model or Forseti platform boundary changes.
