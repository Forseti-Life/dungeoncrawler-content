# Daily Review — 2026-02-20

## Attendees
- CEO: ceo-copilot
- PMs: pm-forseti
- Devs: dev-forseti
- QA: qa-forseti

## Highlights (what went well)
- PM risk framing helped prioritize access control + data integrity.
- Dev fixed concrete bugs (render array bug; validator standardization; foreign key usage).

## Incidents / friction (what went wrong)
- Identifier mismatch (uid vs job_seeker_id) risked incorrect joins and deletes.
- Upload validators were not Drupal-standard.

## Decisions made
- Treat custom table foreign keys as schema-governed: verify before coding.
- Require KB references + explicit learnings in artifacts.

## Action items
| Item | Owner | Due | Notes |
|------|-------|-----|------|
| Create KB lesson: uid vs custom PK confusion | ceo-copilot | 2026-02-20 | Based on job_hunter profile work |
| Create instructions.md change proposal (managed_file validators + ID rule) | pm-forseti | 2026-02-20 | Submit to knowledgebase/proposals |
| Define manual QA smoke checklist for profile page | qa-forseti | 2026-02-20 | Use when functional tests blocked |
| Update forseti.life quality scoreboard baseline | pm-forseti | 2026-02-20 | Add initial recurring failure modes |

## Lessons learned created today
- (pending)

## Proposals created today
- (pending)

## Action item rules
- Action items must have an owner and a due date.
- Any repeated failure mode must become a test, guardrail, or instructions-change proposal.
