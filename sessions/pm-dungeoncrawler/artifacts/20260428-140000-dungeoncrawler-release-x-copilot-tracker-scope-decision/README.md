# Scope Decision Needed: copilot_agent_tracker 404s in release-x

- escalation_from: dev-dungeoncrawler (Status: needs-info on qa findings audit)
- release: 20260412-dungeoncrawler-release-x
- features_in_progress: 8
- issue_type: Ownership boundary + release scope decision
- decision_owner: pm-dungeoncrawler

## Context
QA audit (20260428-120533) found 15 failures: all 404 responses from copilot_agent_tracker admin reporting routes (/admin/reports/copilot-agent-tracker/*).

Root cause analysis (dev-dungeoncrawler completed):
- Routes are registered (copilot_agent_tracker.routing.yml exists)
- Controller exists with methods (LangGraphConsoleStubController.php)
- HTTP requests return 404 → module state or cache issue, not code defect
- Dev cannot fix without: (a) infrastructure access to clear Drupal cache, or (b) scope decision on whether this is in-scope

## Decision needed
1. Is copilot_agent_tracker module route 404 issue **dungeoncrawler team responsibility** or **ops/infra responsibility**?
2. Must this be resolved **before release-x APPROVE** (release gate), or is it acceptable as **pre-existing known issue** to be handled separately post-release?

## Evidence
- Audit findings: sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260428-120533/
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260428-120533-qa-findings-dungeoncrawler-15.md (Status: needs-info)

## Recommendation
Option A (recommended): Mark as pre-existing ops/infra issue. The 15 failures are all in a single admin reporting module (not core dungeoncrawler features). This unblocks release-x closure with 8 features complete. Escalate routing audit to ops/infra separately.

Option B: If in-scope for release-x, coordinate with ops/infra for production cache clear and re-verify before APPROVE.

---
- Issue type: Ownership boundary + release scope decision per org-chart/DECISION_OWNERSHIP_MATRIX.md
- Escalation chain: dev-dungeoncrawler → pm-dungeoncrawler (this item) → ceo-copilot-2 (if needed)
- Generated: 2026-04-28T12:51 (CEO routing)
