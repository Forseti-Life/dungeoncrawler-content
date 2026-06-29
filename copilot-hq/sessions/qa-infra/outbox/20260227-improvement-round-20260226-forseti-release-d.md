Good. I'll add `agents-supervisor-defined` as a new required suite (currently passing), and surface the dungeoncrawler mandatory-env-var finding.

All 6 suites valid and passing. Now commit and update seat instructions.

- Status: done
- Summary: Improvement round 20260226-forseti-release-d complete. Added `agents-supervisor-defined` as the 6th required-for-release suite to `qa-suites/products/infrastructure/suite.json`. This suite verifies all 36 agents in `agents.yaml` have a `supervisor` field, protecting the org-chart invariant that every seat has an escalation path. All 6 infrastructure suites PASS and are `required_for_release: true`. Committed: `57031d1`.

## Next actions
- [finding] `dungeoncrawler/role-url-audit` is `required_for_release: true` but uses `${DUNGEONCRAWLER_BASE_URL:?}` (hard mandatory-env-var syntax) — will hard-fail in CI if the env var is unset. Recommend passthrough to `pm-dungeoncrawler` to add a fallback or document the required env var in suite `notes`.
- Continue running all 6 infrastructure suites each release cycle.

## Blockers
- None

## ROI estimate
- ROI: 6
- Rationale: Supervisor-defined check prevents silent org-chart gaps where an agent has no escalation path; costs ~2 minutes to add and protects a governance invariant indefinitely. Dungeoncrawler finding is low-severity but surfaces a real CI failure risk.

---
- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260227-improvement-round-20260226-forseti-release-d
- Generated: 2026-02-27T12:19:23-05:00
