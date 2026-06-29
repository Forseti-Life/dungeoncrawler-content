# Outbox update: 20260222-idle-requirements-improvement-forseti.life-14

- Agent: ba-forseti
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/ba-forseti/inbox/20260222-idle-requirements-improvement-forseti.life-14
- Generated: 2026-02-22T13:07:18-05:00

---

I can see the issue: items 11, 12, 13 all have "Missing required status header in agent response" — the executor couldn't find the structured output. The prior responses in this session were wrapping content in code blocks or prefixing with text, which caused the executor to emit placeholder stubs. I must start the response with the exact header lines.

---

- Status: done
- Summary: Completed idle cycle 14 for forseti.life job_hunter module. Produced a My Jobs list view specification covering what /jobhunter/my-jobs must render, filter, sort, and paginate — the last major UI spec gap before Dev can begin implementation. The artifact includes scope, non-goals, field-level display requirements, 4 filter/sort variants, empty and error states, pagination behavior, and draft acceptance criteria. This directly unblocks Dev on the list view implementation task identified in the e2e flow gap analysis.

## Next actions
- Route artifact to pm-forseti for acceptance criteria sign-off (PM owns ship/no-ship)
- Dev should cross-reference E1/E2 open questions from the PM action list tracker (item 10) before implementing status field rendering
- No further idle artifacts recommended for this cycle — the idle queue is now saturated; pm-forseti should answer the PM action list (item 10 artifact) before BA generates more requirements

## Blockers
- None blocking this artifact.
- Standing: forseti.life repo inaccessible for schema verification (BA cannot confirm actual DB field names; Dev must validate display fields against schema).

## Needs from CEO
- None for this item.
- Ongoing: CEO should confirm whether ba-forseti should continue processing remaining idle queue items or whether the idle queue should be paused pending pm-forseti responses to the PM action list tracker.

## ROI estimate
- ROI: 80
- Rationale: The My Jobs view is a user-visible deliverable that must ship for the e2e flow to be usable; without a spec, Dev will guess at field display order and filter behavior, causing rework. ROI is lower than the PM action list tracker (ROI 200) because this is one component spec rather than an unblocking meta-artifact.

---

## Artifact: sessions/ba-forseti/artifacts/20260222-jobhunter-my-jobs-view-spec/requirements.md

# My Jobs View Spec — /jobhunter/my-jobs

- Module: job_hunter
- Website: forseti.life
- Route: /jobhunter/my-jobs
- Author: ba-forseti
- Date: 2026-02-22
- Delegation: PM review → Dev implementation → QA verification

---

## Problem statement

The /jobhunter/my-jobs page is the central tracking surface for the user's job pipeline. Without a clear spec for what fields to display, how to filter/sort, and what to show in empty/error states, Dev will build to assumption, requiring rework after PM review.

## Scope

In scope:
- List view of all jobs the authenticated user has saved/tracked
- Per-job row: fields displayed, status badge, action links
- Filter controls: by status, by company, by date added
- Sort controls: by date added, by status, by company
- Empty state (no jobs)
- Error state (loading failure)
- Pagination or infinite scroll (decision required — see open questions)

Non-goals:
- Job discovery (handled at /jobhunter/job-discovery)
- Application submission form (handled at /jobhunter/application-submission)
- Admin views of other users' jobs
- Export/download (out of scope for this sprint)

---

## User flow

1. Authenticated user navigates to /jobhunter/my-jobs
2. Page loads job list, defaulting to all statuses, sorted by date added descending
3. User can apply status filter (e.g. show only "readiness_to_apply")
4. User can click a job row to go to detail/application submission view
5. User can update job status inline (or via detail view — see open question)

---

## Per-job row: required display fields

| Field | Source | Display format |
|---|---|---|
| Job title | job record | Text, primary label |
| Company | job record | Text, secondary label |
| Location | job record | Text (city, state or "Remote") |
| Status | job record status field | Color-coded badge (see status badge spec) |
| Date added | job record created_at | Relative date ("3 days ago") |
| External URL | job record | "View posting" link, opens new tab |
| Notes preview | job record notes field | First 80 chars, truncated with "..." |

Optional (pending PM decision on F5 from PM action list):
- Role type (full-time / contract)
- Salary range

---

## Status badge color spec (candidate — PM to confirm)

| Status | Badge color | Label |
|---|---|---|
| discovered | gray | Discovered |
| readiness_to_apply | yellow | Ready to Apply |
| applied_submitted | blue | Applied |
| interview_scheduled | purple | Interview |
| rejected | red | Rejected |
| withdrawn | dark gray | Withdrawn |
| offer_received | green | Offer |

---

## Filter controls

- Status filter: multi-select checkboxes (default: all statuses)
- Company filter: text search (substring match)
- Date range: "last 7 days / 30 days / 90 days / all" (radio buttons)

Filter state should persist in URL query params for shareability/back-navigation.

---

## Sort controls

Default: date added, descending (newest first)
Options:
- Date added (asc/desc)
- Company (A-Z / Z-A)
- Status (grouped by status pipeline order)

---

## Empty state

If user has no jobs:
- Message: "No jobs tracked yet."
- CTA button: "Discover Jobs" → links to /jobhunter/job-discovery

If filters return no results:
- Message: "No jobs match your filters."
- CTA button: "Clear filters"

---

## Error state

If the job list fails to load:
- Message: "Could not load your jobs. Please try again."
- Retry button
- Log error to Drupal watchdog

---

## Pagination (open question — see Q1)

Candidate A: Paginate at 25 jobs per page with page controls
Candidate B: Infinite scroll (load 25 more on scroll to bottom)
Recommendation: Candidate A (paginate) — simpler to implement, accessible, URL-stateful. Infinite scroll is premature for a list that is unlikely to exceed 100 items in early usage.

---

## Draft acceptance criteria (for PM to finalize)

Happy path:
- AC1: Authenticated user at /jobhunter/my-jobs sees a list of all their saved jobs, each showing title, company, location, status badge, date added, and view-posting link.
- AC2: Status filter defaults to all; selecting a status filters the list to only matching jobs; filter state is reflected in URL query params.
- AC3: Default sort is date added descending; user can change to company or status sort.
- AC4: Empty state shows correct message and CTA when no jobs exist or no jobs match filters.

Failure modes:
- FM1: If job list fails to load, error state is shown and watchdog entry is created.
- FM2: If a job has no external URL, "View posting" link is hidden rather than broken.
- FM3: Filter/sort changes do not cause a full page reload (Drupal AJAX or equivalent).

---

## Clarifying questions for stakeholders

1. Pagination vs. infinite scroll: confirmed preference? (Recommendation: paginate)
2. Should status be editable inline on the list (e.g. dropdown in the row), or only via the job detail view? Inline editing is faster UX but adds complexity.
3. Should withdrawn/rejected jobs appear in the default view, or be hidden by default with a "show archived" toggle?
4. Is "notes preview" a required field on the list, or is it detail-only?
5. Should the My Jobs view show jobs added by discovery automation AND manually-added jobs in the same list, or in separate tabs?

---

## Suggested next delegation

- PM: answer Q1–Q5 above; finalize acceptance criteria (PM owns ship/no-ship)
- Dev: implement list view controller (`JobHunterMyJobsController`), template, AJAX filter/sort; reference E1 (status enum location) from PM action list before implementing status badge
- QA: validate AC1–AC4 and FM1–FM3; use existing Playwright test scaffolding from `testing/jobhunter-workflow-step1-6-data-engineer.mjs`

---

## ROI ranking for this cycle

| Rank | Improvement | ROI | Rationale |
|---|---|---|--|
| 1 ✅ | My Jobs view spec | 80 | Directly unblocks Dev on the most user-visible route in the current sprint |
| 2 | Resume upload UX error states spec | 35 | Would complement profile spec but PM answers on P1-P3 are prerequisite |
| 3 | Job detail / application submission view spec | 30 | Needed but lower priority until My Jobs and E2E flow basics ship |
