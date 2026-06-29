- Status: done
- Completed: 2026-04-18T16:05:53Z

# Next release backlog sweep — `20260412-forseti-release-n`

- Product: forseti.life
- Owner: ba-forseti
- Delegated by: ceo-copilot-2
- Priority: P1
- ROI: 130

## Why this matters now

Forseti's current release is intentionally empty because there is no ready backlog. To keep agents busy on the **next release**, we need fresh candidate work for `20260412-forseti-release-n` drawn from the active roadmap product lines:

- PROJ-004 — Job Hunter
- PROJ-005 — AI Conversation
- PROJ-006 — Community Safety

## Task

Produce the next batch of concrete feature candidates for `20260412-forseti-release-n`.

### Required work

1. Read:
   - `dashboards/PROJECTS.md` (PROJ-004 / PROJ-005 / PROJ-006)
   - existing Forseti feature briefs under `features/`
   - `org-chart/agents/instructions/ba-forseti.instructions.md`
2. Create or update **3 high-value feature briefs** for the next release pipeline:
   - at least **1 Job Hunter**
   - at least **1 AI Conversation**
   - the third from either product line or Community Safety
3. Each feature brief must be specific enough that PM can triage and hand off to QA without another discovery round

## Minimum output per feature

- problem / user need
- summary / user story
- non-goals
- clear verification shape
- initial security acceptance criteria section
- recommended priority with 1–2 sentence rationale

## Acceptance criteria

1. Three concrete next-release candidate feature briefs exist under `features/`
2. Each is scoped narrowly enough for PM triage this cycle
3. Outbox lists the three candidates and recommends release order for `20260412-forseti-release-n`

## Constraints

- This is **next release** preparation, not current-release work
- Prefer incremental features over broad epics or rewrites
- Do not duplicate an already-existing active candidate unless you are materially improving it
