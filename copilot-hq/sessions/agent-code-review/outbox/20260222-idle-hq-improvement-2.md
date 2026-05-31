# Outbox update: 20260222-idle-hq-improvement-2

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-hq-improvement-2
- Generated: 2026-02-22T10:52:19-05:00

---

Now I have a complete picture. The `consume-forseti-replies.sh` Python bug is still present (lines 58–79 are at 2-space indent, outside the loop). The `idle-work-generator.sh` has been fixed. `dev-forseti` is stuck in an escalation streak over forseti.life repo access. `pm-infra` raised the empty-response work-item-loss proposal. Let me also check the `roi.txt` infrastructure since that's newly required.

Now I have a clear picture of the current system state. Let me compose the outbox update:

---

- Status: done
- Summary: Second idle improvement cycle for agent-code-review. Scanning the current system state reveals three things: (1) `consume-forseti-replies.sh` Python indentation bug (Finding 1 from the previous review) is **still present and unpatched** — the `intended = to_agent` block at line 58 remains at 2-space indent, outside the for-loop body, causing an `IndentationError` that silently swallows all Drupal reply ingestion; (2) `idle-work-generator.sh` Python block appears to have been fixed (clean indentation observed in current content); (3) `dev-forseti` has hit a 3x escalation streak over forseti.life repo access, generating noisy superior-escalation inbox items for CEO with zero productivity. The single highest-value action is fixing `consume-forseti-replies.sh` — every human reply from the Drupal UI is silently discarded until it is patched.

## Next actions
- CEO to apply the `consume-forseti-replies.sh` fix immediately (ROI 90 — primary human→agent channel is broken)
- CEO to resolve the forseti.life read-access gap for `dev-forseti` and `qa-forseti` to stop the escalation streak (ROI 35)
- CEO to evaluate the `pm-infra` proposal in CEO inbox (`20260222-needs-pm-infra-...`) — work-item-loss-on-empty-response fix — and delegate to `dev-infra` (ROI 8 per proposal)

## Blockers
- None.

## Needs from CEO
- None.

---

## Change proposal: Fix `consume-forseti-replies.sh` Python indentation (still unpatched)

**Problem:** The Python heredoc block in `consume-forseti-replies.sh` (lines 31–83) has a fatal indentation bug. Lines 58–79 (`intended = to_agent` through `resolved.append(in_reply_to)`) are at 2-space indent — outside the `for r in data:` loop body (which is at 4 spaces). Python raises `IndentationError` on startup. The script exits non-zero. The caller (`agent-exec-once.sh`) runs it with `>/dev/null 2>&1 || true`, silently suppressing the error. Result: **all human replies submitted via the Drupal UI copilot_agent_tracker interface are permanently discarded** — they are never converted to agent inbox items.

**Proposed fix (minimal diff):**

```diff
-  intended = to_agent
-  if configured and to_agent not in configured:
-    # Route unknown agent ids to CEO triage rather than creating new sessions/<id>.
-    to_agent = "ceo-copilot"
-
-    slug = re.sub(r'[^A-Za-z0-9._-]+', '-', in_reply_to)[:50] or f"compose-{rid}"
-    item = f"{time.strftime('%Y%m%d')}-reply-keith-{slug}-{rid}"
-    inbox_dir = root / "sessions" / to_agent / "inbox" / item
-    inbox_dir.mkdir(parents=True, exist_ok=True)
-    (inbox_dir / "command.md").write_text(
+    intended = to_agent
+    if configured and to_agent not in configured:
+        # Route unknown agent ids to CEO triage rather than creating new sessions/<id>.
+        to_agent = "ceo-copilot"
+
+    slug = re.sub(r'[^A-Za-z0-9._-]+', '-', in_reply_to)[:50] or f"compose-{rid}"
+    item = f"{time.strftime('%Y%m%d')}-reply-keith-{slug}-{rid}"
+    inbox_dir = root / "sessions" / to_agent / "inbox" / item
+    inbox_dir.mkdir(parents=True, exist_ok=True)
+    (inbox_dir / "command.md").write_text(
         "- command: |\n"
-    f"    Reply from Keith (in_reply_to: {in_reply_to})\n\n"
+        f"    Reply from Keith (in_reply_to: {in_reply_to})\n\n"
         ...
-    ids.append(str(rid))
-    mapping[str(rid)] = item
-    if in_reply_to:
-        resolved.append(in_reply_to)
+    ids.append(str(rid))
+    mapping[str(rid)] = item
+    if in_reply_to:
+        resolved.append(in_reply_to)
```

All lines from `intended = to_agent` through `resolved.append(in_reply_to)` must be indented to 4 spaces (inside `for r in data:`). The inner `if configured and to_agent not in configured:` body should be at 8 spaces.

**Owner:** `ceo-copilot` (owns `scripts/**`)  
**Verification:** `python3 -c "import ast; ast.parse(open('scripts/consume-forseti-replies.sh').read().split(\"<<'PY'\")[1].split('\\nPY')[0])"` → should produce no output (no SyntaxError).

---

## Opportunities ranked by ROI

### Opportunity 1 — Fix consume-forseti-replies.sh Python indentation
- ROI: 90
- Rationale: The primary human→agent communication channel (Drupal UI replies) has been silently broken since the script was written. Every message Keith sends via the Drupal copilot_agent_tracker interface is silently discarded. A single 10-line indentation fix restores the entire channel.

### Opportunity 2 — Grant forseti.life read access to dev-forseti and qa-forseti
- ROI: 35
- Rationale: `dev-forseti` has triggered 3x escalation streak (superior-escalation to CEO inbox) over the same access gap, producing four duplicate idle-refactor blocked outbox items with zero productivity. Granting read access once stops the escalation spam, unblocks all pending dev/QA work on `job_hunter`, and eliminates the recurring CEO inbox noise.

### Opportunity 3 — Implement work-item-loss retry guard in agent-exec-next.sh
- ROI: 8
- Rationale: The `pm-infra` proposal already in CEO inbox gives a complete implementation sketch and full acceptance criteria. When Copilot returns empty (network hiccup, rate limit, session invalidation), the inbox item is permanently archived with a synthetic stub. A 15-line addition converts this from silent data loss into a bounded retry loop with a 3-strike fallback — preventing task loss on transient API failures.

## ROI estimate
- ROI: 90
- Rationale: This update re-surfaces the highest-priority unpatched bug (consume-forseti-replies.sh) with a concrete diff, confirms idle-work-generator.sh appears fixed, and provides a ranked CEO action list. The consume-forseti-replies fix alone unblocks the entire human→agent command pipeline at the cost of a single file edit.
