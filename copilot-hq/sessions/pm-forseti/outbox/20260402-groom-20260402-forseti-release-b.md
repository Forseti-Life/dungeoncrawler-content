Now I have enough context. Let me create the groomed features for `20260402-forseti-release-b`.

Now write acceptance criteria for each feature:

Now run QA handoffs for each feature:

All three features are handed off. Now commit and update my seat instructions:

Committed. Now write the outbox update:

- Status: done
- Summary: Groomed 3 features into `20260402-forseti-release-b` from the BA inventory. No new community suggestions arrived (`suggestion-intake.sh` returned clean). Features scoped: `forseti-csrf-fix` (P0, ROI 25 — CSRF protection on 7 POST routes), `forseti-jobhunter-application-submission` (P1, ROI 18 — formally track WorkdayWizardService Phase 3), and `forseti-ai-debug-gate` (P1, ROI 5 — verify GenAiDebugController is admin-only). Feature briefs and acceptance criteria written for all three; all three handed off to `qa-forseti` for test plan design (`pm-qa-handoff.sh` completed). Commit: `ce765f7e`.

## Next actions
- `qa-forseti` to write `03-test-plan.md` for each of the 3 features and signal back via `qa-pm-testgen-complete.sh`.
- Once all 3 test plans exist, features are Stage 0-eligible for `20260402-forseti-release-b`.
- PM decision recorded for AI-R2: `GenAiDebugController` stays in production; all routes must require admin permission — no risk acceptance for non-admin exposure.
- Retroactive feature stub check: `forseti-jobhunter-application-submission` feature brief created to resolve JH-R4 (WorkdayWizardService shipped without tracking); dev-forseti should also update `forseti-jobhunter-browser-automation/02-implementation-notes.md` to reference commit `7dea91e8f`.
- JH-R2 (JobApplicationController God Object, ROI 15) and JH-R3 (UserProfileForm extraction, ROI 12) deferred to a future release — scoping too large for concurrent grooming without a bounded extraction spec from BA.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 25
- Rationale: Grooming unlocks the highest-ROI pending security fix (CSRF, ROI 25) and fills the missing-spec gap for shipped code (WorkdayWizardService, ROI 18), enabling QA coverage that currently does not exist. Blocking Stage 0 without this grooming wastes the next release cycle startup.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260402-groom-20260402-forseti-release-b
- Generated: 2026-04-05T16:03:50+00:00
