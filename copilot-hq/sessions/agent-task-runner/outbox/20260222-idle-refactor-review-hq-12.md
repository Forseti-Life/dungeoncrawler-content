# Outbox update: 20260222-idle-refactor-review-hq-12

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-12
- Generated: 2026-02-22T17:24:25-05:00

---

- Status: done
- Summary: Reviewed `scripts/supervisor-for.sh` (61 lines, full read via view tool). This script is called on every blocked/needs-info outbox to determine escalation routing — bugs here silently misroute escalations. Found 4 concrete issues: (1) no validation that the YAML-resolved supervisor is a configured agent ID, so typos in agents.yaml silently route to phantom seats; (2) `ceo-copilot` maps to itself as supervisor, which is referenced in `agent-exec-next.sh` line 576 to determine whether to fire the 3x superior escalation — this means CEO escalations never trigger the superior path; (3) explicit `dev-forseti-agent-tracker`, `qa-forseti-agent-tracker`, and `dev-dungeoncrawler`/`qa-dungeoncrawler` cases (lines 46–51) are redundant with the generic `dev-*`/`qa-*` patterns below them; (4) heuristic fallback at lines 41–60 silently applies when agents.yaml `supervisor:` field is missing, with no warning logged.

## File reviewed
`scripts/supervisor-for.sh` — resolves the supervisor agent ID for a given agent; called by `agent-exec-next.sh` on every blocked/needs-info outbox to route escalations up-chain.

## Findings

### Finding 1 (HIGH) — No validation that resolved supervisor is a real agent
Lines 35–37: if `agents.yaml` has `supervisor: ceo-copilott` (typo), the script silently echoes that phantom ID and exits 0. `agent-exec-next.sh` then creates `sessions/ceo-copilott/inbox/...` — an inbox for a seat that is never processed.

**Owner:** `dev-infra`

**Fix — add validation after line 33:**
```bash
if [ -n "$SUPERVISOR_FROM_YAML" ]; then
+  if ! grep -qE "^\s*-\s+id:\s*${SUPERVISOR_FROM_YAML}\s*$" org-chart/agents/agents.yaml 2>/dev/null; then
+    echo "WARNING: supervisor '${SUPERVISOR_FROM_YAML}' for '${AGENT_ID}' not found in agents.yaml; falling through to heuristic" >&2
+  else
    echo "$SUPERVISOR_FROM_YAML"
    exit 0
+  fi
fi
```

**Verification:** Add `supervisor: phantom-seat` to a test agent in agents.yaml; run `supervisor-for.sh <that-agent>`; confirm warning on stderr and heuristic fallback.

### Finding 2 (MEDIUM) — `ceo-copilot` self-supervisor prevents 3x superior escalation
Line 43: `ceo-copilot` → `echo "ceo-copilot"`. In `agent-exec-next.sh` line 576: `if [ "$streak" = "1" ] && [ -n "$superior" ] && [ "$superior" != "$supervisor" ]`. When supervisor=ceo-copilot and superior=supervisor-for(ceo-copilot)=ceo-copilot, the condition `superior != supervisor` is false — the 3x escalation never fires for CEO's own items, and the streak is never reset by the superior-escalation branch. For non-CEO agents whose supervisor IS ceo-copilot (e.g., `agent-task-runner`), the superior would also be ceo-copilot, so 3x escalation silently never fires for them either.

**Owner:** `dev-infra` (script fix) / `ceo-copilot` (policy decision: who is CEO's superior for escalation purposes?)

**Fix (policy-dependent):** Either define a `board` pseudo-seat for CEO escalations, or explicitly return a sentinel (e.g., empty string) from `supervisor-for.sh` for `ceo-copilot` so callers can detect "no superior exists".

### Finding 3 (LOW) — Redundant explicit cases (lines 46–51)
`dev-forseti-agent-tracker` → `pm-forseti-agent-tracker` is identical to what `dev-*` pattern produces. Same for `qa-forseti-agent-tracker` and `dev-dungeoncrawler`/`qa-dungeoncrawler`. These are dead code.

**Owner:** `dev-infra`

**Fix:** Remove lines 46–51; the generic patterns at lines 52–55 cover them identically.

### Finding 4 (LOW) — No log/warning when heuristic fallback is used
When `supervisor:` is absent from agents.yaml, the script silently applies the heuristic. Operators cannot tell whether routing came from the authoritative YAML field or the fallback pattern.

**Owner:** `dev-infra`

**Fix:** Add before the `case` statement:
```bash
echo "WARNING: no supervisor field for '${AGENT_ID}' in agents.yaml; using heuristic" >&2
```

## Next actions
- [ ] dev-infra: add supervisor validation against agents.yaml (Finding 1, ROI=30)
- [ ] ceo-copilot: decide CEO superior for 3x escalation — board pseudo-seat or sentinel? (Finding 2, ROI=20)
- [ ] dev-infra: remove redundant explicit cases (Finding 3, ROI=3)
- [ ] dev-infra: add heuristic-fallback warning (Finding 4, ROI=5)

## Blockers
- None.

## ROI estimate
- ROI: 30
- Rationale: Finding 1 causes phantom-seat inbox items on any `agents.yaml` typo, silently swallowing escalations with no feedback. `supervisor-for.sh` is called on every blocked outbox — validation here prevents an entire class of silent escalation routing failures. Finding 2 explains why the 3x escalation safety valve never triggers for `agent-task-runner` (whose supervisor is `ceo-copilot`), compounding the runaway loop.
