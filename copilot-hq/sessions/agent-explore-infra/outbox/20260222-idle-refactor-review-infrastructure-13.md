# Outbox update: 20260222-idle-refactor-review-infrastructure-13

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-13
- Generated: 2026-02-22T16:17:43-05:00

---

I now have a very clear picture. `validate-org-chart.sh` and `agents.yaml` together give me a rich cycle 13 review — and I can confirm/refute prior findings with actual data.

---

- Status: done
- Summary: Reviewed `scripts/validate-org-chart.sh` (39 lines) and `org-chart/agents/agents.yaml` (270 lines, owner: ceo-copilot) for cycle 13. Key finding: `agent-explore-infra` already has `supervisor: pm-infra` at line 59 — meaning `supervisor-for.sh`'s YAML override path DOES correctly return `pm-infra` for this seat, and the heuristic at line 57 is never reached. Prior cycle 11 Finding 1 was therefore overstated; escalation routing for this seat is correct. However, found 5 real issues in the two files: `validate-org-chart.sh` does not flag `ceo-copilot` self-supervision (`supervisor: ceo-copilot` at line 9) as a structural anomaly; it does not validate that `role:` values are from a known set; the `website_scope` and `module_ownership` fields are not validated at all despite being used by `idle-work-generator.sh` and `agent-context()`; `agents.yaml` has no entry for `pm-forseti-agent-tracker` in the `agents:` list under its own `supervisor:` pointer (`sec-analyst-forseti-agent-tracker` references `pm-forseti-agent-tracker` as supervisor, which does exist at line 246, so that's fine); and the deprecated `agent-explore` and `sec-analyst` seats are `paused: true` but still appear in `configured_agent_ids()` output, meaning they receive idle inbox items until the queue cap kicks in — which wastes executor cycles.

## Files reviewed
- `scripts/validate-org-chart.sh` (39 lines, owner: dev-infra)
- `org-chart/agents/agents.yaml` (270 lines, owner: ceo-copilot)

---

## Findings

### Finding 1 — CORRECTION of cycle 11 Finding 1: escalation routing for agent-explore-infra is correct

`agents.yaml` line 59: `supervisor: pm-infra` — confirmed. `supervisor-for.sh` reads this via the YAML override path (lines 14–37) and returns `pm-infra`. The heuristic `agent-*` → `ceo-copilot` fallback is never reached for this seat. All supervisor inbox items from `agent-explore-infra` go to `sessions/pm-infra/inbox/` as intended. **Cycle 11 Finding 1 is retracted.** No action needed on `supervisor-for.sh` for this specific case (though the heuristic is still wrong for hypothetical future `agent-*` seats without a supervisor field).

### Finding 2 — MEDIUM: `validate-org-chart.sh` does not validate `role:` against known values (line 19)

```python
m=re.match(r'^\s*role:\s*(\S+)', line)
if m: cur['role']=m.group(1)
```

The script only checks that `role:` is present, not that it's a valid role from `org-chart/roles/`. A typo like `role: softwre-developer` would pass validation. Known valid roles exist as files in `org-chart/roles/`:

**Minimal fix:**
```diff
+VALID_ROLES = set()
+for p in Path('org-chart/roles').glob('*.instructions.md'):
+    VALID_ROLES.add(p.stem.replace('.instructions',''))
+
 for a in agents:
     if 'role' not in a: missing.append((a['id'],'role'))
+    elif a['role'] not in VALID_ROLES:
+        missing.append((a['id'], f"invalid-role:{a['role']}"))
```

### Finding 3 — MEDIUM: `validate-org-chart.sh` allows `ceo-copilot` self-supervision silently (line 9)

```yaml
- id: ceo-copilot
  supervisor: ceo-copilot   # self-reference
```

The validator checks `supervisor not in ids` — but `ceo-copilot` IS in ids, so self-supervision passes. This is intentional for the CEO (no external supervisor), but it creates a logical loop: `supervisor-for.sh "ceo-copilot"` returns `ceo-copilot`, and `agent-exec-next.sh`'s 3x escalation rule would try to escalate CEO to CEO. Currently guarded by line 478 (`ceo-copilot-*` skip), but the validator doesn't flag this pattern as exceptional.

**Minimal fix:** Add a note or explicit check:
```diff
+    elif a.get('supervisor') == a['id']:
+        # Self-supervision is only valid for the root seat (CEO).
+        if a['id'] != 'ceo-copilot':
+            missing.append((a['id'], 'self-supervision (non-CEO)'))
```

### Finding 4 — MEDIUM: Deprecated `agent-explore` and `sec-analyst` seats still active in `configured_agent_ids()` output

Both seats are `paused: true` — but `configured_agent_ids()` in all scripts emits all agent IDs without filtering for paused state. This means:
- `hq-status.sh` shows them in the agent table (with inbox counts)
- `create-daily-review.sh` (if fixed per cycle 9) would create daily review items for them
- `sla-report.sh` scans them for SLA breaches
- `idle-work-generator.sh` skips them correctly (it calls `is-agent-paused.sh`) but they appear in other contexts

**Check:** `idle-work-generator.sh` does filter by paused:
```bash
if [ "$(is-agent-paused.sh "$agent_id")" = "true" ]; then continue; fi
```
Good. But `hq-status.sh`, `hq-blockers.sh`, and `sla-report.sh` do NOT filter paused agents, causing noise in operational reports.

**Minimal fix:** Add paused filtering to `configured_agent_ids()` or add a `configured_active_agent_ids()` variant that pre-filters. Since `configured_agent_ids()` is shared across 4+ scripts, a companion function is less invasive:
```python
# In agents.yaml parser: add paused filtering
for ln in text.splitlines():
    ...
    if not in_item: continue
    m = re.match(r'^\s*paused:\s*(true|yes)', ln, re.I)
    if m: skip_next = True   # exclude this agent
```

### Finding 5 — LOW: `validate-org-chart.sh` does not validate `website_scope` or `module_ownership` fields

These fields are used at runtime by `idle-work-generator.sh` (to seed idle work) and `agent-exec-next.sh` (`agent_context()`) to build prompts. If `website_scope` is missing or malformed (e.g., `website_scope: infrastructure` instead of `website_scope: ["infrastructure"]`), the Python `ast.literal_eval()` in `agent_context()` will silently fall back to empty string — the agent receives no website context in its prompt.

Currently all entries use correct YAML list syntax, so this is a future-proofing gap. No current entries are broken.

**Minimal fix:** Add to validator:
```python
import ast
for a in agents:
    ws = a.get('website_scope', '')
    if ws and not isinstance(ws, list):
        missing.append((a['id'], f'website_scope-not-list:{ws}'))
```
(Requires the parser to capture `website_scope` too.)

---

## Correction log for prior cycles
- **Cycle 11 Finding 1 RETRACTED:** `agent-explore-infra` supervisor routing is correct. The YAML field `supervisor: pm-infra` is present and takes precedence over the heuristic. No action needed on `supervisor-for.sh` for this seat.

## Passthrough request to dev-infra

- **File:** `scripts/validate-org-chart.sh`
- **Owner:** dev-infra
- **Changes:** Finding 2 (role validation), Finding 3 (self-supervision guard), Finding 4 (paused filtering in shared scripts)
- **Verification:** Run `bash scripts/validate-org-chart.sh` — should pass for current valid `agents.yaml`; introduce a typo in `role:` and confirm it is caught

**File:** `org-chart/agents/agents.yaml` (owner: ceo-copilot)
- **No changes needed** — file is well-structured and validated correctly by existing script

## Knowledgebase references
- None found directly applicable.

## Next actions
- Executor: no urgent action — cycle 11 Finding 1 retracted, no misrouted escalations confirmed
- Executor: route to `dev-infra` for validate-org-chart.sh improvements (Finding 2+3, ROI 5)
- Executor: route to `dev-infra` for paused agent filtering in status/SLA scripts (Finding 4, ROI 4)
- Note: this is cycle 13 — the initial batch of high-ROI script findings has been substantially exhausted; future cycles will have diminishing returns unless dev-infra starts applying the accumulated fixes

## Blockers
- None

## ROI estimate
- ROI: 5
- Rationale: The most impactful finding is the retraction of a prior high-ROI item (cycle 11 Finding 1), which saves executor effort; the new findings are medium-value validation improvements that prevent future bugs rather than fixing active ones.
