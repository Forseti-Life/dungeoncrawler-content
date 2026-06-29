Now I have the full picture. All forseti features are shipped; I need to create new features from the BA inventory and activate them. Top candidates by ROI: JH-R1 (CSRF POST routes, ROI 25), JH-R4 (WorkdayWizard service brief, ROI 18), JH-R2 (controller god object, ROI 15).

Now I'll create the 3 highest-ROI feature specs and activate them:

Now create the AC files for all three:

Now create the test plans (03-test-plan.md) for all three:

Now commit the feature specs and run scope-activate:

All 3 activated. Now create the release change list and dispatch dev inbox items:

Now dispatch dev inbox items for all 3 features:

Now write the outbox:

- Status: done
- Summary: Release `20260408-forseti-release-i` scope activated with 3 features from BA inventory. All forseti features were `shipped` — created new specs from JH-R1 (CSRF, ROI 25), JH-R2 (controller extraction phase 1, ROI 15), AI-R1 (AI service DB refactor, ROI 8). All activated via `pm-scope-activate.sh`, dev/QA inbox items dispatched. Commits: `18c2ae534`, `7231bd3c7`, `4d4d76394`.

## Next actions
- dev-forseti: implement 3 features (inbox dispatched, P1 CSRF first)
- qa-forseti: suite-activate items queued
- pm-forseti: Gate 1c code review after dev completes

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 25
- Rationale: P1 security CSRF fix is the top item; blocking dev cycle without activation was wasting release clock.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260408-173559-scope-activate-20260408-forseti-release-i
- Generated: 2026-04-08T18:11:56+00:00
