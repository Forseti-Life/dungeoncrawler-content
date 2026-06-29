# Feature Brief

- Work item id: forseti-copilot-agent-tracker
- Website: forseti.life
- Module: copilot_agent_tracker
- Status: in_progress
- Release: 20260405-forseti-release-c
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

## Security acceptance criteria

- **Authentication/permission surface**: All routes under `/admin/reports/copilot-agent-tracker/*` require `administer copilot agent tracker` permission (Drupal role-based access). No anonymous or authenticated-but-unprivileged access permitted. Verify via: `drush php-eval` role check or curl returning 403 for unauthenticated requests.
- **CSRF protection**: All state-changing endpoints (telemetry publish, status update) must use Drupal's CSRF token validation (`\Drupal::csrfToken()->validate()`). GET-only reads are exempt. New form submissions must use `#token` in the Form API. Verify: attempt POST to state-changing route without CSRF token and confirm 403/400 response.
- **Input validation**: All incoming telemetry payloads validated server-side — agent_id max 64 chars alphanumeric/dash/underscore, status enum-validated, current_action max 512 chars, metadata JSON object only. Reject/log malformed input. No client-supplied values written to DB without sanitization. Verify: submit out-of-bounds payload and confirm rejection.
- **PII/logging constraints**: Must NOT log raw Copilot chat content, agent session tokens, or any user-identifying telemetry beyond agent_id. Drupal watchdog entries must not include payload body. Verify: review watchdog table after a publish cycle; confirm no chat transcript or credentials present.

## Latest updates
- 2026-02-20: Work item created; dedicated team staffed; module skeleton started.
- 2026-04-05: Security acceptance criteria section added (pm-forseti-agent-tracker, gap fix from sec-analyst outbox 20260227).
- 2026-04-05: Release ID updated to 20260405-forseti-release-c (post-release gap review).
