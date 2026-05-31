# Hotfix code review — forseti job_hunter CSRF + install pairing

Review the direct CEO hotfix applied to Forseti `job_hunter` after coordinated release `20260412-forseti-release-m`.

## Files changed
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.install`

## Change summary
- Repaired CSRF token seeds for job_hunter POST routes that were using route names or other incorrect values instead of rendered route paths:
  - `company_interest_save`
  - `company_research_save`
  - `contacts_save`
  - `contacts_delete`
  - `contact_job_link_save`
- Moved `resume_source_save` token delivery from `X-CSRF-Token` header to the `?token=` query parameter expected by Drupal `_csrf_token` route access.
- Added fresh-install coverage in `job_hunter_install()` for:
  - `jobhunter_company_interest`
  - `jobhunter_company_research`
  - `jobhunter_contacts`
  - `jobhunter_offers`
- Extracted helper creators for contacts/offers so install + update paths share the same table creation logic.

## Reason for bypassing dev inbox flow
- This was a live post-release hotfix after coordinated deploy/post-push review surfaced 6 HIGH CSRF defects and 1 MEDIUM fresh-install schema gap in the shipped code.

## Required review focus
- CSRF route-path seed correctness
- CSRF delivery channel correctness for JS fetch POSTs
- Any new regression introduced by the install helper extraction
- Fresh-install schema parity
