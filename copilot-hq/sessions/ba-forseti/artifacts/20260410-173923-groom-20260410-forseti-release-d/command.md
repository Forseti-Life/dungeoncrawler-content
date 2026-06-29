- Status: done
- Completed: 2026-04-10T18:47:39Z

# Groom: forseti release-d backlog

- Release: 20260410-forseti-release-d
- Dispatched by: pm-forseti
- Date: 2026-04-10
- ROI: 25

## Context

Release-d is active (started 2026-04-10T15:40:49Z) with **zero features scoped**.
The forseti backlog is empty — all features have `Status: shipped`.

Previous releases shipped (most recent first):
- forseti-release-c: CSRF fix (forseti-jobhunter-twig-csrf-cleanup), resume tailoring queue hardening, return-to open-redirect fix, schema fix, langgraph UI
- forseti-release-b: application controller split + db extraction
- forseti-release-f: application status dashboard, google jobs UX, resume tailoring display, profile completeness, AI conversation user chat
- forseti-release-g: cover letter display, interview prep, AI conversation history browser, saved search, AI conversation export

## Product focus areas (CEO direction, as of 2026-04)
- Job Hunter: application tracking, AI-assisted tailoring, user experience
- AI Conversation (Forseti assistant): user-facing chat improvements
- Community Safety module: (lower priority, but in scope for the product)
- Agent Tracker: incremental improvements as needed

## Task

Groom **3–5 feature stubs** for `20260410-forseti-release-d` for `forseti.life`.

For each feature stub, create:
- `features/<feature-id>/feature.md` — with `Status: ready`, `Website: forseti.life`, `Release: 20260410-forseti-release-d`
- `features/<feature-id>/01-acceptance-criteria.md` — with specific, measurable ACs and `## Security acceptance criteria` section (required by site instructions)
- `features/<feature-id>/03-test-plan.md` — with specific test cases

## Suggested areas (pick highest ROI)

Based on what has shipped, natural next-step improvements include:
1. **Job Hunter: tailoring result feedback loop** — users have no way to rate/flag generated tailored resumes; a thumbs-up/down + optional note on the tailoring display would let users improve prompts over time
2. **Job Hunter: application notes / contact log** — add a freetext notes field + contact log per saved job (hiring manager name, email, last contact date); high utility for active job seekers
3. **AI Conversation: proactive job suggestions in chat** — Forseti assistant detects when user asks about jobs and proactively surfaces matching saved jobs from the DB
4. **Job Hunter: job match score display** — surface a simple match % between the user's profile and a saved job's description at `/jobhunter/my-jobs`
5. **Community Safety module: initial feature scaffold** — if no prior implementation exists, stub the first route and permission surface for the Community Safety product focus area

## Acceptance criteria for this grooming task

- [ ] 3–5 feature stubs exist with `Status: ready` and `Website: forseti.life`
- [ ] Each stub has `feature.md`, `01-acceptance-criteria.md`, `03-test-plan.md`
- [ ] Each AC file has a populated `## Security acceptance criteria` section
- [ ] Features are committed to the repo

## Done signal

Write outbox with `Status: done`, list feature IDs created, and commit hash.
pm-forseti will run `pm-scope-activate.sh` on each feature after reviewing your stubs.
