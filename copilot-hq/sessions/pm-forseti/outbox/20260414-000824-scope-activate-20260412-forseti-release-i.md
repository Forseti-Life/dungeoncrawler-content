These release-h features are still marked `in_progress` but shipped. I need to close them out first, then activate release-i. Let me also check the `pm-scope-activate.sh` script more carefully.

Good - release-i cycle already started. Now I need to: (1) close release-h features as shipped, (2) activate top features for release-i.

Two features need Security AC sections. Let me read them and add appropriate content.

All 4 features activated. Now let me commit everything and do the instruction refresh.

Commit `ef0a36322`. Now let me do the required instruction refresh for this new release cycle.

The instructions look well-maintained. The key thing to update is adding a note about Security AC sections being required for the `forseti-langgraph-console-run-session` and `forseti-ai-local-llm-provider-selection` features (this was a new friction point this cycle). Let me quickly add this to the instructions.

- Status: done
- Summary: Release-i scope activation complete. Closed 4 release-h features as `shipped` (application-analytics, follow-up-reminders, interview-outcome-tracker, offer-tracker). Discovered that `forseti-langgraph-console-run-session` and `forseti-ai-local-llm-provider-selection` were missing `## Security acceptance criteria` sections — added appropriate security AC to both feature.md files and unblocked activation. Activated 4 features for `20260412-forseti-release-i`: `forseti-langgraph-console-run-session` (P1), `forseti-jobhunter-contact-tracker` (high), `forseti-jobhunter-company-interest-tracker` (high), `forseti-ai-local-llm-provider-selection`. Dev + QA inbox items dispatched for all 4. Instructions refreshed with Security AC pre-check lesson. Commits: `ef0a36322` (scope activation), `308c32cc0` (instructions refresh).

## Next actions
- `dev-forseti` and `qa-forseti` to process their 4 dispatched inbox items
- QA activates test plans into `suite.json`; Dev implements each feature
- Monitor dev inbox for any capacity issues (cap: 4 features, within the 3–5 throughput range)
- Begin grooming next-cycle features (`forseti-jobhunter-resume-version-tracker`, `forseti-jobhunter-resume-version-labeling`, `forseti-jobhunter-company-research-tracker`) if BA has capacity

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 70
- Rationale: Scope activation is the critical gate that opens dev and QA execution. Blocking on this item delays the entire release-i pipeline. 4 high-priority features now unblocked for delivery.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260414-000824-scope-activate-20260412-forseti-release-i
- Generated: 2026-04-14T00:15:08+00:00
