- Status: done
- Completed: 2026-04-12T17:55:26Z

# Fix: contact-tracker schema — add missing columns

- Agent: dev-forseti
- Release: 20260412-forseti-release-d
- Feature: forseti-jobhunter-contact-tracker
- Priority: high (Gate 2 unblock)

## Context

QA BLOCK: `sessions/qa-forseti/outbox/20260412-unit-test-20260412-100923-impl-forseti-jobhunter-contact-tracker.md`

Two required columns are absent from the `jobhunter_contacts` DB schema:
- `last_contact_date` (date, nullable)
- `referral_status` (varchar 16: none/requested/pending/provided)

PM has updated AC-4 in `features/forseti-jobhunter-contact-tracker/feature.md` to:
- Accept `title` (instead of `role_title`) — no code change needed
- Accept updated `relationship_type` enum values — no code change needed
- **Require** `last_contact_date` and `referral_status` — must be added via update hook

## Required work

1. Add a new update hook (e.g., `job_hunter_update_9052`) in the `.install` file to:
   - `ALTER TABLE jobhunter_contacts ADD COLUMN last_contact_date DATE NULL DEFAULT NULL`
   - `ALTER TABLE jobhunter_contacts ADD COLUMN referral_status VARCHAR(16) NOT NULL DEFAULT 'none'`

2. Ensure the schema definition in `hook_schema()` (or `hook_install()`) also includes these two columns so fresh installs include them.

3. Update any controller/form code that references `role_title` to use `title` if not already done.

4. Verify enum constants in the controller match AC-4 updated values: `recruiter/referral/hiring_manager/connection`.

## Acceptance criteria (done when)
- `drush sql:query "DESCRIBE jobhunter_contacts"` shows `last_contact_date` and `referral_status` columns present
- `drush updb` runs cleanly with the new update hook
- No PHP errors or warnings in watchdog after cache clear
- Provide commit hash + rollback note in outbox

## Verification command
```bash
cd /home/ubuntu/forseti.life/sites/forseti
vendor/bin/drush updb -y
vendor/bin/drush sql:query "DESCRIBE jobhunter_contacts" | grep -E "last_contact_date|referral_status"
vendor/bin/drush cr
```

## KB reference
- `knowledgebase/` — check for prior lessons on Drupal update hooks
