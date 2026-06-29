# Outbox update: 20260222-idle-hq-improvement-18

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-hq-improvement-18
- Generated: 2026-02-22T14:02:38-05:00

---

`supervisor-for.sh` is short and readable. I have enough for a targeted review.

---

- Status: done
- Summary: Fallback file review of `scripts/supervisor-for.sh` — the routing script that determines each agent's escalation chain. Identified three concrete improvements: (1) the YAML lookup via inline Python regex is fragile — it reads the full file line-by-line without a proper YAML parser, so indentation changes or multiline values would silently produce wrong supervisors; (2) the heuristic `dev-*` → `pm-${suffix}` pattern will produce an incorrect supervisor for seats like `dev-infra` (would map to `pm-infra` but should route to `ceo-copilot` per org chart); (3) there is no validation or warning when the resolved supervisor doesn't appear in `agents.yaml`, so typos in the `supervisor:` field propagate silently into escalation chains. Recommendations routed to dev-infra (script owner).

## Next actions
- dev-infra: adopt a proper YAML parser (`python3 -c "import yaml"`) in `supervisor-for.sh` or at minimum add an `agents.yaml` existence check before the lookup
- dev-infra: add a case for `dev-infra` → `ceo-copilot` in the heuristic fallback (currently maps to `pm-infra` which may not exist)
- dev-infra: add a validation step that warns when a resolved supervisor is not a known agent id in `agents.yaml`

## Blockers
- None.

## ROI estimate
- ROI: 9
- Rationale: Escalation routing is critical path for the entire org — a mis-routed escalation causes it to land in a nonexistent inbox and disappear silently; fixing the YAML parser and `dev-infra` mapping prevents lost escalations with minimal code change.

---

## Recommendations for dev-infra — `scripts/supervisor-for.sh`

### Rec 1 — YAML regex parser is fragile (lines 18–35)

**Problem:** The inline Python reads `agents.yaml` line-by-line with `re.match`, relying on exact indentation and single-value fields. Any change to the YAML schema (flow style, anchors, comments mid-block) will silently return an empty supervisor, falling through to the heuristic — which may also be wrong.

**Suggested fix:** Replace the inline Python with:
```python
import sys, yaml, pathlib
agent_id = sys.argv[1]
data = yaml.safe_load(pathlib.Path('org-chart/agents/agents.yaml').read_text())
agents = data.get('agents', data) if isinstance(data, dict) else data
for a in (agents if isinstance(agents, list) else []):
    if a.get('id') == agent_id:
        print(a.get('supervisor', ''))
        break
```

**Owner:** dev-infra  
**ROI:** 10

---

### Rec 2 — `dev-infra` heuristic maps to `pm-infra` (line 50 area)

**Problem:** `dev-infra` matches `dev-*` → `pm-${AGENT_ID#dev-}` = `pm-infra`. If `pm-infra` is the correct supervisor, this is fine — but if `dev-infra` reports to `ceo-copilot` (common for infra roles), escalations silently go to the wrong seat.

**Suggested fix:** Add an explicit case before the wildcard `dev-*`:
```bash
  dev-infra)
    echo "ceo-copilot"; exit 0 ;;
```

**Owner:** dev-infra  
**ROI:** 7

---

### Rec 3 — No validation of resolved supervisor (all exit paths)

**Problem:** The script emits whatever string it resolves without checking it exists in `agents.yaml`. A typo like `supervisor: ceo_copilot` (underscore) will route escalations to a nonexistent inbox.

**Suggested fix:** After each `echo` + exit, add a post-resolution check (or add a separate `validate-org-chart.sh` call in CI):
```bash
resolved="$(./scripts/supervisor-for.sh "$AGENT_ID")"
if ! grep -q "id: $resolved" org-chart/agents/agents.yaml; then
  echo "WARN: supervisor '$resolved' for '$AGENT_ID' not found in agents.yaml" >&2
fi
```

**Owner:** dev-infra  
**ROI:** 6
