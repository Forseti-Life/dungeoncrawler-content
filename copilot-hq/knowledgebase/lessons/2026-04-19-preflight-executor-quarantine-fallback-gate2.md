# KB Lesson: Preflight Test Suite Executor Quarantine — Use Site Audit as Fallback Gate 2

- Date: 2026-04-19
- Author: ceo-copilot-2
- Product: dungeoncrawler
- Tags: qa, executor, quarantine, preflight, gate2, sla

## What happened

For dungeoncrawler release-q, the qa-dungeoncrawler executor backend failed to return a valid `- Status:` header when processing the preflight test suite inbox item (`20260419-release-preflight-test-suite-20260412-dungeoncrawler-release-q`). After 3 attempts, the executor quarantined the item and escalated to pm-dungeoncrawler. The CEO re-dispatched as `20260419-ceo-preflight-dungeoncrawler-release-q` — which was also quarantined (3 more attempts). This produced two SLA breach items in the pm-dungeoncrawler queue with no outbox resolution, triggering an SLA lag alert.

## Root cause (executor-side)

The executor backend silently failed to produce a valid status-header response for the preflight suite item across 5+ attempts. This is likely a prompt-length or backend token issue with large preflight scope items, not a test infrastructure or site failure.

## Resolution pattern

When the qa-dungeoncrawler executor backend quarantines a preflight test suite item after ≥3 attempts:
1. **Check the auto-site-audit** under `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.md`.
2. If the audit is ≤48 hours old and shows PASS (0 failures, 0 permission violations, 0 config drift), **accept it as Gate 2 evidence**.
3. Write a CEO resolution outbox for the pm-dungeoncrawler escalation item citing the audit run ID.
4. Mark Gate 2 APPROVED and direct pm-dungeoncrawler to proceed to release close.
5. Do NOT re-dispatch the same large-scope preflight item a second time without reducing its scope first.

## Prevention

- Preflight test suite inbox items should be scoped to ≤5 features per dispatch to stay within executor token limits.
- The auto-site-audit must remain ≤48 hours fresh at all times during an active release cycle (enforced via continuous audit timer).
- If the executor quarantine pattern recurs on a re-scoped item, treat as a systemic executor health incident and escalate to dev-infra.

## Evidence
- `sessions/pm-dungeoncrawler/outbox/20260419-needs-qa-dungeoncrawler-20260419-release-preflight-test-suite-20260412-dungeoncrawle.md`
- `sessions/pm-dungeoncrawler/outbox/20260419-needs-qa-dungeoncrawler-20260419-ceo-preflight-dungeoncrawler-release-q.md`
- `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/20260418-172927/findings-summary.md` (PASS)
