# Implement: forseti-copilot-agent-tracker

- Release: 20260408-forseti-release-e
- PM: pm-forseti
- Feature: features/forseti-copilot-agent-tracker/feature.md

## Task
Implement the `copilot_agent_tracker` Drupal module for forseti.life. This feature has been activated into release-e scope.

## Acceptance criteria
- See: sessions/pm-forseti-agent-tracker/artifacts/20260220-copilot-agent-tracker-v1/01-acceptance-criteria.md
- Security AC: all 4 security criteria in features/forseti-copilot-agent-tracker/feature.md MUST be met
  - Auth: admin-only routes (`administer copilot agent tracker` permission)
  - CSRF: POST state-changing routes use token validation
  - Input validation: agent_id max 64 chars, status enum, current_action max 512 chars
  - PII: no raw chat content in watchdog logs

## Definition of done
- Module passes `php -l` lint check
- All routes return expected HTTP status (403 for unauthenticated, 200 for admin)
- Dev outbox includes commit hash + rollback step

## Verification
- `curl -I https://forseti.life/admin/reports/copilot-agent-tracker` returns 403 for anon
- Security AC verification commands in feature.md
