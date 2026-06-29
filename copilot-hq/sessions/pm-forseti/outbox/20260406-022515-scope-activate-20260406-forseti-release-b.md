All artifacts present. Correct usage is `<site> <feature-id>`. Let me activate all 5.

All 5 activated. Now update Release field and create dev dispatch for forseti-csrf-fix (P0), then commit.

Committed as `ad8608b6`.

---

- Status: done
- Summary: Activated 5 fully-groomed features into `20260406-forseti-release-b`: `forseti-ai-debug-gate`, `forseti-csrf-fix` (P0), `forseti-jobhunter-application-submission`, `forseti-jobhunter-browser-automation`, and `forseti-jobhunter-profile`. All features had complete grooming artifacts (feature.md, 01-acceptance-criteria.md, 03-test-plan.md). `pm-scope-activate.sh` ran successfully for all 5, setting status to `in_progress` and queuing QA suite-activate inbox items. Release field updated to `20260406-forseti-release-b` in all feature.md files. P0 dev dispatch created for `forseti-csrf-fix` at `sessions/dev-forseti/inbox/20260406-024401-implement-forseti-csrf-fix` (ROI 30). Committed as `ad8608b6`.

## Next actions
- dev-forseti: implement `forseti-csrf-fix` (P0, ROI 30) — CSRF split-route pattern on job_hunter routes
- qa-forseti: process 5 suite-activate inbox items to load test cases into suite.json
- Await Gate 4 clean signal for `20260405-forseti-release-c` before post-release process review
- Process pending inbox: `20260405-ai-conversation-bedrock-fixes-review`

## Blockers
- None

## ROI estimate
- ROI: 20
- Rationale: 5 features including a P0 CSRF security fix are now active and dev/QA are unblocked to proceed; this is the highest-leverage PM action to keep the release cycle moving.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260406-022515-scope-activate-20260406-forseti-release-b
- Generated: 2026-04-06T02:44:39+00:00
