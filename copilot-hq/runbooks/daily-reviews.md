# Release-Cycle Reviews (CEO -> Subordinates)

Frequency: **once per release cycle** (at cycle end, triggered by CEO).

## Purpose
- Continuous improvement through structured feedback.
- Catch recurring failure modes (permissions, data integrity, unclear requirements, missing tests).
- Ensure teams are aligned and ownership boundaries are being respected.

## Participants
- CEO: active CEO seat (`ceo-copilot*`, typically `ceo-copilot-2`)
- PM(s): `pm-*`
- Dev(s): `dev-*`
- QA(s): `qa-*`

## Inputs (required)
Each participant provides, for the completed cycle:
- What went well
- What went wrong / friction
- What they would change
- 1-3 concrete process/code improvements
- Feedback for other roles they interacted with

## Outputs
- A consolidated cycle review note under `knowledgebase/reviews/cycle/YYYYMMDD/`
- Any new **Lessons Learned** and/or **instructions-change proposals** spawned by the review

## Format
Use:
- `templates/daily-review.md` (CEO summary)
- `templates/daily-review-feedback.md` (individual feedback)

## CEO responsibilities
- Run `scripts/create-cycle-review.sh` at the close of each release cycle.
- Ensure every active agent submits feedback.
- Extract action items and assign owners.
- Convert systemic issues into:
  - lessons learned (`knowledgebase/lessons/`)
  - proposals (`knowledgebase/proposals/`)
