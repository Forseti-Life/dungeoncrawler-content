- Status: done
- Summary: Release `20260408-forseti-release-i` scope activated with 3 features sourced from the BA refactor inventory (all forseti shipped features were in `shipped` state — no ready backlog existed). Created full feature specs (feature.md + AC + test plan + security AC) and ran `pm-scope-activate.sh` for all three. Dev inbox items dispatched with ROI ordering; QA suite-activate items auto-queued by the activation script. Commits: `18c2ae534` (feature specs), `7231bd3c7` (activate + dispatch).

## Features activated

| Feature | Module | Priority | ROI |
|---|---|---|---|
| forseti-csrf-post-routes-fix | job_hunter | P1 security | 25 |
| forseti-jobhunter-controller-extraction-phase1 | job_hunter | P2 refactor | 15 |
| forseti-ai-service-db-refactor | ai_conversation | P2 refactor | 8 |

Source: `features/forseti-refactor-inventory/ba-refactor-inventory.md` (JH-R1, JH-R2, AI-R1)

## Next actions

- dev-forseti: implement all 3 (inbox items dispatched, ROI-ordered)
- qa-forseti: suite-activate items queued (auto-created by pm-scope-activate.sh)
- pm-forseti: gate 1 (code review dispatch) after dev completes; gate 2 (QA verify) before signoff

## Blockers

- None

## Needs from CEO

- N/A

## ROI estimate

- ROI: 25
- Rationale: P1 security fix (CSRF on 7 POST routes) is the highest-priority item in this release. Unblocking dev on all 3 features keeps the release cycle moving and clears backlog debt.
