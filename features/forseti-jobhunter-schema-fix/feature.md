# Feature Brief

- Work item id: forseti-jobhunter-schema-fix
- Website: forseti.life
- Module: job_hunter
- Project: PROJ-004
- Group Order: 6
- Group: infrastructure
- Group Title: Technical Infrastructure
- Group Sort: 5
- Status: shipped
- Release: 20260408-forseti-release-c
- Priority: P2
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Source: BA inventory JH-R5 (ROI 6)

## Goal

Fix the `field_age_18_or_older` field definition in the `job_hunter` module which declares `db_column: 'age_18_or_older'` but the column does not exist in the database schema. This mismatch causes silent failures when the field is read or written. The fix is to align the field definition with the actual schema (either add the missing column via an update hook or correct the field definition to match the real column name).

## Non-goals

- Changing the business logic around age verification.
- Renaming the field for branding reasons.

## Gap Analysis

### Implementation status

| Requirement | Existing code path | Coverage status |
|---|---|---|
| `field_age_18_or_older` db_column matches actual DB column | `job_hunter.module` or install schema | None (mismatch exists) |
| Update hook or schema correction deployed | `job_hunter.install` | None |
| Field reads/writes confirmed working | N/A | None (no tests) |

### Coverage determination

- **Feature type: enhancement** — Field definition exists but is broken. Dev must add a corrective schema update hook or fix the db_column mapping.

### Test path guidance for QA

- Confirm field is readable and writable after fix via functional test.
- Verify no data loss for existing rows.

## Risks

- Low: isolated field fix. No dependencies on other modules.

## Latest updates

- 2026-04-08: Scoped into release — suite activation sent to QA.

- 2026-04-08: Scoped into release — suite activation sent to QA.

- 2026-04-07: Scoped into release — suite activation sent to QA.

- 2026-04-06: Grooming complete — test plan written by QA. Ready for next Stage 0 scope selection.

- 2026-04-05: Handed off to QA for test generation (pm-qa-handoff.sh)

- 2026-04-05: Feature brief created from BA inventory JH-R5; scoped for 20260405-forseti-release-b.

## Security acceptance criteria
- Authentication/permission surface: Field definition fix only — no new routes or permissions. Existing `access job hunter` permission gates all jobhunter field access.
- CSRF expectations: No new POST routes added. No CSRF impact.
- Input validation: Schema fix aligns db_column name; no new user input surface introduced. Existing field validation via Drupal Field API unchanged.
- PII/logging constraints: `field_age_18_or_older` is a boolean field. No PII concern. Update hook must not log existing field values.
- 2026-04-08: Deferred from 20260407-forseti-release-b — no dev implementation or Gate 2 APPROVE before release close.
