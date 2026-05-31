# Feature Brief: Add a general statement on the Dungeoncrawler home page informing players that user suggestions are being implemented

- Work item id: dc-home-suggestion-notice
- Website: dungeoncrawler.life
- Module: dungeoncrawler_content (home page / front page block)
- Status: done
- Release: 
- Priority: P2
- PM owner: pm-dungeoncrawler
- Dev owner: dev-dungeoncrawler
- QA owner: qa-dungeoncrawler
- Source: community_suggestion NID 2 (Talk to Forseti intake)
- Category: Technical Improvement
- Created: 2026-04-05

## Goal

Add a short, visible statement on the Dungeoncrawler home page informing players that community suggestions are actively being received and implemented. This closes the feedback loop so players know their input has value.

## Non-goals

- Does not require listing specific suggestions or a full changelog.
- Does not require a dynamic feed or automated sync from Drupal suggestion nodes.
- Does not touch game mechanics or character-related features.

## Acceptance Criteria

See `features/dc-home-suggestion-notice/01-acceptance-criteria.md`.

## Implementation hint

Add a Drupal block or field on the front page node containing a short static message (e.g., "We're actively implementing your suggestions — keep them coming!"). Configure block visibility to front page only. No dynamic content required; a hardcoded text field or site-wide announcement block is sufficient. Can be toggled on/off via admin without a code deploy using Drupal block visibility settings.

## Mission alignment

This feature directly advances community trust and participation:
> "Democratize and decentralize internet services by building community-managed versions of core systems
> for scientific, technology-focused, and tolerant people."

A visible acknowledgment that suggestions are being acted on encourages continued community involvement, which is core to the mission.

## Original User Message

> I have a suggestion, add a message on the home page that you are implementing suggestions.

## Risks

- Low effort, low risk. Static content addition only.
- No impact on game mechanics or data model.

## Security & Release Integrity Gate

- Board security review required: no
- Board approval artifact: n/a
- Intake risk signals: none

## Latest updates

- 2026-04-07: Scoped into release — suite activation sent to QA.

- 2026-04-07: Scoped into release — suite activation sent to QA.

- 2026-04-05: Scoped into release — suite activation sent to QA.

- 2026-04-05: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-05: Handed off to QA for test generation (pm-qa-handoff.sh)

- 2026-04-05: Created from community_suggestion NID 2 via suggestion-triage.sh (manual recovery — script bug in RISK_SIGNALS heredoc interpolation; Drupal node status updated to in_progress correctly)

## Security acceptance criteria
- Authentication/permission surface: display-only UI notice on home page; admin-only update route requires `_admin_access: TRUE`
- CSRF expectations: all POST/PATCH notice update routes require `_csrf_request_header_mode: TRUE`
- Input validation: notice text sanitized at Drupal field layer; no raw HTML accepted
- PII/logging constraints: no PII logged; notice renders static/config-driven content only
