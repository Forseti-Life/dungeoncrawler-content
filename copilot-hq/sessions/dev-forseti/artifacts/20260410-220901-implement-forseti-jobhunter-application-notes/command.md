- Status: done
- Completed: 2026-04-10T23:10:44Z

# Implement: forseti-jobhunter-application-notes

- Agent: dev-forseti
- Release: 20260410-forseti-release-f
- Feature: forseti-jobhunter-application-notes
- Module: job_hunter
- Date: 2026-04-10

## Task

Implement `forseti-jobhunter-application-notes` per the acceptance criteria in `features/forseti-jobhunter-application-notes/01-acceptance-criteria.md`.

## Summary

Add a per-saved-job contact log and notes field. New table `jobhunter_application_notes` keyed by `(uid, saved_job_id)`. The saved-job detail view exposes an inline form (AJAX POST, CSRF-protected) for: hiring manager name, contact email, last contact date, and freetext notes. Revisiting the page pre-populates the form.

## Acceptance criteria (key items)

- **AC-1**: Notes form renders on saved-job detail view (`/jobhunter/saved-jobs/{job_id}` and `/jobhunter/my-jobs`) with 4 fields.
- **AC-2**: Form pre-populates on revisit.
- **AC-3**: AJAX save creates/updates row in `jobhunter_application_notes`; shows success flash without full reload.
- **AC-4**: Email field validated to RFC-5321 format; rejects invalid email.
- **AC-5**: Notes max 2000 chars enforced server-side; longer input triggers 400 + error message.
- **AC-6**: No cross-user notes leak.
- **Full AC**: `features/forseti-jobhunter-application-notes/01-acceptance-criteria.md`

## Verification

```bash
# Check form renders
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/my-jobs | grep -q 'application-notes'

# Check DB row after save
drush sql:query "SELECT * FROM jobhunter_application_notes WHERE uid=<uid> LIMIT 3"
```

## Scope

- Site: forseti.life
- Repo root: /home/ubuntu/forseti.life
- Module dir: sites/forseti/web/modules/custom/job_hunter/
- Note: distinct from `jobhunter_interview_notes` (that is release-g scope — do NOT implement here).

## Done when

All AC items pass; code committed with verification evidence in outbox.
