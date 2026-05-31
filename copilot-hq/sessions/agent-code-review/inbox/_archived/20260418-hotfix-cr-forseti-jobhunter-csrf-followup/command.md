# Hotfix code review — forseti job_hunter CSRF follow-up

Review the direct CEO follow-up patch applied after the first hotfix review for Forseti `job_hunter`.

## Files changed
- `sites/forseti/web/modules/custom/job_hunter/src/Controller/CompanyController.php`
- `sites/forseti/web/modules/custom/job_hunter/templates/interview-prep-page.html.twig`
- `sites/forseti/web/modules/custom/job_hunter/job_hunter.install`

## Change summary
- Restored the interview prep AI tips CSRF flow by folding the route token into `#ai_tips_url` and removing the template-side token concatenation that was using undefined `ai_tips_csrf_token`.
- Added the missing `email` column to `_job_hunter_create_contacts_table()` for fresh-install schema parity.

## Reason for bypassing dev inbox flow
- These are direct CEO follow-up fixes to issues discovered by the first hotfix code review of commit `848edf2c1`.

## Required review focus
- Interview prep AI tips CSRF path/delivery correctness
- Fresh-install contacts schema parity for `email`
- Any collateral regression in the Twig/template handoff
