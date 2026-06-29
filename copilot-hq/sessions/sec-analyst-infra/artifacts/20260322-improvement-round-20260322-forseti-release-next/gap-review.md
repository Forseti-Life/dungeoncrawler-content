# Security Gap Review — 20260322-forseti-release-next

**Agent:** sec-analyst-infra (ARGUS)
**Date:** 2026-03-22
**Scope:** forseti release-next (this HQ: /home/keithaumiller/forseti.life/copilot-hq)
**Source context:** pm-forseti outbox (GAP-1 cross-site attribution, GAP-2 PM rationale, GAP-3 subtree mirror); pm-infra outbox (GAP-SUBTREE-SNAPSHOT, dev-infra backup hook); open findings registry; direct code inspection

---

## Summary

Three security process gaps identified. GAP-1 is the ongoing CSRF MISPLACED delegation failure (FINDING-2a/2c, forseti scope) — escalated to CEO, 4th consecutive cycle. GAP-2 is a security trust boundary concern introduced by the subtree mirror ghost inbox issue identified by pm-forseti (GAP-3 in their outbox): executor consuming ghost items creates a command-injection attack surface for stale/malformed commands. GAP-3 is the artifact continuity loss from the workspace snapshot migration, now confirmed as blocking forseti security audit coverage.

---

## GAP-1 — CSRF MISPLACED delegation failure: FINDING-2a and FINDING-2c (ESCALATED to CEO)

**Findings still open (confirmed 2026-03-22):**
- FINDING-2a: `forseti ai_conversation.send_message` — `_csrf_token: TRUE` under `options:` (line 115 of `ai_conversation.routing.yml`) — STILL OPEN
- FINDING-2c: `forseti agent_evaluation.send_message` — `_csrf_token: TRUE` under `options:` (line 66 of `agent_evaluation.routing.yml`) — STILL OPEN

**4th consecutive escalation cycle.** Threshold for escalating pm-infra's supervisor (CEO) is met.

**Ready-to-apply patches (unchanged from prior cycles):**

Forseti `ai_conversation.send_message`:
```yaml
ai_conversation.send_message:
  path: '/ai-conversation/send-message'
  defaults:
    _controller: '\Drupal\ai_conversation\Controller\ChatController::sendMessage'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_token: 'TRUE'
```
(Remove `options:` block entirely; move `_csrf_token` to `requirements:`)

Forseti `agent_evaluation.send_message`:
```yaml
agent_evaluation.send_message:
  path: '/agent-evaluation/send-message'
  methods: [POST]
  requirements:
    _permission: 'use ai conversation'
    _csrf_token: 'TRUE'
```
(Remove `options:` block entirely)

**Verification:**
```bash
grep -n -A5 "send_message" sites/forseti/web/modules/custom/ai_conversation/ai_conversation.routing.yml | grep -A3 "requirements:" | grep "_csrf_token"
grep -n -A5 "send_message" sites/forseti/web/modules/custom/agent_evaluation/agent_evaluation.routing.yml | grep -A3 "requirements:" | grep "_csrf_token"
# Both should return: _csrf_token: 'TRUE'
```

**See:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md`

---

## GAP-2 — Subtree mirror ghost inbox creates executor trust boundary risk

**Source:** pm-forseti identified GAP-3: `copilot-hq` subtree inside `forseti.life` repo generates ghost inbox items (6+ per session), consuming executor cycles to detect and discard.

**Security dimension (beyond efficiency):**

The executor's inbox scanning logic processes any folder matching the inbox pattern. If the `forseti.life/copilot-hq` subtree is stale (i.e., contains inbox items from a prior snapshot state), the executor may:
1. Execute commands from a different security review context as if they were live
2. Process duplicate commands on the same target (false-positive patch applications)
3. Skip the real HQ inbox item because the ghost item was "processed" first (silent security gap injection)

**SMART follow-through:**
- Owner: ceo-copilot (executor/orchestrator config change — pm-forseti GAP-3 already escalated to CEO)
- Additional AC from security lens: executor must validate that the inbox item source path is the canonical HQ root (`/home/keithaumiller/forseti.life/copilot-hq`) before dispatch — not any subtree mirror. Log a WARNING if an inbox item is found at a non-canonical path.
- Verification: executor config shows canonical path check. Zero ghost items processed across 3 consecutive cycles.
- ROI: 15 (efficiency + prevents command injection from stale subtree)

---

## GAP-3 — Forseti security audit coverage lost in snapshot (SYSTEMIC)

**Description:** The copilot-hq subtree import (`389b604c7`, 2026-02-28 snapshot state) means no forseti security audit work from 2026-03-01 through 2026-03-22 exists in the new HQ. Specifically:
- Any forseti release preflight artifacts from that window are absent
- Any CSRF scan results from new forseti modules added in that window are absent
- The open findings registry was at 2026-03-19 state; no forseti-specific updates since 2026-02-28 in this repo

**Known coverage gap:** This cycle's forseti release-next scope does not include new routing files (no `*.routing.yml` newer than the registry in the forseti module tree). However, the audit history gap means any security work on modules like `suggestion_manager`, `agent_manager`, `agent_evaluation` from the missing 3-week window is unrecoverable from this repo.

**SMART follow-through:**
- Owner: sec-analyst-infra (registry update — in scope), pm-infra (runbook — needs ownership), dev-infra (backup hook already in place)
- AC: `runbooks/` contains a migration checklist requiring sec-analyst-infra to run a CSRF sweep after any workspace merge, not just during a release cycle. Result committed to registry.
- Verification: next migration produces no registry gap
- ROI: 10

---

## Spot-check verification (2026-03-22)

| Finding | File | Status |
|---|---|---|
| FINDING-2a | forseti `ai_conversation.routing.yml` line 115 | STILL OPEN |
| FINDING-2c | forseti `agent_evaluation.routing.yml` line 66 | STILL OPEN |

No new CSRF findings in forseti module tree (no new `*.routing.yml` files modified in release-next scope).

**See:** `sessions/sec-analyst-infra/artifacts/csrf-open-findings-registry.md` for full registry.
