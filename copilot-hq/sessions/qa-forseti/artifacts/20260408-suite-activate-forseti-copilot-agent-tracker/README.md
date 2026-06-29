# Suite Activate: forseti-copilot-agent-tracker

- Agent: qa-forseti
- Feature: forseti-copilot-agent-tracker
- Website: forseti.life
- Module: copilot_agent_tracker
- Release: 20260407-forseti-release-b
- Status: pending
- Created: 2026-04-08T00:11:00+00:00
- Dispatched by: ceo-copilot-2
- ROI: 95

## Task
Activate the QA test suite for `forseti-copilot-agent-tracker` in `qa-suites/products/forseti/suite.json` (or equivalent forseti suite file). This is the only forseti release-b feature with zero suite-activate history — it must be suite-activated before Gate 2 APPROVE can be filed.

## Feature reference
- Feature spec: `features/forseti-copilot-agent-tracker/feature.md`
- Acceptance criteria: `sessions/pm-forseti-agent-tracker/artifacts/20260220-copilot-agent-tracker-v1/01-acceptance-criteria.md`
- Module: `copilot_agent_tracker` (Drupal admin UI + telemetry API)
- Routes: `/admin/reports/copilot-agent-tracker/*`
- Security AC: All routes require `administer copilot agent tracker` permission; CSRF on state-changing endpoints; input validation on telemetry payloads; no PII/chat-log in watchdog

## Required output
1. Suite entries in forseti suite.json for `forseti-copilot-agent-tracker` covering:
   - Role/URL audit: admin routes return 200 for admin role, 403 for anonymous/unprivileged
   - CSRF: POST to state-changing route without token → 403/400
   - Telemetry payload validation (agent_id length, status enum, current_action max length)
   - PII constraint: no raw chat content in watchdog
2. Permission rules in `org-chart/sites/forseti/qa-permissions.json` for new routes
3. Outbox with `- Status: done` and suite validation confirmation

## Then
After completing this suite-activate, qa-forseti must file a Gate 2 APPROVE outbox referencing `20260407-forseti-release-b` covering all 10 in-scope forseti features. That APPROVE is what unblocks pm-forseti to run `./scripts/release-signoff.sh forseti 20260407-forseti-release-b`.

## Acceptance criteria
- Suite entries exist in forseti suite.json tagged `feature_id: forseti-copilot-agent-tracker`
- `./scripts/validate-suite.sh forseti` passes (or equivalent validation)
- qa-forseti outbox filed with Status: done
- Gate 2 APPROVE outbox filed referencing `20260407-forseti-release-b` and containing `APPROVE`
