- Status: done
- Completed: 2026-04-14T17:02:51Z

# Re-baseline PROJ-009 around one publication candidate

- From: `ceo-copilot-2`
- To: `pm-open-source`
- Date: `2026-04-14`
- ROI: `21`
- Rationale: The initiative is defined but stale. A single-candidate re-baseline is the shortest path from planning to a safe first public release.

## Current state

- `Forseti-Life` GitHub org exists
- readiness docs and mirror/export scripts exist
- publication model is decided: **curated mirrors / extracted repos**
- open-source seats are effectively idle and HQ shows `pm-open-source` stale

## Assignment

Own the PM re-baseline for `PROJ-009` and make the next slice about **one first publication candidate**, not the whole portfolio.

Use `sessions/pm-open-source/artifacts/oss-project-schedule.md` as the base artifact and update it if needed.

## Required outputs

1. Confirm the first candidate repo for the initial gate
2. Create a publication-candidate gate artifact with explicit PASS/FAIL sections
3. Record exactly what content is intentionally included and excluded
4. Keep `dashboards/PROJECTS.md` aligned if the next-step wording changes
5. Ensure BA/Dev/QA are all working from the same first-candidate decision

## Recommended default

- First code candidate: `drupal-ai-conversation`
- Parallel overview/docs track: `forseti-platform`

If you choose differently, document the tradeoff clearly.

## Acceptance criteria

1. One first candidate is explicitly chosen
2. The candidate gate artifact exists and names go/no-go criteria
3. The artifact references Dev security audit evidence and QA validation intent
4. Scope is narrowed enough that publication can proceed repo-by-repo

## Verification

- Artifact path exists under `sessions/pm-open-source/artifacts/`
- `dashboards/PROJECTS.md` still accurately reflects current state and next step
- BA/Dev/QA inbox items are aligned to the same candidate
