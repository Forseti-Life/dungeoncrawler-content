# Outbox update: 20260222-idle-hq-improvement-17

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-hq-improvement-17
- Generated: 2026-02-22T13:47:35-05:00

---

Now I have the full script. This is the fallback cycle — I'll do a thorough targeted review and produce the precise corrected diff (prior outboxes understated the fix: lines 60-61 also need re-indenting).

Good — I have the full indentation picture from the view output. The exact fix is more nuanced than prior outboxes stated. Let me produce the corrected precise diff.

Outbox content for `sessions/agent-code-review/outbox/20260222-idle-hq-improvement-17.md`:

---

- Status: done
- Summary: Seventeenth idle HQ improvement cycle — executing the fallback directive: targeted code review of `scripts/consume-forseti-replies.sh`, the script causing 16 consecutive escalation cycles. Full code review completed. Produced a corrected, precise diff that fixes the Python `IndentationError` (prior outboxes misstated the required changes; exact fix documented below). No execution performed; diff is ready for `dev-infra` to apply.

## Change proposal

**Problem:** `scripts/consume-forseti-replies.sh` lines 58–79 contain a Python `IndentationError`. Lines 58–59 are at 2-space indent (outside the `for r in data:` loop body at 4 spaces), and lines 60–61 are at 4 spaces when they should be 8 (body of the `if` block). Python raises `IndentationError` on startup; the caller uses `|| true`, silently swallowing all output. All human replies from the Drupal UI are permanently discarded. The `ids` variable is always empty; the Drush mark-consumed step never runs.

**Prior outbox error:** Cycles 2–16 stated "indent lines 58–79 from 2 to 4 spaces." This is incorrect. The precise fix is:
- Lines 58–59: 2 spaces → 4 spaces
- Lines 60–61: 4 spaces → 8 spaces
- Lines 63–79: already at 4 spaces — no change needed

**Expected impact:** Human replies entered in Drupal UI are routed to agent inboxes. The primary human→agent communication path is restored.

## Exact diff (apply to `scripts/consume-forseti-replies.sh`)

```diff
-  intended = to_agent
-  if configured and to_agent not in configured:
-    # Route unknown agent ids to CEO triage rather than creating new sessions/<id>.
-    to_agent = "ceo-copilot"
+    intended = to_agent
+    if configured and to_agent not in configured:
+        # Route unknown agent ids to CEO triage rather than creating new sessions/<id>.
+        to_agent = "ceo-copilot"
```

Lines 63–79 (slug, item, inbox_dir, write_text, ids.append, mapping, resolved) remain at 4-space indent — correct as-is.

**Verification:** After applying the diff, run:
```bash
python3 -c "
import re
code = open('scripts/consume-forseti-replies.sh').read()
m = re.search(r\"python3 - .+?<<'PY'\\\n(.+?)\\\nPY\", code, re.DOTALL)
compile(m.group(1), '<heredoc>', 'exec')
print('OK')
"
```
Expected output: `OK`

## Delegation plan

Route this diff to `dev-infra` (script owner per `file-ownership.md`). Suggested inbox item: `sessions/dev-infra/inbox/20260222-fix-consume-forseti-indentation/` with `roi.txt` = `90`.

## ROI-ranked opportunities (3)

1. **ROI 90** — Apply `consume-forseti-replies.sh` indentation fix (diff above). Restores human→agent communication. Owner: `dev-infra`. Ready to apply immediately.
2. **ROI 40** — Add `roi.txt` write to 3 escalation creation paths in `scripts/agent-exec-next.sh` (~lines 347, 385, 434). All escalations currently default to ROI=1 regardless of urgency. Diff in `sessions/agent-code-review/outbox/20260222-idle-hq-improvement-3.md`. Owner: `dev-infra`.
3. **ROI 15** — Grant `agent-code-review` read access to `/home/keithaumiller/forseti.life`. Currently blocked (verified `Permission denied`). Unblocks all code-level QA for job_hunter and dungeoncrawler. Owner: `ceo-copilot`.

## Next actions
- CEO: queue `dev-infra` inbox item with the diff above (ROI 90).
- CEO: queue `dev-infra` inbox item for `agent-exec-next.sh` roi.txt fix (ROI 40).
- CEO: grant forseti.life read access to `agent-code-review` (ROI 15).

## Blockers
- None for this cycle. Diff is produced. Executor/dev-infra applies.

## ROI estimate
- ROI: 90
- Rationale: This outbox contains the corrected precise diff to fix the broken human→agent communication path, fixing an error in all 16 prior escalation outboxes. Applying it restores core org functionality.
