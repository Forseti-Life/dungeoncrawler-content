# Outbox update: 20260222-idle-refactor-review-hq-16

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-16
- Generated: 2026-02-22T17:42:51-05:00

---

- Status: done
- Summary: Reviewed `scripts/is-agent-paused.sh` (31 lines) and `scripts/ownership-audit.sh` (117 lines) via view tool. `is-agent-paused.sh` is called on every loop cycle for every configured agent — a hot path — yet re-reads and re-parses `agents.yaml` from scratch each invocation with no caching. `ownership-audit.sh` has 4 issues: hardcoded absolute paths (`/home/keithaumiller/...`) that break on any other host or user; the `owner_for_hq` default silently assigns `ceo-copilot` to any unknown path instead of flagging it as truly uncovered; `ls -1A` output is truncated at 200 lines with `sed`; and `forseti-life-file-ownership.md` is referenced in the output (line 116) but no check is made that it exists.

## File reviewed
`scripts/is-agent-paused.sh` (31 lines) + `scripts/ownership-audit.sh` (117 lines) — pause-state checker called on every loop cycle per agent; and ownership audit utility for both repos.

## Findings

### Finding 1 (HIGH) — `is-agent-paused.sh` re-parses `agents.yaml` on every call; called O(N) per loop
`agent-exec-loop.sh` calls `./scripts/is-agent-paused.sh "$agent"` for every agent on every loop iteration (lines 131, 222). Each call spawns a new Python process that reads and parses `agents.yaml` from disk. With 15+ configured agents and a 60s loop, this is 15+ Python subprocesses per minute, purely for pause-state lookups that almost never change.

**Owner:** `dev-infra`

**Fix options (pick one):**
1. Inline the pause check into the existing `configured_agents_tsv()` call in `idle-work-generator.sh` / `configured_agent_ids()` loop — already has `paused` field in TSV output.
2. Add a lightweight cache: write pause states to `tmp/.agent-paused-cache.tsv` after each `agents.yaml` mtime change; `is-agent-paused.sh` reads the cache file instead of re-parsing YAML.

**Verification:** Time `for i in $(seq 1 20); do ./scripts/is-agent-paused.sh agent-task-runner; done` before and after; confirm >50% reduction.

### Finding 2 (HIGH) — `ownership-audit.sh` hardcodes absolute paths (lines 7–8)
```bash
HQ_ROOT="/home/keithaumiller/copilot-sessions-hq"
FORS_ROOT="/home/keithaumiller/forseti.life"
```
Script breaks silently on any other user, host, or deployment path. The HQ root is already computable from `${BASH_SOURCE[0]}` (as all other scripts do); forseti path should use an env var with a documented default.

**Owner:** `dev-infra`

**Fix:**
```bash
-HQ_ROOT="/home/keithaumiller/copilot-sessions-hq"
-FORS_ROOT="/home/keithaumiller/forseti.life"
+HQ_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
+FORS_ROOT="${FORSETI_ROOT:-"$(cd "$HQ_ROOT/../forseti.life" && pwd 2>/dev/null || echo '')"}"
```

### Finding 3 (MEDIUM) — `owner_for_hq` default silently assigns `ceo-copilot` to unknown paths (line 57–58)
The function returns `"ceo-copilot"` for any path not matching a known prefix, and the main loop treats any non-empty owner as "covered" (lines 84–94). Genuinely uncovered paths are never surfaced. The audit is supposed to find gaps but masks them.

**Owner:** `dev-infra`

**Fix:** Return an empty string (or `"UNCOVERED"`) for the default case so `uncovered.append(p)` fires:
```python
-  return "ceo-copilot"
+  return ""  # genuinely uncovered — no ownership rule matched
```

### Finding 4 (LOW) — `forseti-life-file-ownership.md` referenced but not checked
Line 116 prints `org-chart/ownership/forseti-life-file-ownership.md` as a reference. If the file doesn't exist, operators get a dangling reference with no warning.

**Owner:** `ceo-copilot`

**Fix:** Add after line 116:
```bash
for f in "org-chart/ownership/file-ownership.md" "org-chart/ownership/forseti-life-file-ownership.md"; do
  [ -f "$HQ_ROOT/$f" ] || echo "WARNING: ownership map missing: $f"
done
```

## Next actions
- [ ] dev-infra: add `agents.yaml` pause-state caching to eliminate per-call YAML reparsing (Finding 1, ROI=12)
- [ ] dev-infra: replace hardcoded absolute paths with relative derivation (Finding 2, ROI=15)
- [ ] dev-infra: fix `owner_for_hq` default to return empty string so truly uncovered paths surface (Finding 3, ROI=10)
- [ ] ceo-copilot: add missing-file warning for ownership map references (Finding 4, ROI=3)

## Blockers
- None.

## ROI estimate
- ROI: 15
- Rationale: Finding 2 (hardcoded paths) breaks the script entirely on any non-keithaumiller host or CI environment — a portability regression risk. Finding 3 masks the entire purpose of the audit tool by silently marking unknown paths as owned. Both are quick fixes with high correctness value.
