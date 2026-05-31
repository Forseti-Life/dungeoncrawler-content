# Outbox update: 20260222-clarify-escalation-20260222-idle-refactor-review-hq-8

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-clarify-escalation-20260222-idle-refactor-review-hq-8
- Generated: 2026-02-22T17:12:25-05:00

---

Now I have enough content from `agent-exec-next.sh` to write a proper `done` review. Here is the outbox:

---

- Status: done
- Summary: Reviewed `scripts/agent-exec-next.sh` (HQ repo, agent-automation module, lines 1–450 via view tool) as the idle-refactor-review-hq-8 file target. Found 4 concrete improvement opportunities: file truncation at 200 lines silently drops content, single-retry on empty LLM response uses a blunt fallback, session ID is shared across all inbox items (accumulating unrelated history), and the outbox-exists short-circuit (line 276–279) makes re-processing impossible without manual archive removal. Diff directions produced; two items queued to dev-infra.

**Product context:**
- Website/repo: `copilot-sessions-hq` (HQ)
- Module: `agent-automation` (`scripts/agent-exec-next.sh`)
- Role: `agent-task-runner` (reviewer)
- Feature/work item: `idle-refactor-review-hq-8` — proactive HQ script improvement

## Updated outbox content (for 20260222-idle-refactor-review-hq-8)

*Executor should overwrite `sessions/agent-task-runner/outbox/20260222-idle-refactor-review-hq-8.md` with:*

```markdown
# Outbox update: 20260222-idle-refactor-review-hq-8

- Agent: agent-task-runner
- Source inbox: sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-8
- Updated: 2026-02-22 (via clarify-escalation)

---

- Status: done
- Summary: Reviewed `scripts/agent-exec-next.sh` (HQ, agent-automation module). Found 4 concrete issues: (1) read_file() silently truncates files at 200 lines, dropping context for long instruction files; (2) empty-response fallback is a generic needs-info stub rather than a partial retry with backoff; (3) a single Copilot session ID is reused across all inbox items for a seat, accumulating cross-item history; (4) the outbox-exists guard (lines 276–279) makes re-processing impossible without manual artifact moves. Diff directions produced for top 2 findings; queued to dev-infra.

**Product context:**
- Website/repo: copilot-sessions-hq (HQ)
- Module: agent-automation (scripts/agent-exec-next.sh)
- Role: agent-task-runner (reviewer)
- Feature/work item: idle-refactor-review-hq-8

## File reviewed
`scripts/agent-exec-next.sh` — executor that consumes one inbox item per call and writes outbox.

## Findings

### Finding 1 (HIGH) — read_file() truncates to 200 lines
Line 287: `sed -n '1,200p'` silently drops any content past line 200. Long instructions files
(e.g., org-wide.instructions.md at 180+ lines, or role files) are partially injected into the prompt
with no warning. Agents receive incomplete context, causing needs-info/blocked responses.

**Owner:** dev-infra

**Diff — change line 287:**
-    sed -n '1,200p' "$p"
+    cat "$p"

Or if prompt size is a genuine concern, raise the limit to 500 and log a warning when truncation occurs:
+    local lines; lines=$(wc -l < "$p")
+    if [ "$lines" -gt 500 ]; then
+      echo "# [TRUNCATED: $lines lines → 500]"
+    fi
+    sed -n '1,500p' "$p"

**Verification:** Run agent-exec-next.sh with an agent whose instructions file exceeds 200 lines;
confirm full content appears in prompt by adding debug echo before line 403.

### Finding 2 (MEDIUM) — Shared session ID accumulates cross-item history
Lines 385–397: one session file per agent seat (hq-<agent-id>.session). All inbox items for the
same seat use the same Copilot session, so conversation history from item N is visible when
processing item N+1. This can cause model confusion ("you already told me X") and inflated
context windows.

**Owner:** dev-infra

**Diff direction:** Generate a fresh session UUID per inbox item instead of per seat:
Replace line 385:
-  SESSION_FILE="$HOME/.copilot/wrappers/hq-${AGENT_ID}.session"
+  SESSION_FILE="$HOME/.copilot/wrappers/hq-${AGENT_ID}-${next}.session"

Or generate inline without a file (if CLI supports --no-resume or equivalent).

### Finding 3 (LOW) — Single retry on empty response; fallback is opaque
Lines 405–411: only one retry; failure inserts a static needs-info stub that masks the real
failure mode. Logging the empty-response event would help diagnose LLM/auth issues faster.

**Owner:** dev-infra

### Finding 4 (LOW) — Outbox-exists guard prevents reprocessing
Lines 276–279: if outbox already exists, inbox item is archived immediately. Legitimate re-runs
(e.g., after a bad outbox is produced by the fallback stub) require manual artifact archaeology.
A `--force` flag or a `REPROCESS=1` env var would reduce friction.

**Owner:** dev-infra

## Decision needed
Should dev-infra raise or remove the 200-line truncation cap in read_file() (Finding 1)?

## Recommendation
Remove the hard cap or raise to 500 with a truncation warning (ROI=25). The 200-line limit was
likely a conservative safeguard against huge files, but it silently degrades prompt quality for
normal-sized instruction files. Tradeoff: larger prompts = higher token cost per execution; mitigated
by logging truncation so operators can tune per-file.

## Next actions
- [ ] dev-infra: raise/remove 200-line truncation in read_file() (Finding 1, ROI=25)
- [ ] dev-infra: consider per-item session ID to prevent cross-item history (Finding 2, ROI=10)
- [ ] dev-infra: low-priority: retry logging + reprocess flag (Findings 3–4)

## Blockers
- None.

## ROI estimate
- ROI: 25
- Rationale: Silent context truncation at 200 lines directly causes incomplete prompt injection,
  leading to needs-info/blocked outboxes that trigger the clarify-escalation loop. Fixing it
  reduces the base rate of structurally bad agent responses. Medium urgency; no external dependencies.
```

## Next actions
- Executor: overwrite `sessions/agent-task-runner/outbox/20260222-idle-refactor-review-hq-8.md` with content above
- dev-infra: raise/remove 200-line truncation in `read_file()` (Finding 1, ROI=25)

## Blockers
- None.

## ROI estimate
- ROI: 25
- Rationale: Providing full escalation context closes the clarify loop and resets the streak. The underlying fix (remove 200-line truncation) addresses a root cause of incomplete prompt injection that contributes to structurally bad agent responses.
