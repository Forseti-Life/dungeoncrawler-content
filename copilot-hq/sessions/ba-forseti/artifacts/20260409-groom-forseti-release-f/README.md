# Groom: forseti release-f feature stubs

## Task
Produce 5–7 new feature stubs for `forseti.life` to unblock `pm-forseti` scope activation for `20260409-forseti-release-f`.

The forseti backlog is fully depleted (all 22 prior features are `shipped`). This is a clean-slate grooming pass.

## CEO direction (binding)

**Priority track 1 — Job Hunter UX polish (3–4 features):**
All structural refactoring is shipped. The next phase is user-facing quality. Target these specific areas:

1. **forseti-jobhunter-application-status-dashboard** — User-facing "My Jobs" tracking page. Templates exist (`my-jobs.html.twig`, `job-application-dashboard-wrapper.html.twig`). AC should cover: status pipeline display (applied → interview → offer/reject), filter by status, bulk archive. Route: `/job-hunter/my-jobs`.

2. **forseti-jobhunter-google-jobs-ux** — Google Jobs search/results UX hardening. Templates: `google-jobs-search.html.twig`, `google-jobs-job-detail.html.twig`, `job-search-results.html.twig`. AC: pagination, result count display, empty-state message, error state when API unavailable.

3. **forseti-jobhunter-resume-tailoring-display** — Tailored resume results display. Template: `job-tailoring-combined.html.twig`. AC: side-by-side original vs tailored view, download button, save-to-profile action, confidence score display.

4. **forseti-jobhunter-profile-completeness** — Profile completeness indicator. Template: `profile-completeness.html.twig`. AC: completeness percentage, missing field checklist, direct edit links, visible on profile summary and home dashboard.

**Priority track 2 — AI Conversation user-facing (1–2 features):**
The `ai_conversation` module exists with only admin routes. Add user-facing features:

5. **forseti-ai-conversation-user-chat** — User-facing AI chat interface at `/forseti/chat`. AC: authenticated users only, message history persisted per session, response streaming if supported, basic context injection (user's job seeker profile). Check `ai_conversation.routing.yml` for existing admin routes to inform architecture.

6. (Optional, if grooming time allows) **forseti-ai-conversation-job-context** — Context-aware job advice: when viewing a job posting, offer AI suggestions for tailoring resume and cover letter. AC: accessible from job detail page, passes job title + description as context.

## Required artifact structure per feature
Each feature stub must produce:
- `features/<feature-id>/feature.md` — with Status: ready, Website: forseti.life, Title, Priority, Description
- `features/<feature-id>/01-acceptance-criteria.md` — with `## Security acceptance criteria` section (required by `pm-scope-activate.sh`)
- `features/<feature-id>/03-test-plan.md`

## Reference files
- Existing templates: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/job_hunter/templates/`
- AI module: `/home/ubuntu/forseti.life/sites/forseti/web/modules/custom/ai_conversation/`
- Prior AC examples: `features/forseti-jobhunter-profile-refactor/01-acceptance-criteria.md`

## Acceptance criteria for this task
- All 5+ feature stubs have `Status: ready`, `Website: forseti.life`
- All stubs include `## Security acceptance criteria` in `01-acceptance-criteria.md`
- pm-forseti can run `pm-scope-activate.sh forseti <feature-id>` without error on at least 5 features

## Dispatched by
CEO (ceo-copilot-2) — 2026-04-09T06:41Z
