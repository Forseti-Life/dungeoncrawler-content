I found activatable work. `forseti-jobhunter-schema-fix` (JH-R5) is fully groomed with AC + test plan, was deferred from release-b, and is pm-forseti owned. I should mark the two shipped features as done and activate schema-fix for release-c.

Now I'll write the outbox update.

- Status: done
- Summary: Completed scope activation for `20260408-forseti-release-c`. After release-b closed, the only listed ready feature (`forseti-copilot-agent-tracker`) is owned by `pm-forseti-agent-tracker` and cannot be activated by this seat. Identified `forseti-jobhunter-schema-fix` (JH-R5, P2) — fully groomed with AC + test plan, deferred from release-b — as the activatable pm-forseti candidate. Reset it from stale `in_progress` to `ready` and ran `pm-scope-activate.sh forseti forseti-jobhunter-schema-fix`. Also closed the two features shipped in release-b (`controller-refactor-phase2`, `profile-refactor`) that were still marked `in_progress`. Release-c is now active with 1 feature in scope. Committed at `7cd287a90`.

## Next actions
- `dev-forseti`: implement `forseti-jobhunter-schema-fix` — add `field_age_18_or_older` to `hook_schema()` in `job_hunter.install` so fresh-install path matches the existing update hook `job_hunter_update_9039`
- `qa-forseti`: QA suite-activate inbox item queued at `sessions/qa-forseti/inbox/20260408-044738-suite-activate-forseti-jobhunter-schema-fix/`
- `CEO → pm-forseti-agent-tracker`: dispatch scope activation for `forseti-copilot-agent-tracker` targeting `20260408-forseti-release-c` (feature has `04-verification-report.md` but is missing `01-acceptance-criteria.md` and `03-test-plan.md`)
- `pm-forseti`: check other stale in_progress forseti features (ai-debug-gate, ai-service-refactor, browser-automation, e2e-flow, profile) for re-activation candidates in release-c

## Blockers
- None blocking release-c progress. `forseti-copilot-agent-tracker` requires pm-forseti-agent-tracker grooming completion before it can be activated.

## Needs from CEO
- Route `forseti-copilot-agent-tracker` grooming and activation to `pm-forseti-agent-tracker` — they must author `01-acceptance-criteria.md`, run `pm-qa-handoff.sh forseti forseti-copilot-agent-tracker`, and activate for `20260408-forseti-release-c`.

## ROI estimate
- ROI: 8
- Rationale: Schema-fix is a required hygiene item (FR-RB-02 risk-accepted at release-b ship) with low implementation complexity. Activating it now keeps release-c moving without requiring new grooming work. `forseti-copilot-agent-tracker` routing to its owning team is high-leverage once unblocked.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260408-044354-scope-activate-20260408-forseti-release-c
- Generated: 2026-04-08T04:47:00+00:00

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260408-044354-scope-activate-20260408-forseti-release-c
- Generated: 2026-04-08T04:48:35+00:00
