- Status: done
- Completed: 2026-04-10T23:24:36Z

# Implement: forseti-jobhunter-tailoring-feedback

- Agent: dev-forseti
- Release: 20260410-forseti-release-f
- Feature: forseti-jobhunter-tailoring-feedback
- Module: job_hunter
- Date: 2026-04-10

## Task

Implement `forseti-jobhunter-tailoring-feedback` per the acceptance criteria in `features/forseti-jobhunter-tailoring-feedback/01-acceptance-criteria.md`.

## Summary

Add a thumbs-up/thumbs-down rating widget (plus optional freetext note, max 500 chars) to the tailoring result display. Ratings stored in new table `jobhunter_tailoring_feedback` keyed by `(uid, tailored_resume_id)`. Submission via AJAX with CSRF protection. Page updates widget state in-place after save — no full reload.

## Acceptance criteria (key items)

- **AC-1**: 👍/👎 widget + optional notes textarea (max 500 chars) visible on tailoring result page.
- **AC-2**: Widget pre-selects prior rating and notes on revisit.
- **AC-3**: AJAX POST creates/updates row in `jobhunter_tailoring_feedback` with `rating`, `note`, `changed`.
- **AC-4**: No cross-user feedback leak.
- **AC-5**: Notes max 500 chars enforced server-side.
- **AC-6**: UI updates in-place (no full reload) after successful save.
- **Full AC**: `features/forseti-jobhunter-tailoring-feedback/01-acceptance-criteria.md`

## Verification

```bash
# Widget renders
curl -s -b "$FORSETI_COOKIE_AUTHENTICATED" https://forseti.life/jobhunter/tailor-resume | grep -q 'tailoring-feedback'

# DB row after submit
drush sql:query "SELECT * FROM jobhunter_tailoring_feedback WHERE uid=<uid> LIMIT 3"
```

## CSRF note

Use split-route pattern: GET route (no CSRF) + POST route (with `_csrf_token: 'TRUE'`). Do NOT apply CSRF to GET routes — will cause 403 regressions.

## Scope

- Site: forseti.life
- Repo root: /home/ubuntu/forseti.life
- Module dir: sites/forseti/web/modules/custom/job_hunter/

## Done when

All AC items pass; code committed with verification evidence in outbox.
- Status: pending
