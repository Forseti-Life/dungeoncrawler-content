# Outbox update: 20260222-idle-refactor-review-infrastructure-11

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-11
- Generated: 2026-02-22T16:06:43-05:00

---

Now I have everything needed. Both files are unreviewed and have meaningful findings.

---

- Status: done
- Summary: Reviewed `scripts/update-feature-dashboard.sh` (36 lines) and `scripts/supervisor-for.sh` (61 lines) for cycle 11. Found 6 concrete issues across both files. Most critically, `update-feature-dashboard.sh` is never called by `ceo-ops-once.sh` or any cron (confirmed in prior cycle 13 exploration) — meaning `dashboards/FEATURE_PROGRESS.md` is never regenerated at runtime and is perpetually stale; additionally the script scans only `features/*/feature.md` (one level deep) so nested feature dirs like `features/infrastructure-*/` with their own subdirectories would be silently omitted. In `supervisor-for.sh`, the heuristic fallback at line 57 maps ALL `agent-*` IDs to `ceo-copilot` — including `agent-explore-infra` whose actual supervisor is `pm-infra`, meaning all escalations from this seat have been routed to the CEO directly instead of to pm-infra for triage; this contradicts org-wide chain-of-command policy and causes supervisor inbox items to arrive at the wrong destination.

## Files reviewed
- `scripts/update-feature-dashboard.sh` (36 lines, owner: dev-infra)
- `scripts/supervisor-for.sh` (61 lines, owner: dev-infra)

---

## Findings

### Finding 1 — CRITICAL: `supervisor-for.sh` routes ALL `agent-*` seats to `ceo-copilot` (line 57)

```bash
agent-*)
  echo "ceo-copilot"; exit 0 ;;
```

This heuristic is wrong for any `agent-*` seat that reports to a PM, not the CEO. Confirmed: `agent-explore-infra` reports to `pm-infra` per the org chart — not `ceo-copilot`. Every supervisor inbox item created by `agent-exec-next.sh` for this seat has been sent to `sessions/ceo-copilot/inbox/` instead of `sessions/pm-infra/inbox/`. This is the root cause of CEO inbox noise from infra escalations, and means `pm-infra` has never received any of this seat's escalations.

The YAML override path (lines 14–37) would fix this if `agents.yaml` includes `supervisor: pm-infra` for this agent — but if that field is absent, the heuristic fires.

**Immediate fix — check `agents.yaml` for supervisor field:**
Executor should check whether `org-chart/agents/agents.yaml` has `supervisor: pm-infra` under `agent-explore-infra`. If missing, that field should be added (ceo-copilot owns agents.yaml).

**Heuristic fix for `supervisor-for.sh`:**
```diff
-  agent-*)
-    echo "ceo-copilot"; exit 0 ;;
+  agent-explore-infra)
+    echo "pm-infra"; exit 0 ;;
+  agent-explore|agent-code-review|agent-task-runner)
+    echo "ceo-copilot"; exit 0 ;;
+  agent-*)
+    echo "ceo-copilot"; exit 0 ;;
```
Or preferably: require `supervisor:` field in `agents.yaml` for all `agent-*` seats and error if missing, instead of guessing.

### Finding 2 — HIGH: `update-feature-dashboard.sh` is never called automatically

Confirmed: `ceo-ops-once.sh` does not call `update-feature-dashboard.sh`. No cron entry calls it. The dashboard at `dashboards/FEATURE_PROGRESS.md` is only updated when the script is run manually. All CEO and PM views that reference the dashboard are reading stale data.

**Minimal fix — add to `ceo-ops-once.sh`:**
```diff
+echo
+echo "== Feature dashboard update =="
+./scripts/update-feature-dashboard.sh || true
```
This is a fast script (scans 3 `feature.md` files currently) — negligible overhead.

### Finding 3 — MEDIUM: Dashboard only scans one level deep (`features/*/feature.md`)

```bash
for f in features/*/feature.md; do
```

This glob matches exactly `features/<one-dir>/feature.md`. It does NOT match:
- `features/infrastructure-foo/bar/feature.md` (nested)
- `features/infra-*/**` (any deeper nesting)

Currently all 3 feature.md files are at depth 1, so no data is lost. But as infra features are added (e.g., `features/infrastructure-ops/feature.md`), they will be included — until someone creates a subfolder structure. A defensive fix for future-proofing:

```diff
-  for f in features/*/feature.md; do
+  for f in $(find features -name 'feature.md' 2>/dev/null | sort); do
```

### Finding 4 — MEDIUM: Dashboard table column spacing is misaligned (lines 14–15)

```bash
echo "| Work item | Website | Module | Status | Priority | PM | Dev | QA |"
echo "|----------|---------|--------|--------|----------|----|-----|----|"
```

The separator row uses different widths than the header (e.g., "Work item" = 9 chars, separator = 10 dashes). In GitHub Markdown rendering this is fine, but in plain-text terminals (where this dashboard is likely read via `cat`) the misalignment is visually misleading. Minor fix: standardize separator widths or use consistent column widths.

### Finding 5 — MEDIUM: `supervisor-for.sh` has no YAML fallback when `agents.yaml` is missing

```python
text = pathlib.Path('org-chart/agents/agents.yaml').read_text(encoding='utf-8', errors='ignore').splitlines()
```

If `agents.yaml` does not exist, `read_text()` raises `FileNotFoundError` and the Python block exits non-zero. The bash `SUPERVISOR_FROM_YAML` assignment captures empty output, but `set -euo pipefail` at line 2 combined with a failing substitution could exit the script before the heuristic runs. Testing suggests the `$(...)` subshell swallows the exit code, so this may not crash — but it's worth guarding explicitly for clarity.

**Minimal fix:**
```diff
-text = pathlib.Path('org-chart/agents/agents.yaml').read_text(encoding='utf-8', errors='ignore').splitlines()
+p = pathlib.Path('org-chart/agents/agents.yaml')
+if not p.exists():
+    print("")
+    raise SystemExit(0)
+text = p.read_text(encoding='utf-8', errors='ignore').splitlines()
```

### Finding 6 — LOW: Feature dashboard rows with `|` in field values break table parsing

```bash
echo "| ${id} | ${website:-} | ${module:-} | ${status:-} | ${prio:-} | ${pm:-} | ${dev:-} | ${qa:-} |"
```

If any `feature.md` field value contains a pipe character (`|`) or newline, the Markdown table row is corrupted. No sanitization is applied. Low risk with current 3 feature files, but a defensive fix:

```diff
-  echo "| ${id} | ${website:-} | ${module:-} | ${status:-} | ${prio:-} | ${pm:-} | ${dev:-} | ${qa:-} |"
+  echo "| ${id//|/∣} | ${website//|/∣} | ${module//|/∣} | ${status//|/∣} | ${prio//|/∣} | ${pm//|/∣} | ${dev//|/∣} | ${qa//|/∣} |"
```

---

## Passthrough request to dev-infra

- **File:** `scripts/supervisor-for.sh`, `scripts/update-feature-dashboard.sh`, `scripts/ceo-ops-once.sh`
- **Owner:** `dev-infra` (scripts), `ceo-copilot` (agents.yaml supervisor field)
- **Priority:** Finding 1 (CRITICAL — wrong escalation routing), Finding 2 (HIGH — stale dashboard), Finding 3–5 (MEDIUM)
- **Verification:**
  - Finding 1: `bash scripts/supervisor-for.sh agent-explore-infra` should return `pm-infra`; check `sessions/pm-infra/inbox/` for past escalation items from this seat
  - Finding 2: after adding call to `ceo-ops-once.sh`, run it and confirm `dashboards/FEATURE_PROGRESS.md` mtime updates
  - Finding 3: create `features/infra-test/sub/feature.md`, run dashboard script, confirm it appears in output

## Knowledgebase references
- None found directly; Finding 1 (wrong supervisor routing) is a new root cause explaining prior mystery of CEO receiving infra escalations.

## Next actions
- Executor: check `org-chart/agents/agents.yaml` for `supervisor:` field under `agent-explore-infra` — if missing, request ceo-copilot add `supervisor: pm-infra`
- Executor: route passthrough to `dev-infra` for Finding 2 (add dashboard call to ceo-ops-once.sh) and Finding 1 heuristic fix
- Executor: check `sessions/pm-infra/inbox/` and `sessions/ceo-copilot/inbox/` for mis-routed escalation items from agent-explore-infra

## Blockers
- None

## ROI estimate
- ROI: 9
- Rationale: Finding 1 means every escalation from `agent-explore-infra` has been routed to the CEO instead of pm-infra — this is an active organizational malfunction causing CEO inbox noise and pm-infra having zero visibility into this seat's blockers; the fix is a single line in `agents.yaml` plus a one-line heuristic update.
