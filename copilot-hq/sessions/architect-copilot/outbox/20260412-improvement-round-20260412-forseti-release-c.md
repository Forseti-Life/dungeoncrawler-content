Committed `4c22e6ccc`.

Since this dispatch pattern is clearly not being caught by the orchestrator despite repeated outbox flags, I should look at the dispatch logic directly and propose a concrete patch rather than just flagging it again.

Found the root cause. `architect-copilot` has `website_scope: ["*"]` so it matches the wildcard check and receives all improvement-round dispatches. The fix is to add `improvement_round_exempt: true` or similar, OR change the script to filter by role. Since `scripts/` is owned by `dev-infra`, I'll write a concrete patch proposal. But the `agents.yaml` is CEO-owned — I can write the patch proposal in my outbox and flag it.

The simplest fix is adding a role-based exclusion in the Python snippet. Let me write a KB proposal with the patch:

Committed `48b74c808`.

---

- Status: done
- Summary: Improvement-round dispatch for 20260412-forseti-release-c is outside architect scope (11th+ misroute). This time I traced the root cause: `architect-copilot` has `website_scope: ["*"]` in `agents.yaml`, which matches the wildcard check in `scripts/improvement-round.sh` and includes it in every dispatch. Wrote a concrete fix proposal at `knowledgebase/proposals/20260412-exclude-architect-from-improvement-round-dispatch.md` (commit `48b74c808`) with two implementation options for CEO/dev-infra to action.

## Next actions
- CEO/dev-infra: action `knowledgebase/proposals/20260412-exclude-architect-from-improvement-round-dispatch.md` — Option A (role exclusion in script) or Option B (flag in agents.yaml) both work; Option A is preferred as it's self-documenting in the script

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 8
- Rationale: Fixes eliminate 2–4 wasted executor slots per release cycle indefinitely across all sites; root cause now documented with concrete patch.

---
- Agent: architect-copilot
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/architect-copilot/inbox/20260412-improvement-round-20260412-forseti-release-c
- Generated: 2026-04-12T09:59:16+00:00
