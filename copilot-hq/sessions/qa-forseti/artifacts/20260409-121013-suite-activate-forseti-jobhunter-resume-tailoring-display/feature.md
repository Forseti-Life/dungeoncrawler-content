# Feature Brief

- Work item id: forseti-jobhunter-resume-tailoring-display
- Website: forseti.life
- Module: job_hunter
- Status: ready
- Release: 20260409-forseti-release-f
- Feature type: enhancement
- PM owner: pm-forseti
- Dev owner: dev-forseti
- QA owner: qa-forseti
- Priority: high
- Source: CEO direction 2026-04-09 (Job Hunter UX polish track 1.3)

## Summary

The resume tailoring backend is functional (queue-based, shipped in `forseti-jobhunter-resume-tailoring-queue-hardening`). The `job-tailoring-combined.html.twig` template already scaffolds a combined job/resume view. This feature polishes the display: a side-by-side original vs. tailored resume view, a download button for the generated PDF, a save-to-profile action, and a confidence score display when available.

## Goal

- Users can review the tailored resume side-by-side with the original before deciding to save or download.
- Users can download the PDF of their tailored resume directly.
- Users can save a tailored resume version as their active profile resume.
- Users see a confidence score where available to understand tailoring quality.

## Acceptance criteria

- AC-1: Side-by-side view — `/jobhunter/jobtailoring/{job_id}` renders the original profile text alongside the tailored resume text in a two-column layout (or stacked on mobile) when tailoring status is `completed`.
- AC-2: Download button — if `pdf_path` is set, a "Download PDF" button renders linking to the PDF path. Button is absent if no PDF exists.
- AC-3: Save-to-profile — a "Save as active resume" POST action button is present when tailoring is complete; clicking it sets the tailored resume as the user's active resume in their profile record.
- AC-4: Confidence score — if the tailored_resume record contains a `confidence_score` field (0–100), it is displayed as a percentage label next to the tailored result header.
- AC-5: Status states — while tailoring is `pending`/`queued`/`processing`, the page shows a status indicator with an auto-refresh hint (not a blank spinner); on `failed`, a retry option is shown.

## Non-goals

- Real-time streaming/polling for tailoring status (deferred).
- Editing the tailored resume inline (deferred).
- Multi-version resume history management (deferred).

## Security acceptance criteria

- Authentication/permission surface: Route `job_hunter.job_tailoring` requires `_permission: 'access job hunter'`. Users may only view tailoring results for their own job records — controller must verify `job.uid == current user uid`.
- CSRF expectations: "Save as active resume" POST uses the split-route pattern (`_csrf_token: 'TRUE'` on POST-only route entry). Download button is a plain GET link — no CSRF needed.
- Input validation requirements: `{job_id}` path param must be an integer; controller must 404 on non-existent or inaccessible job IDs.
- PII/logging constraints: Resume text content must not be logged to watchdog. PDF path must be a private filesystem path (not public); no public URL exposure of resume content.

## Implementation notes (to be authored by dev-forseti)

- Template: `job-tailoring-combined.html.twig` — already scaffolded; extend for side-by-side layout.
- New route needed: `job_hunter.job_tailoring_save_resume` (POST-only + CSRF) for the save-to-profile action.
- Confidence score: check if `tailored_resume` entity/array has a `confidence_score` key before rendering.

## Test plan (to be authored by qa-forseti)

- TC-1: Completed tailoring → side-by-side view renders; confidence score shown if present.
- TC-2: Download button present iff PDF exists.
- TC-3: Save-to-profile POST with valid CSRF → active resume updated; invalid CSRF → 403.
- TC-4: Pending/processing status → status indicator shown, not blank.
- TC-5: User A cannot view User B's tailoring result → 403 or 404.
- TC-6: Non-integer job_id in URL → 404, no PHP notice.

## Journal

- 2026-04-09: Feature stub created by ba-forseti (CEO dispatch, release-f grooming).
