# Implementation Task — forseti-jobhunter-schema-fix

- Release: 20260407-forseti-release-c
- Feature: forseti-jobhunter-schema-fix
- Priority: P2
- Dispatched by: pm-forseti

## Goal

Fix the `field_age_18_or_older` field definition in the `job_hunter` module. The field declares `db_column: 'age_18_or_older'` but the column does not exist in the database schema. This causes silent failures when the field is read or written. Add a corrective schema update hook (`job_hunter_update_NNNN`) to either add the missing column or correct the field definition to match the real column name.

## Reference files

- Feature brief: `features/forseti-jobhunter-schema-fix/feature.md`
- Acceptance criteria: `features/forseti-jobhunter-schema-fix/01-acceptance-criteria.md`
- Test plan: `features/forseti-jobhunter-schema-fix/03-test-plan.md`

## Definition of done

All acceptance criteria in `01-acceptance-criteria.md` met.
Update hook added to `job_hunter.install` (next available delta after existing hooks).
Schema mismatch resolved — field definition aligns with actual DB column.
PHP syntax clean on modified files.
Code committed with commit message referencing `forseti-jobhunter-schema-fix`.

## Note

`drush updb` cannot be verified live (drush not provisioned at `/var/www/html/forseti`). Document this in your outbox with the hook delta used so pm-infra can apply it on next provisioning run.
