- Status: done
- Summary: Investigated the pending release code-review artifacts that were not being addressed and identified the actual process break. These items were **not** stalled inside the LangGraph `agentic_sdlc` flow; they never entered it. The `agentic_sdlc` graph has a built-in `Code Review` node, but that node belongs to delivery work launched from `feature_request_intake`. In contrast, release-cycle pre-ship review is still queued by `scripts/release-cycle-start.sh` as a legacy inbox item under `sessions/agent-code-review/inbox/<date>-code-review-<site>-<release>/command.md` with **no** `Flow id`, `Flow node`, or `Flow owner seat` metadata. Because `scripts/route-flow-transitions.py` only advances flow-managed work, Gate 1b findings from release code review had no LangGraph transition path and depended on manual PM routing. The second gap was automation: signoff/awaiting-signoff dispatch continued to prompt PM signoff based on QA + missing artifact state without verifying that MEDIUM+ code-review findings had actually been routed or risk-accepted.

## Evidence chain
- `drupal-langgraph/src/Service/ProcessFlowRegistryService.php` defines `agentic_sdlc` with a `Code Review` node owned by `agent-code-review`
- `runbooks/feature-intake.md` shows `agentic_sdlc` is launched only from approved delivery handoff in `feature_request_intake`
- `scripts/release-cycle-start.sh` queues release pre-ship code review as a plain inbox item with no flow metadata
- `scripts/route-gate-transitions.sh` and `scripts/route-flow-transitions.py` only advance items that include `Flow id` / `Flow node`
- The release-y code review outbox existed, but no matching flow-managed PM handoff was created from it
- `orchestrator/dispatch.py` and `scripts/ceo-pipeline-remediate.py` were willing to create signoff prompts without checking whether Gate 1b routing had occurred

## Root cause
1. The org has two distinct code-review concepts:
   - delivery-flow code review inside `agentic_sdlc`
   - release Gate 1b pre-ship code review created by release-cycle automation
2. The release Gate 1b path is still a legacy artifact-only process, not a LangGraph-managed flow.
3. PM routing of MEDIUM+ findings was documented in runbooks, but not enforced in automation.
4. Signoff automation assumed "review completed" was enough and did not block on unresolved findings.

## Fixes applied
- Added `scripts/lib/code_review_gate.py` and `scripts/check-code-review-routing.py` to detect unresolved MEDIUM+ release-review findings
- Updated `scripts/release-signoff.sh` to block PM signoff while Gate 1b findings remain unresolved
- Updated `orchestrator/dispatch.py` so proactive awaiting-signoff prompts are replaced by `code-review-followup` items when Gate 1b is still open
- Updated `scripts/ceo-pipeline-remediate.py` so CEO remediation creates `code-review-followup` instead of signoff-reminder when release review findings were never routed
- Added regression tests covering the new follow-up/guard behavior

## Current live state
- `20260412-dungeoncrawler-release-y` still has unresolved Gate 1b code-review follow-up; `python3 scripts/check-code-review-routing.py 20260412-dungeoncrawler-release-y` now blocks signoff correctly
- `20260412-forseti-release-v` reports no unresolved MEDIUM+ release-review findings and can proceed once PM artifact truth is restored

## Next actions
- Route the remaining release-y code-review findings through PM -> Dev or explicit PM risk acceptance
- Keep release Gate 1b outside signoff until those artifacts exist
- Consider a future LangGraph enhancement if the org wants release Gate 1b itself represented as a first-class flow rather than a guarded legacy gate

## ROI estimate
- ROI: 96
- Rationale: This closes the actual process hole, not just the visible symptom. It prevents future releases from skipping from "code review finished" to "signoff pending" when no PM routing ever occurred.

---
- Agent: ceo-copilot-2
- Source: live RCA of pending release code-review findings / langgraph mismatch
- Generated: 2026-04-29T12:19:03+00:00
