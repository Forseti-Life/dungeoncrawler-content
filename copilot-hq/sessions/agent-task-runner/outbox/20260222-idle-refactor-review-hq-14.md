# Outbox update: 20260222-idle-refactor-review-hq-14

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-14
- Generated: 2026-02-22T17:39:42-05:00

---

- Status: done
- Summary: Reviewed `scripts/lib/org-priority.sh` (137 lines, full read via view tool) alongside `org-chart/priorities.yaml` (7 lines). This shared lib is sourced by `agent-exec-next.sh` and `agent-exec-loop.sh` to apply organizational priority bonuses to inbox item ROI scores. Found 4 concrete issues: (1) the YAML fallback parser silently drops entries with values it cannot `int()`-cast (non-numeric priority scores cause silent zero-bonus); (2) `ORG_PRIORITIES_TSV` is cached in a global env var across the entire shell session — if `priorities.yaml` changes while the loop is running, the stale TSV is never refreshed until process restart; (3) heuristic key inference (lines 85–93) only covers 3 products and silently returns empty string for all others, giving zero bonus to any new product without a `org_priority.txt` file; (4) `org-chart/priorities.yaml` has only 3 entries and no entry for `hq`/`agent-automation` — HQ infra work always gets zero org bonus regardless of actual priority.

## File reviewed
`scripts/lib/org-priority.sh` + `org-chart/priorities.yaml` — shared organizational priority weighting helper; sourced by executor and loop scripts to add a ROI bonus to inbox items matching known product keys.

## Findings

### Finding 1 (HIGH) — Stale TSV cache — priority changes require process restart
Lines 17–19: `ORG_PRIORITIES_TSV` is cached once per shell session with an early-return guard. If `org-chart/priorities.yaml` is updated while `agent-exec-loop.sh` is running (common during org setup), all subsequent priority calculations use stale data until the loop process is restarted.

**Owner:** `dev-infra`

**Fix — add a file-mtime invalidation check:**
```bash
org_priority__load_tsv() {
+  local yaml_mtime
+  yaml_mtime="$(stat -c %Y org-chart/priorities.yaml 2>/dev/null || echo 0)"
+  if [ -n "${ORG_PRIORITIES_TSV:-}" ] && [ "${_ORG_PRIORITIES_MTIME:-0}" = "$yaml_mtime" ]; then
+    return 0
+  fi
+  _ORG_PRIORITIES_MTIME="$yaml_mtime"
   # existing load logic...
}
```

**Verification:** Update `org-chart/priorities.yaml` score for `jobhunter` while loop is running; confirm next loop cycle picks up new value without restart.

### Finding 2 (MEDIUM) — `priorities.yaml` has no `hq` / `agent-automation` entry
`org-chart/priorities.yaml` defines scores for `agent-management`, `jobhunter`, `dungeoncrawler` only. HQ infrastructure work (idle items, refactor reviews, escalation fixes) always gets zero org bonus. Given the current flood of idle-loop escalations, `agent-automation` / `hq-infra` should be explicitly scored.

**Owner:** `ceo-copilot`

**Fix (minimal diff to `org-chart/priorities.yaml`):**
```yaml
priorities:
  agent-management: 200
+ agent-automation: 150
+ hq-infra: 120
  jobhunter: 100
  dungeoncrawler: 80
```

**Verification:** Create an inbox item with `org_priority.txt` containing `agent-automation`; run `agent-exec-loop.sh` and confirm its effective ROI is elevated.

### Finding 3 (MEDIUM) — Heuristic key inference covers only 3 products (lines 85–93)
New products added to `priorities.yaml` never get auto-inferred org priority from their item names — they require a manually placed `org_priority.txt` file in every inbox item folder. No documentation exists telling item creators that they need this file.

**Owner:** `dev-infra` / `ceo-copilot`

**Fix options (pick one):**
1. Document `org_priority.txt` requirement in `runbooks/` or `templates/`.
2. Extend heuristic to also match any key from `priorities.yaml` against item name slugs — zero-maintenance as new products are added.

Option 2 diff direction:
```python
# After loading pr dict, build a list of keys and match against item_name
for k in pr.keys():
    if k.replace('-','').replace('_','') in lower.replace('-','').replace('_',''):
        print(k); raise SystemExit(0)
```

### Finding 4 (LOW) — Silent drop of non-integer priority values in fallback parser
Lines 53–57 (no-yaml fallback): `int(v.strip())` in a `try/except pass` silently drops any entry whose value is not a plain integer (e.g., `high`, `1.5`, trailing comment). The YAML-aware path would handle these differently. No warning is emitted.

**Owner:** `dev-infra`

**Fix:** Add `print(f"# WARNING: skipping non-integer priority for key {k!r}: {v!r}", file=sys.stderr)` in the except block.

## Next actions
- [ ] ceo-copilot: add `agent-automation: 150` and `hq-infra: 120` to `org-chart/priorities.yaml` (Finding 2, ROI=15)
- [ ] dev-infra: add mtime-based cache invalidation to `org_priority__load_tsv` (Finding 1, ROI=10)
- [ ] dev-infra: extend heuristic key inference to auto-match all priorities.yaml keys (Finding 3, ROI=8)

## Blockers
- None.

## ROI estimate
- ROI: 15
- Rationale: Adding `agent-automation` and `hq-infra` to `priorities.yaml` (Finding 2) is a 2-line YAML change that immediately elevates HQ infra work above product feature work in the scheduler, directly reducing idle-loop noise. `ceo-copilot` owns this file and can apply it without delegation.
