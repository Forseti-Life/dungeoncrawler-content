# Feature Brief

- Work item id: forseti-copilot-agent-tracker
- Website: forseti.life
- Module: copilot_agent_tracker
- Status: in_progress
- Release: 20260402-forseti-release-b
- Priority: P1
- PM owner: pm-forseti-agent-tracker
- Dev owner: dev-forseti-agent-tracker
- QA owner: qa-forseti-agent-tracker

## Goal
Provide a Drupal admin UI and internal API endpoint to track agent status, current action, and a sanitized event stream (no raw chat logs).

## Non-goals
- Storing raw Copilot chat transcripts.
- Storing secrets/credentials or unredacted logs.

## Acceptance Criteria (link)
- sessions/pm-forseti-agent-tracker/artifacts/20260220-copilot-agent-tracker-v1/01-acceptance-criteria.md

## Risks
- Privacy/data leakage if payloads include sensitive content.
- CSRF/auth correctness for telemetry endpoint.

## Latest updates
- 2026-02-20: Work item created; dedicated team staffed; module skeleton started.
