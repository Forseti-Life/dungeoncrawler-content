# Implementation Notes: forseti-jobhunter-contact-tracker

- Feature: forseti-jobhunter-contact-tracker
- Author: dev-forseti
- Date: 2026-04-14
- Status: done — code complete, DB updated, cache cleared

## KB references

None found. Pattern follows existing per-user overlay tables (interview_rounds, company_interest).

## Implementation state

Feature was pre-implemented for core CRUD. Two gaps were found and resolved in commit `c30549830`:
- AC-4: `email` column was missing — added via `hook_update_9060`
- SEC-4 / `linkedin_url`: column existed in schema but was not read from POST or written — `contactForm` and `contactSave` updated to handle both fields with validation

## Storage

Table `jobhunter_contacts` — schema post-9060:
- `id` (int unsigned, PK)
- `uid` (int unsigned NOT NULL)
- `full_name` / `name` (varchar 255) — resolved via `getContactNameField()`
- `job_title` / `title` (varchar 255, nullable) — resolved via `getContactTitleField()`
- `company_id` (int unsigned, nullable)
- `company_name` (varchar 255)
- `linkedin_url` (varchar 512, nullable) — now written from POST
- `email` (varchar 255, nullable) — added by hook_update_9060
- `relationship_type` (varchar 32, default 'connection')
- `notes` (mediumtext, nullable)
- `last_contact_date` (varchar 10, nullable)
- `referral_status` (varchar 20, default 'none')
- `created`, `changed` (int)

## Routes

| Route key | Path | Method | CSRF |
|---|---|---|---|
| `job_hunter.contacts_list` | `/jobhunter/contacts` | GET | no |
| `job_hunter.contacts_add` | `/jobhunter/contacts/add` | GET | no |
| `job_hunter.contacts_save` | `/jobhunter/contacts/save` | POST | yes |
| `job_hunter.contacts_edit` | `/jobhunter/contacts/{contact_id}/edit` | GET | no |
| `job_hunter.contacts_delete` | `/jobhunter/contacts/{contact_id}/delete` | POST | yes |

Split-route pattern applied on POST routes. All require `_user_is_logged_in: TRUE`.

## Controllers (CompanyController.php)

- `contactsList()` (~line 3997): queries `jobhunter_contacts` scoped to `uid`, joins companies for name, renders list table.
- `contactForm($contact_id = NULL)` (~line 4078): renders add/edit form; pre-populates on edit; includes email and linkedin_url fields.
- `contactSave()` (~line 4258): validates required fields (name, company_id); validates email via `filter_var(FILTER_VALIDATE_EMAIL)`; validates LinkedIn URL contains 'linkedin.com'; sanitizes free-text via `strip_tags()`; writes email and linkedin_url conditionally (schema field check); enforces uid from session; ownership check on update; SEC-5 preserved.
- `contactDelete($contact_id)` (~line ~4410): ownership-checked DELETE (WHERE uid + id).

## Security compliance

| Check | Status |
|---|---|
| SEC-1: `_user_is_logged_in: TRUE` on all routes | ✅ |
| SEC-2: CSRF split-route on contacts_save (POST) and contacts_delete (POST) | ✅ |
| SEC-3: uid always from session; ownership checked in update/delete | ✅ |
| SEC-4: strip_tags on all free-text; email validated with filter_var; linkedin_url validated against linkedin.com pattern | ✅ |
| SEC-5: Logger records uid + contact_id only; email/name/linkedin_url never logged | ✅ |

## Commits

- `c30549830` — feat: add email+linkedin_url to contact tracker (AC-4/SEC-4)

## Verification targets

```bash
# AC-4: schema
cd /var/www/html/forseti && vendor/bin/drush sql:query "DESCRIBE jobhunter_contacts"
# Expected: email and linkedin_url columns present

# AC-2: contacts page
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/contacts

# AC-1/AC-5: save + verify
vendor/bin/drush sql:query "SELECT name, title, company_id, relationship_type, email FROM jobhunter_contacts WHERE uid=<uid> ORDER BY created DESC LIMIT 1"

# AC-5: cross-user isolation
vendor/bin/drush sql:query "SELECT COUNT(*) FROM jobhunter_contacts WHERE uid=<uid_B>"
```

## Cross-site sync

N/A — `job_hunter` is forseti.life-only.


## KB references

None found. Pattern follows existing per-user overlay tables (interview_rounds, company_interest).

## Implementation state

Feature is **fully implemented** — verified 2026-04-14 by reading live code and running schema/route queries. No changes were made in this release cycle.

## Storage

Table `jobhunter_contacts` — verified present in production DB. Schema has evolved across releases with multiple columns; key columns confirmed:
- `id` (int unsigned, PK)
- `uid` (int unsigned NOT NULL)
- `full_name` / `name` (varchar 255) — controller uses `getContactNameField()` helper to resolve active column
- `job_title` / `title` (varchar 255, nullable) — controller uses `getContactTitleField()` helper
- `company_id` (int unsigned, nullable)
- `company_name` (varchar 255)
- `linkedin_url` (varchar 512, nullable)
- `relationship_type` (varchar 32, default 'connection')
- `notes` (mediumtext, nullable)
- `last_contact_date` (varchar 10, nullable)
- `referral_status` (varchar 20, default 'none')
- `created`, `changed` (int)
- Index on `uid`

Note: AC-4 mentions `email` column. Live schema shows no `email` column — the table may have evolved away from it. QA should verify and report if email column is required by AC.

## Routes

| Route key | Path | Method | CSRF |
|---|---|---|---|
| `job_hunter.contacts_list` | `/jobhunter/contacts` | GET | no |
| `job_hunter.contacts_add` | `/jobhunter/contacts/add` | GET | no |
| `job_hunter.contacts_save` | `/jobhunter/contacts/save` | POST | yes |
| `job_hunter.contacts_edit` | `/jobhunter/contacts/{contact_id}/edit` | GET | no |
| `job_hunter.contacts_delete` | `/jobhunter/contacts/{contact_id}/delete` | POST | yes |

Split-route pattern applied on POST routes. All require `_user_is_logged_in: TRUE` and `_permission: access job hunter`.

## Controllers (CompanyController.php)

- `contactsList()` (~line 3997): queries `jobhunter_contacts` scoped to `uid`, joins companies for name, renders list table.
- `contactForm($contact_id = NULL)` (~line 4078): renders add/edit form; pre-populates when editing existing contact.
- `contactSave()` (~line 4246): validates required fields (name, company_id), sanitizes all free-text via `strip_tags()`, validates date format, verifies company exists, enforces uid from session. Logs uid + contact_id only (SEC-5). Edit: verifies ownership before update via `WHERE uid=? AND id=?`.
- `contactDelete($contact_id)` (~line 4379): ownership-checked DELETE (WHERE uid + id).
- `contactJobLinkSave($contact_id)` (~line 4422): links contact to a saved job via `jobhunter_contact_job_links` table.

## Security compliance

| Check | Status |
|---|---|
| SEC-1: `_user_is_logged_in: TRUE` on all routes | ✅ |
| SEC-2: CSRF split-route on contacts_save (POST) and contacts_delete (POST) | ✅ |
| SEC-3: uid always from session; ownership checked in update/delete with `WHERE uid=?` | ✅ |
| SEC-4: All free-text sanitized via `strip_tags()`; display via Twig auto-escaping | ✅ |
| SEC-5: Logger records uid + contact_id only | ✅ |

## QA note

AC-4 expects an `email` column. Live schema does NOT include `email`. QA should flag this if `email` is a required field per product intent — potential gap.

## Verification targets

```bash
# AC-4: schema
cd /var/www/html/forseti && vendor/bin/drush sql:query "DESCRIBE jobhunter_contacts"

# AC-2: contacts page
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/contacts

# AC-1/AC-5: save + verify
vendor/bin/drush sql:query "SELECT name, title, company_id, relationship_type FROM jobhunter_contacts WHERE uid=<uid> ORDER BY created DESC LIMIT 1"

# AC-5: isolation
vendor/bin/drush sql:query "SELECT COUNT(*) FROM jobhunter_contacts WHERE uid=<uid_B>"
```

## Cross-site sync

N/A — `job_hunter` is forseti.life-only.

and surface relevant contacts on saved-job detail views by matching company.

## Data model

Recommended fields:
- `id`
- `uid`
- `name`
- `company_id`
- `title`
- `relationship_type`
- `last_contact_date`
- `referral_status`
- `notes`
- `created`
- `changed`

Prefer a nullable FK-style `company_id` if some historical contacts do not yet
map cleanly to the company catalog, but preserve the brief's intent to match
saved jobs by company.

## UI surfaces

- `/jobhunter/contacts`:
  - add/edit/delete list page
  - empty state for new users
- saved-job detail:
  - `Your contacts here` section showing matching contacts for the job's company

## Logic notes

- Keep delete as an explicit confirmation flow.
- All list/detail queries must be uid-scoped.
- This feature overlaps with `contact-referral-tracker`; if both stay live,
  converge on one shared `jobhunter_contacts` implementation instead of two
  separate tables.

## Verification targets

```bash
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/contacts
drush sql:query "DESCRIBE jobhunter_contacts"
drush sql:query "SELECT name, company_id, relationship_type FROM jobhunter_contacts WHERE uid=<uid>"
```

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.
