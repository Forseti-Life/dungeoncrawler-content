Now I have all the context I need. Let me implement the feature:

Now I have full context. Let me implement all 7 changes:

Now add the 3 controller methods to `CompanyController.php`. I'll add them before the `addForm` method (after `coverLetterSave`):

Now add the theme hook in `job_hunter.module`:

Now create the Twig template:

Now update `ApplicationSubmissionController.php` — add `interview` to enum, labels, and `deriveWorkflowStatus`:

Now update `deriveWorkflowStatus` to handle `interview_scheduled`/`interview_completed`:

Now update `my-jobs.html.twig` — add `interview` filter option and "Prepare for Interview" link:

Now add "Prepare for Interview" link for `interview` stage cards in the My Jobs operations column:

Now add the DB schema update hook:

Now verify syntax on all modified PHP files, run `drush updb` and `drush cr`:

All verifications pass. Now commit:

All done. Here is the outbox update:

---

- Status: done
- Summary: Implemented `forseti-jobhunter-interview-prep` (release-g). Added 3 routes (GET `/jobhunter/interview-prep/{job_id}`, POST save with CSRF, POST AI tips with CSRF/AJAX), 3 controller methods in `CompanyController`, `interview_prep_page` theme hook in `job_hunter.module`, and `interview-prep-page.html.twig` with a 5-item static checklist, notes textarea (10,000-char cap + strip_tags on save), and AJAX AI tips button calling `ai_conversation.ai_api_service`. Added `interview` to `WORKFLOW_STATUS_ENUM` and `PIPELINE_STAGE_LABELS`; `deriveWorkflowStatus` now maps `interview_scheduled`/`interview_completed` app statuses → `interview` workflow stage. `my-jobs.html.twig` gets an "Interview" filter option and "Prepare for Interview" link for interview-stage cards. `job_hunter_update_9042` creates the `jobhunter_interview_notes` table. Commit `a7d7accc8`. Verified: all PHP syntax clean, `drush updb` ran successfully (table created), `drush cr` clean, anonymous → 403, non-integer job_id → 404, both POST routes have `_csrf_token: 'TRUE'`.

## Next actions
- QA-forseti to run AC-1 through AC-8 verification
- AI tips (AC-5) requires a live user session with a real job to test end-to-end; QA should verify error path with a mocked/failing AI service too

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 18
- Rationale: Completes the interview stage UX — users moving from `pending_response` to `interview_scheduled` previously had no in-app tooling; this closes the workflow gap between application and offer stages.

---
- Agent: dev-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-forseti/inbox/20260409-impl-forseti-jobhunter-interview-prep
- Generated: 2026-04-09T14:57:58+00:00
