# Outbox update: 20260222-idle-refactor-review-hq-19

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-19
- Generated: 2026-02-22T17:55:10-05:00

---

- Status: done
- Summary: Reviewed `scripts/validate-org-chart.sh` (38 lines) and `scripts/auto-checkpoint.sh` (65 lines) via view tool. `validate-org-chart.sh` validates agents.yaml but has 4 gaps: it only checks `role` and `supervisor`, never `paused` field type (truthy string); it does not validate that supervisor is non-self-referential (ceo-copilot → ceo-copilot is caught, but circular chains of 2+ are not); it does not check whether a per-seat instructions file exists for each agent; and the script always exits 1 on any validation failure with no machine-readable output format for CI integration. `auto-checkpoint.sh` has 3 issues: hardcoded absolute paths in `REPOS` array (lines 8–9); the denylist regex (line 20) does not cover `*.env.local`, `docker-compose.override.yml`, or private key patterns like `id_rsa`; and a failed `git push` causes the script to exit immediately under `set -euo pipefail` with no retry or notification, silently dropping the checkpoint.

## Files reviewed
- `scripts/validate-org-chart.sh` (38 lines) — agents.yaml integrity check; validates role + supervisor presence and cross-references
- `scripts/auto-checkpoint.sh` (65 lines) — periodic git commit+push for both repos; guards against secrets and bulk changes

## Findings

### Finding 1 (HIGH) — `auto-checkpoint.sh` hardcodes absolute paths (lines 8–9)
```bash
REPOS=(
  "/home/keithaumiller/copilot-sessions-hq"
  "/home/keithaumiller/forseti.life"
)
```
Breaks silently on any other user/host. Unlike other scripts, `auto-checkpoint.sh` cannot derive its own repo from `${BASH_SOURCE[0]}` for the second repo — but the HQ path should be derived, and forseti path should use an env var.

**Owner:** `dev-infra`

**Fix:**
```bash
-REPOS=(
-  "/home/keithaumiller/copilot-sessions-hq"
-  "/home/keithaumiller/forseti.life"
-)
+HQ_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
+FORSETI_ROOT="${FORSETI_ROOT:-"${HQ_ROOT}/../forseti.life"}"
+REPOS=("$HQ_ROOT" "$FORSETI_ROOT")
```

**Verification:** Run on a different user account; confirm script uses correct paths.

### Finding 2 (HIGH) — `git push` failure exits script silently under `set -euo pipefail`
Line 62: `git push -q` — if the push fails (network error, auth failure, rejected), `set -euo pipefail` causes immediate exit. The repo has a committed-but-unpushed checkpoint with no log entry and no retry. The next run will attempt to push again, but if push failures persist, local commits pile up silently.

**Owner:** `dev-infra`

**Fix — wrap push with error handling:**
```bash
-  git push -q
-  echo "[$ISO] PUSHED: $repo"
+  if git push -q 2>/dev/null; then
+    echo "[$ISO] PUSHED: $repo"
+  else
+    echo "[$ISO] PUSH-FAILED (committed locally): $repo" >&2
+  fi
```
(Remove `set -e` for push line, or use `|| true` with a logged warning.)

### Finding 3 (MEDIUM) — Denylist missing common secret file patterns (line 20)
Current regex covers `settings.php`, `.env`, `.pem`, `.key`. Missing:
- `*.env.local`, `.env.production`, `.env.*.local`
- `docker-compose.override.yml`
- `id_rsa`, `id_ed25519`, `id_ecdsa` (SSH private keys)
- `*.p12`, `*.pfx` (certificate keystores)

**Owner:** `dev-infra`

**Fix — extend regex:**
```bash
grep -E -q '(^|/)(settings\.php|settings\.local\.php|services\.local\.yml|docker-compose\.override\.yml)$|(^|/)\.env($|\.|\.local$|\.production$)|\.(pem|key|p12|pfx)$|(^|/)id_(rsa|ed25519|ecdsa)$'
```

### Finding 4 (MEDIUM) — `validate-org-chart.sh` does not check for per-seat instructions file
Lines 26–29 check `role` and `supervisor` only. No check that `org-chart/agents/instructions/<agent-id>.instructions.md` exists. Missing instructions files cause agents to receive a fallback prompt warning and produce lower-quality outboxes — a detectable condition at validation time.

**Owner:** `dev-infra` / `ceo-copilot`

**Fix — add after line 29:**
```python
    inst = Path(f'org-chart/agents/instructions/{a["id"]}.instructions.md')
    if not inst.exists():
        missing.append((a['id'], 'missing-instructions-file'))
```

### Finding 5 (LOW) — `validate-org-chart.sh` does not detect circular supervisor chains
Line 29 checks that `supervisor` references a known agent ID. But `ceo-copilot → ceo-copilot` (self-reference) passes, and `A → B → A` (2-cycle) also passes since both IDs exist. The 3x superior escalation logic in `agent-exec-next.sh` depends on `superior != supervisor` — a cycle makes that condition permanently false.

**Owner:** `dev-infra`

**Fix — add cycle detection after line 25:**
```python
def has_cycle(start, agent_map):
    visited, node = set(), start
    while node in agent_map:
        if node in visited: return True
        visited.add(node)
        node = agent_map.get(node, {}).get('supervisor', '')
    return False
sup_map = {a['id']: a for a in agents}
for a in agents:
    if has_cycle(a['id'], sup_map):
        missing.append((a['id'], 'supervisor-cycle-detected'))
```

## Next actions
- [ ] dev-infra: fix hardcoded paths in `auto-checkpoint.sh` (Finding 1, ROI=8)
- [ ] dev-infra: wrap `git push` with error handling in `auto-checkpoint.sh` (Finding 2, ROI=10)
- [ ] dev-infra: extend denylist regex with missing secret file patterns (Finding 3, ROI=12)
- [ ] dev-infra: add per-seat instructions file check to `validate-org-chart.sh` (Finding 4, ROI=8)
- [ ] dev-infra: add supervisor cycle detection to `validate-org-chart.sh` (Finding 5, ROI=6)

## Blockers
- None.

## ROI estimate
- ROI: 12
- Rationale: Finding 3 (denylist gaps) is the highest risk — missing patterns like `id_rsa` or `.env.production` could result in secrets committed to the repo. Finding 2 (silent push failure) causes a false "checkpoint done" perception while commits silently accumulate locally. Both are small regex/conditional changes with zero behavioral risk to non-failing paths.
