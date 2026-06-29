# Groom: forseti release-g backlog

- Release: 20260409-forseti-release-g
- Dispatched by: pm-forseti
- Date: 2026-04-09
- ROI: 25

## Context

Release-f shipped 5 features (all Job Hunter UX polish + AI Conversation user-facing chat).
The backlog is now empty — no `Status: ready` features exist for forseti.life.

Post-release QA audit (run `20260409-135804`) is clean: 0 violations, 0 failures, 0 config drift.

## Shipped in release-f (do not re-groom)
- forseti-jobhunter-application-status-dashboard — My Jobs pipeline view, status/company filters, bulk archive
- forseti-jobhunter-google-jobs-ux — pagination, result count, empty/error states
- forseti-jobhunter-resume-tailoring-display — side-by-side view, PDF download, save-to-profile, confidence score
- forseti-jobhunter-profile-completeness — completeness service, missing-field checklist, widget on profile + home
- forseti-ai-conversation-user-chat — /forseti/chat route, history persistence, job-seeker context injection

## Product focus areas (CEO direction, as of 2026-04)
- Job Hunter: application tracking, AI-assisted tailoring, user experience
- AI Conversation (Forseti assistant): user-facing chat improvements
- Community Safety module: (lower priority, but in scope for the product)
- Agent Tracker: incremental improvements as needed

## Task

Groom **3–5 feature stubs** for `20260409-forseti-release-g` for `forseti.life`.

For each feature stub, create:
- `features/<feature-id>/feature.md` — with `Status: ready`, `Website: forseti.life`, `Release: 20260409-forseti-release-g`
- `features/<feature-id>/01-acceptance-criteria.md` — with specific, measurable ACs and `## Security acceptance criteria` section (required by site instructions)
- `features/<feature-id>/03-test-plan.md` — with specific test cases

## Suggested areas to consider (pick highest ROI for the product)

Based on what shipped in release-f, natural next-step improvements include:
1. **Job Hunter: interview prep / calendar integration** — users accepted to interview have no tooling; a structured interview prep checklist or notes surface would be high value
2. **Job Hunter: job recommendations / saved search** — users lack a way to save search parameters or receive proactive job suggestions from their profile
3. **AI Conversation: context persistence across sessions** — currently each /forseti/chat session starts fresh beyond the most-recent node; multi-session history browsing would improve the user experience
4. **AI Conversation: conversation export / share** — allow users to export a conversation thread as text or PDF
5. **Job Hunter: cover letter generation** — parallel to resume tailoring; generate a targeted cover letter for a saved job using the user's profile

Use your judgment — these are suggestions, not mandates. The quality bar is: each stub must be implementable by dev-forseti in a single release cycle with no external blockers.

## Acceptance criteria for this grooming task

- [ ] 3–5 feature stubs exist with `Status: ready` and `Website: forseti.life`
- [ ] Each stub has `feature.md`, `01-acceptance-criteria.md`, `03-test-plan.md`
- [ ] Each AC file has a populated `## Security acceptance criteria` section
- [ ] Features are committed to the repo

## Done signal

Write outbox with `Status: done`, list feature IDs created, and commit hash.
pm-forseti will run `pm-scope-activate.sh` on each feature after reviewing your stubs.
