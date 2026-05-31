# Implementation Notes: forseti-jobhunter-contact-referral-tracker

- Feature: forseti-jobhunter-contact-referral-tracker
- Author: dev-forseti
- Date: 2026-04-12
- Status: implemented — commits cb9c5e51b, f9c9b2731 (release-k SEC-4 fix)

## Approach

Migrated the existing `jobhunter_contacts` table (release-d schema) to the
release-e schema via Drupal update hooks. Extended `CompanyController` with all
new/updated methods. No separate overlay table created — this supersedes the
release-d contact-tracker schema.

## Actual implementation

### Schema changes (hooks 9055 + 9056 — run 2026-04-12, verified)

- `job_hunter_update_9055`: rename `name`→`full_name`, `title`→`job_title`;
  drop `email` and `company_id` columns; add `company_name varchar(255)`;
  widen `referral_status` to varchar(20); normalize old values to `none`.
- `job_hunter_update_9056`: create `jobhunter_contact_job_links`
  (id, uid, contact_id, saved_job_id, created) with unique key
  `(uid, contact_id, saved_job_id)`.
- Fresh-install hook `job_hunter_update_9050` also updated to match.

### Routes added

- `job_hunter.contact_job_link_save` (POST `/jobhunter/contacts/{contact_id}/link-job`, CSRF split-route)

### Controller changes (`CompanyController.php`)

- `CONTACT_RELATIONSHIP_TYPES`: colleague/recruiter/friend/alumni/other
- `CONTACT_REFERRAL_STATUSES`: none/referred/pending-referral
- `contactsList()`: queries new column names; shows referral badge; adds Last Contact Date column
- `contactForm()`: uses full_name/job_title/company_name; removes email/company_id; adds linked-jobs section in edit mode (loads `jobhunter_contact_job_links` + saved jobs dropdown)
- `contactSave()`: reads new field names; validates company_name as required; redirects to edit page after save so job-link section is immediately accessible
- `contactJobLinkSave()` (new): SEC-3 ownership checks on both contact and saved job; upsert (insert only if not already linked)
- `viewJob()` contacts section: replaced company_id FK query with `jobhunter_contact_job_links` lookup for saved_job_id + company_name text-match fallback; shows Referred/Pending Referral badges inline

## KB references

- None found in knowledgebase/ directly applicable to this feature.

## Security checklist

- [x] All routes require `_user_is_logged_in: 'TRUE'` (inherited from routing)
- [x] POST routes use CSRF split-route pattern (SEC-2)
- [x] All DB reads/writes scoped to `currentUser()->id()` — no uid from request (SEC-3)
- [x] LinkedIn URL validated: scheme must be http/https (422 on javascript:/data:); must contain `linkedin.com` (SEC-4) — **fixed release-k: added scheme check returning 422**
- [x] No PII logged — only uid + contact id / saved-job id in watchdog (SEC-5)
- [x] Schema migrations preserve existing data, normalize old values

## Verification commands

```bash
# Schema
drush sql:query "DESCRIBE jobhunter_contacts"
drush sql:query "DESCRIBE jobhunter_contact_job_links"

# Functional
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/contacts | grep -q 'contact'
drush sql:query "SELECT full_name, company_name, referral_status FROM jobhunter_contacts LIMIT 5"
```

## Rollback

Revert commit cb9c5e51b and run a manual `ALTER TABLE` to restore old column names if needed. Update hooks are not automatically reversible.

## Cross-feature note

This feature supersedes `forseti-jobhunter-contact-tracker` (release-d) for schema and UI. The release-d contact-tracker code paths are now fully replaced by this implementation.

## Cross-site sync

- N/A — `job_hunter` is forseti.life-only.

