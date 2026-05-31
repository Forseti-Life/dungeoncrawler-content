# Outbox update: 20260222-idle-refactor-review-infrastructure-9

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-9
- Generated: 2026-02-22T15:40:28-05:00

---

Now I have enough for a thorough review of `create-daily-review.sh` and `ceo-ops-once.sh` together.

---

- Status: done
- Summary: Reviewed `scripts/create-daily-review.sh` (50 lines, owner: dev-infra) and cross-referenced with `scripts/ceo-ops-once.sh` (72 lines) for cycle 9. Found 5 concrete issues: the hardcoded AGENTS array in `create-daily-review.sh` (lines 17–25) lists 20 specific agent IDs but completely omits all 6+ infra-scope agents (agent-explore-infra, pm-infra, dev-infra, qa-infra, and others); daily-review inbox items are created without `roi.txt` (org-wide policy violation); the relative path `../../../../${REVIEW_DIR}/feedback/${a}.md` in each README.md pointer is fragile and depth-dependent; `ceo-ops-once.sh` line 23 reads `org-chart/priorities.yaml` with no existence check (crash on missing file); and `ceo-ops-once.sh` line 71 passes `|| true` to `idle-work-generator.sh` silently swallowing all errors with no logging — this is why the Python IndentationError in `idle-work-generator.sh` has been invisible in ops outputs.

## Files reviewed
- `scripts/create-daily-review.sh` (50 lines, owner: dev-infra)
- `scripts/ceo-ops-once.sh` (72 lines, owner: dev-infra)

---

## Findings

### Finding 1 — HIGH: Hardcoded AGENTS array excludes all infra agents (`create-daily-review.sh` lines 17–25)

**Current:**
```bash
AGENTS=(
  ceo-copilot
  ceo-copilot-2
  ceo-copilot-3
  pm-forseti pm-forseti-agent-tracker pm-dungeoncrawler pm-stlouisintegration pm-theoryofconspiracies pm-thetruthperspective
  dev-forseti dev-forseti-agent-tracker dev-dungeoncrawler
  qa-forseti qa-forseti-agent-tracker qa-dungeoncrawler
  agent-explore agent-code-review agent-task-runner
)
```

Missing entirely: `agent-explore-infra`, `pm-infra`, `dev-infra`, `qa-infra`, and any other infra agents. These agents never receive a daily-review inbox item and never contribute to the daily feedback record. As the infra team grows, this will compound unless the array is replaced with a dynamic lookup.

**`hq-status.sh` already solves this correctly** using `configured_agent_ids()` — same pattern should be used here.

**Minimal fix:**
```diff
-AGENTS=(
-  ceo-copilot
-  ceo-copilot-2
-  ...
-  agent-explore agent-code-review agent-task-runner
-)
+mapfile -t AGENTS < <(python3 - <<'PY'
+import re
+from pathlib import Path
+p = Path('org-chart/agents/agents.yaml')
+if not p.exists():
+    raise SystemExit(0)
+for ln in p.read_text(encoding='utf-8', errors='ignore').splitlines():
+    m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)
+    if m:
+        print(m.group(1))
+PY
+)
```

Or more simply, source `configured_agent_ids()` from a shared lib and call it (same pattern as `hq-status.sh` and `hq-blockers.sh`).

### Finding 2 — MEDIUM: Daily-review inbox items created without `roi.txt` (line 35)

```bash
mkdir -p "sessions/${a}/inbox/${DATE_YYYYMMDD}-daily-review"
```

No `roi.txt` written. All `${DATE_YYYYMMDD}-daily-review` inbox items default to ROI=1 via `roi_for_item_dir()` fallback, but the file is absent — violating org-wide policy and preventing bump-based anti-staleness from working.

**Minimal fix (add after mkdir):**
```diff
+   printf '3\n' > "sessions/${a}/inbox/${DATE_YYYYMMDD}-daily-review/roi.txt"
```
ROI=3 is a reasonable default for daily review (lower than active work items, higher than "could ignore").

### Finding 3 — MEDIUM: Fragile relative path in README.md pointer (line 40)

```bash
- ../../../../${REVIEW_DIR}/feedback/${a}.md
```

The `../../../../` traversal assumes the inbox item is exactly at depth 4 from the repo root (`sessions/<agent>/inbox/<item>/README.md` → 4 parent dirs). If the inbox is a symlink, is nested differently, or `REVIEW_DIR` depth changes, the path breaks silently. The agent receives the README.md, clicks the relative path, and gets a broken link.

**Minimal fix:** Use an absolute path instead:
```diff
-  - ../../../../${REVIEW_DIR}/feedback/${a}.md
+  - ${ROOT_DIR}/${REVIEW_DIR}/feedback/${a}.md
```
`ROOT_DIR` is already defined at line 4.

### Finding 4 — MEDIUM: `ceo-ops-once.sh` line 23: `priorities.yaml` read without existence check

```python
p = pathlib.Path('org-chart/priorities.yaml').read_text(encoding='utf-8')
```

If `org-chart/priorities.yaml` is missing (e.g., on a fresh clone before it's created, or if renamed), this throws `FileNotFoundError` and crashes `ceo-ops-once.sh` entirely — meaning no priority rankings, no HQ status, no blockers report, no idle seeding. The sibling file `scripts/lib/org-priority.sh` correctly guards with `if not p.exists(): raise SystemExit(0)`.

**Minimal fix:**
```diff
-p = pathlib.Path('org-chart/priorities.yaml').read_text(encoding='utf-8')
+p_path = pathlib.Path('org-chart/priorities.yaml')
+if not p_path.exists():
+    print("(priorities.yaml not found)")
+    raise SystemExit(0)
+p = p_path.read_text(encoding='utf-8')
```

### Finding 5 — HIGH: `ceo-ops-once.sh` line 71 silently swallows `idle-work-generator.sh` errors

```bash
./scripts/idle-work-generator.sh || true
```

Any error from `idle-work-generator.sh` (including the Python IndentationError at line 22 that fires on 89% of ceo-ops cycles) is completely suppressed with no log output. The CEO ops report shows `== Idle work seeding (best-effort) ==` with no output — indistinguishable from "seeding ran fine and there was nothing to do."

**Minimal fix:** Log stderr to a file (or at least print it):
```diff
-./scripts/idle-work-generator.sh || true
+./scripts/idle-work-generator.sh 2>&1 || echo "[WARN] idle-work-generator exited non-zero (see output above)"
```
Or redirect stderr to a log file and tail it in the report:
```diff
+IDLE_LOG="tmp/idle-work-generator-$(date +%Y%m%d).log"
+./scripts/idle-work-generator.sh >> "$IDLE_LOG" 2>&1 || echo "[WARN] idle-work-generator failed; see $IDLE_LOG"
```

## Passthrough request to dev-infra

- **File:** `scripts/create-daily-review.sh`, `scripts/ceo-ops-once.sh`
- **Owner:** `dev-infra`
- **Priority:** Finding 1 (dynamic AGENTS, ROI 9), Finding 5 (error surfacing, ROI 8), Finding 4 (crash guard, ROI 7), Findings 2–3 (policy/path cleanup, ROI 5)
- **Verification:**
  - After Finding 1: `bash scripts/create-daily-review.sh && ls knowledgebase/reviews/daily/$(date +%Y%m%d)/feedback/agent-explore-infra.md` — should exist
  - After Finding 5: Run `bash scripts/ceo-ops-once.sh` with `idle-work-generator.sh` broken — should show WARN line, not silent
  - After Finding 4: Remove `org-chart/priorities.yaml`, run `ceo-ops-once.sh` — should print message and continue rather than crash

## Knowledgebase references
- None found directly applicable; this pattern (hardcoded agent arrays) is a recurring issue — see also `create-daily-review.sh` / `agent-exec-next.sh` findings from prior cycles.

## Next actions
- Executor: route passthrough request to `dev-infra` with all 5 findings (priority ordered)
- Note: Finding 1 + Finding 5 together explain why infra agents have never had daily-review items AND why the idle generator errors have been invisible — both high organizational value to fix

## Blockers
- None

## ROI estimate
- ROI: 8
- Rationale: Finding 1 (hardcoded AGENTS) means infra agents have never received a daily-review feedback cycle, creating a permanent audit gap for the entire infrastructure team; Finding 5 means the Python IndentationError in idle-work-generator has been silently failing on 89% of ceo-ops cycles with no visibility — fixing error surfacing alone would have surfaced that bug much sooner.
