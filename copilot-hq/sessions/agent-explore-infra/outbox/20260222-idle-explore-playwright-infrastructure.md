# Outbox update: 20260222-idle-explore-playwright-infrastructure

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-explore-playwright-infrastructure
- Generated: 2026-02-22T19:51:05-05:00

---

- Status: needs-info
- Summary: This inbox item requests a Playwright-driven UX exploration of the infrastructure website scope, but the infrastructure scope has no web UI — it is an HQ operational system consisting of bash scripts, log files, session directories, and runbooks. Playwright requires a browser-accessible URL to navigate; there is no such URL for infrastructure. The suggested module dir and random file fields are both "(not found / inaccessible)" — the generator correctly detected no web surface. This is the same root-cause issue documented in idle-explore cycles 6–12: the `idle-explore-playwright` command template was not updated when the infrastructure scope was confirmed to be operator-audit rather than web-UX. This item cannot be executed as specified.

## Next actions
- Executor: route this findings summary to pm-infra to confirm the correct idle-explore format for infrastructure scope
- Executor: update `idle-work-generator.sh` to skip Playwright-style commands for seats whose `website_scope` is `infrastructure` (or any scope without a live URL) — this is the same fix recommended in cycle 3 and cycle 6

## Blockers
- No browser-accessible URL exists for the infrastructure scope; Playwright cannot be run

## Needs from Supervisor

- **Product context:** website=infrastructure, module=HQ operational scripts/runbooks, role=agent-explore-infra, feature=idle Playwright exploration
- **Specific gap:** The `idle-explore-playwright-infrastructure` command assumes a web UI exists; infrastructure has no browser-accessible surface
- **What I need:** Confirmation from pm-infra of the correct idle-explore format for infrastructure. Options:
  1. Operator audit (shell commands, log review, script review) — this is what cycles 13 and 16–20 used successfully
  2. Skip Playwright cycles entirely for infrastructure and only generate refactor-review cycles
  3. A live URL for an infrastructure-related web surface (if one exists that I'm not aware of)

## Decision needed
- Should infrastructure idle-explore cycles use operator audit (bash/file review) or be skipped entirely? The `idle-explore-playwright` template is not applicable to this scope.

## Recommendation
- Use operator audit as the infrastructure idle-explore format (same as cycles 13, 16–20). The `idle-work-generator.sh` template for infrastructure should generate operator audit commands, not Playwright commands. This requires a 3-line change to the generator to branch on `website_scope == infrastructure`. I can produce this diff if pm-infra confirms.

## ROI estimate
- ROI: 7
- Rationale: This template mismatch will generate the same blocked/needs-info response on every future Playwright cycle for infrastructure; fixing the generator now prevents recurring busywork and escalation noise; the operator audit approach has already demonstrated ROI 8–10 findings across 20 cycles.
