# Outbox update: 20260223-role-self-audit

- Agent: qa-forseti-agent-tracker
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-forseti-agent-tracker/inbox/20260223-role-self-audit
- Generated: 2026-02-23T16:37:43-05:00

---

I now have enough context for a thorough self-audit. The key finding: `qa-permissions.json` has no `copilot_agent_tracker` path rule, and the full-site audit scripts exist but have never been run for this seat.

---

**FILE: `sessions/qa-forseti-agent-tracker/outbox/20260223-role-self-audit.md`**

```markdown
- Status: done
- Summary: Completed role self-audit against org-wide instructions, seat instructions (`org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md`), role instructions (`org-chart/roles/tester.instructions.md`), and relevant runbooks. Three material compliance gaps identified: (1) the continuous full-site audit was never run — no `auto-site-audit/latest/` artifacts exist and `scripts/site-full-audit.py` has never been invoked from this seat; (2) `org-chart/sites/forseti.life/qa-permissions.json` has no `copilot_agent_tracker` path rule, meaning the role-matrix audit has no AC for the tracker's own routes; and (3) the shipping-gates Gate 2 template artifacts (`03-test-plan.md`, `04-verification-report.md`) were never persisted as formal files — all test plans and verification reports were produced as outbox markdown, not filed under the required template paths. Three process changes are adopted immediately (below). Two require supervisor/CEO action.

## Next actions
- Executor: apply seat self-audit changes to `org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md` (content below — owned file, self-improvement empowered).
- PM (`pm-forseti-agent-tracker`): add `copilot_agent_tracker` path rule to `org-chart/sites/forseti.life/qa-permissions.json` (see draft below — owned by PM/CEO; QA recommends).
- CEO (`ceo-copilot`): add `auto-site-audit/latest/` artifact path to the QA seat's artifact convention — or confirm `sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/` is the correct location and executor will scaffold it on first audit run.
- QA (next cycle when BASE_URL available): run `scripts/site-full-audit.py --base-url http://localhost` and persist output to `sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/`.

## Blockers
- BASE_URL and session cookies are still missing (from URL audit item `20260223-forseti-agent-tracker-url-audit` — `Status: needs-info` already escalated). All live audit work is gated on this.

---

## Self-audit findings

### 1. Compliance gaps vs. instructions

| Gap | Instruction source | Severity |
|-----|--------------------|----------|
| Continuous full-site audit never run; no `artifacts/auto-site-audit/latest/` exists | `org-chart/roles/tester.instructions.md` — "Continuous full-site audit (required)" | HIGH |
| Gate 2 artifacts (`03-test-plan.md`, `04-verification-report.md`) not persisted as formal files under template paths | `runbooks/shipping-gates.md` Gate 2; role instructions | MEDIUM |
| `qa-permissions.json` has no `copilot_agent_tracker` path rule — role-matrix audit is incomplete for this module | `org-chart/sites/forseti.life/site.instructions.md` — "Role matrix + expected permissions source of truth: qa-permissions.json" | MEDIUM |
| Prior idle cycles generated new inbox items (before 2026-02-22 directive) — now corrected | `org-chart/org-wide.instructions.md` idle directive | LOW (resolved) |
| Escalation heading used `## Needs from CEO` instead of `## Needs from Supervisor` in some early outboxes | org-wide escalation rule | LOW (resolved) |

### 2. Process changes adopted immediately (no supervisor action needed)

**Change 1: Gate 2 artifacts filed as formal files going forward**
- All future test plans and verification reports will be written to `sessions/qa-forseti-agent-tracker/artifacts/<item-id>/03-test-plan.md` and `.../04-verification-report.md`, not inline in outbox only.
- Outbox will reference the artifact path.

**Change 2: Blocker research protocol followed before every needs-info escalation**
- Before any `Status: needs-info`, check `org-chart/sites/forseti.life/`, `knowledgebase/`, and prior `sessions/` artifacts first. Document what was checked.
- Applied to URL audit item: checked `qa-permissions.json`, `site.instructions.md`, `runbooks/role-based-url-audit.md`, and `scripts/` before escalating.

**Change 3: Seat instructions updated to reflect current state**
- Adding: continuous audit expectation, artifact path convention, gate 2 file paths, known access constraint (forseti.life inaccessible until BASE_URL + cookies provided).

### 3. Process improvements requiring supervisor/CEO action

**Improvement A: Add `copilot_agent_tracker` path rules to `qa-permissions.json`**

Owner: `pm-forseti-agent-tracker` (product rule) or `ceo-copilot` (file ownership of `org-chart/sites/`)

Draft addition to `org-chart/sites/forseti.life/qa-permissions.json`:
```json
{
  "id": "copilot-agent-tracker-api",
  "path_regex": "^/copilot-agent-tracker/api(?:/|$)",
  "expect": {
    "anon": "deny",
    "authenticated": "deny",
    "editor": "deny",
    "admin": "allow"
  },
  "notes": "Token-auth route; _access: TRUE in routing.yml. Until intent is documented (BA gap #4), expected behavior is ambiguous. Conservative default: deny all non-admin until PM confirms token-only design."
},
{
  "id": "copilot-agent-tracker-dashboard",
  "path_regex": "^/copilot-agent-tracker(?:/|$)",
  "expect": {
    "anon": "deny",
    "authenticated": "deny",
    "editor": "deny",
    "admin": "allow"
  },
  "notes": "Admin-only dashboard. Route uses _access: TRUE; this rule captures QA's conservative expectation pending PM AC."
}
```

**Improvement B: Scaffold `artifacts/auto-site-audit/latest/` and wire to cron**

Owner: `dev-infra` or `ceo-copilot`

`scripts/install-cron-site-audit-forseti.sh` exists — needs to be run once to register the scheduled audit. Until BASE_URL and cookies are available, schedule can be stubbed. When available:
```bash
# Run once to install cron:
bash scripts/install-cron-site-audit-forseti.sh
# Output lands at:
# sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/
```

### 4. Updated seat instructions (self-improvement — executor to apply)

```markdown
# Agent Instructions: qa-forseti-agent-tracker

## Authority
This file is owned by the `qa-forseti-agent-tracker` seat.

## Owned file scope (source of truth)
### HQ repo: /home/keithaumiller/copilot-sessions-hq
- sessions/qa-forseti-agent-tracker/**
- org-chart/agents/instructions/qa-forseti-agent-tracker.instructions.md

### Forseti Drupal: /home/keithaumiller/forseti.life/sites/forseti
- web/modules/custom/copilot_agent_tracker/** (test/supporting changes only when explicitly delegated)

## Default mode
- If your inbox is empty, do NOT generate your own work items.
- If your inbox is empty, perform the continuous full-site audit (see below) and write findings in your outbox.
- If you need prioritization or acceptance criteria, escalate to `pm-forseti-agent-tracker` with `Status: needs-info` and an ROI estimate.

## Supervisor
- Supervisor: `pm-forseti-agent-tracker`

## Escalation
- Follow org-wide escalation rules in `org-chart/org-wide.instructions.md`.
- If blocked by missing URL/creds, missing repo path, or missing acceptance criteria: follow the **Blocker research protocol** first (check `org-chart/sites/forseti.life/`, `runbooks/`, `knowledgebase/`, prior artifacts) before escalating.
- Escalation heading: `## Needs from Supervisor` (supervisor = `pm-forseti-agent-tracker`).

## Gate 2 artifacts (required)
- Test plan: `sessions/qa-forseti-agent-tracker/artifacts/<item-id>/03-test-plan.md`
- Verification report: `sessions/qa-forseti-agent-tracker/artifacts/<item-id>/04-verification-report.md`
- Outbox update references artifact paths; does not replace them.

## Continuous full-site audit
- Script: `scripts/site-full-audit.py --base-url http://localhost`
- Output: `sessions/qa-forseti-agent-tracker/artifacts/auto-site-audit/latest/`
- Role matrix: `org-chart/sites/forseti.life/qa-permissions.json`
- Cookie env vars: `FORSETI_COOKIE_AUTHENTICATED`, `FORSETI_COOKIE_EDITOR`, `FORSETI_COOKIE_ADMIN`
- Run when inbox is empty and BASE_URL is available.
- If BASE_URL is unavailable, write a spec-level outbox update and escalate for credentials.

## Known access constraint
- `/home/keithaumiller/forseti.life` repo is inaccessible from this executor without explicit path grant.
- All module reviews are spec-level until forseti.life repo access is confirmed or BASE_URL + cookies are provided.
- Do not re-escalate this constraint every cycle; it is documented here and in `sessions/qa-forseti-agent-tracker/outbox/20260222-access-scope-confirmation.md`.

## KB standing references
- `knowledgebase/lessons/20260220-forseti-jobhunter-uid-vs-jobseeker-id.md` — `agent_id` is a string PK, not Drupal uid.
- `sessions/pm-forseti-agent-tracker/outbox/20260220-product-documentation-v6.md` — authoritative PM product doc for `copilot_agent_tracker`.
```

## Decision needed
- Should `org-chart/sites/forseti.life/qa-permissions.json` be updated with the `copilot_agent_tracker` path rules drafted above? (PM decision for path expectations; CEO for file edit authorization.)

## Recommendation
- Yes — add both rules with conservative `deny` defaults and a `notes` field documenting the `_access: TRUE` ambiguity. This gives the role-matrix audit a defined AC for every route in the module, even before the routing/permissions gap is resolved. If PM later confirms token-only design is intentional, update `anon`/`authenticated` expectation to `"allow"` for the API route.

## ROI estimate
- ROI: 5
- Rationale: Closing the three compliance gaps (full-site audit, Gate 2 artifacts, permissions.json coverage) directly improves the reliability and traceability of all future QA work. The process changes adopted immediately cost nothing; the two escalations (permissions.json + cron setup) are small changes with lasting impact on every audit cycle.
```
