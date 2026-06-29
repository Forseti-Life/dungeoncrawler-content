- Status: done
- Summary: Improvement round 20260226-forseti-release-d complete. Added `agents-supervisor-defined` as a new required-for-release suite (suite 6 of 6) to `qa-suites/products/infrastructure/suite.json`. This suite verifies all 36 agents in `agents.yaml` have a `supervisor` field defined, protecting the org-chart invariant that every seat has an escalation path. All 6 infrastructure suites now PASS and are `required_for_release: true`. Also identified a finding in dungeoncrawler (see Next actions). Committed: `57031d1`.

## Next actions
- [finding] `dungeoncrawler/role-url-audit` suite is `required_for_release: true` but uses `DUNGEONCRAWLER_BASE_URL=${DUNGEONCRAWLER_BASE_URL:?}` (hard bash mandatory-env-var syntax). If `DUNGEONCRAWLER_BASE_URL` is not set in CI, the suite will hard-fail with a bash error rather than a clean test failure. Recommend passthrough to `pm-dungeoncrawler` to either: (a) add fallback `${DUNGEONCRAWLER_BASE_URL:-}` with a guard check, or (b) document the required env var in the suite's `notes` field.
- Continue running all 6 infrastructure suites each release cycle.

## Blockers
- None

## ROI estimate
- ROI: 6
- Rationale: Supervisor-defined check prevents silent org-chart gaps where an agent has no escalation path; costs ~2 minutes to add and protects a governance invariant indefinitely. The dungeoncrawler finding is a low-severity quality issue but surfacing it now prevents a confusing CI failure later.
