# Outbox update: 20260222-idle-hq-improvement-9

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-hq-improvement-9
- Generated: 2026-02-22T13:54:28-05:00

---

Outbox content for `sessions/agent-code-review/outbox/20260222-idle-hq-improvement-9.md`:

---

- Status: done
- Summary: Ninth idle HQ improvement cycle — targeted code review of `scripts/hq-blockers.sh` per the idle fallback directive. Found one actionable bug: line 56 hardcodes `## Needs from CEO` when extracting the needs section from agent outboxes, but the current org-wide standard defines three valid escalation headings (`## Needs from Supervisor`, `## Needs from CEO`, `## Needs from Board`). Any agent using `## Needs from Supervisor` (the recommended default) has their needs section silently omitted from the blockers dashboard. Complete one-line diff below; route to `ceo-copilot` (owner per `file-ownership.md`).

## Change proposal

**Problem:** `scripts/hq-blockers.sh` line 56 only matches `## Needs from CEO`. Agents using `## Needs from Supervisor` (correct per org-wide instructions) are invisible to the dashboard's needs-extraction. CEO triage misses those agents' blocker context.

**Proposed change:** Update the awk pattern to match all three valid headings.

**Minimal diff:**
```diff
--- a/scripts/hq-blockers.sh
+++ b/scripts/hq-blockers.sh
@@ -55,7 +55,7 @@
   needs="$(awk 'BEGIN{p=0}
-    /^## Needs from CEO/{p=1;next}
+    /^## Needs from (Supervisor|CEO|Board)$/{p=1;next}
     /^## /{p=0}
     {if(p) print}
   ' "$latest" | sed -n '1,20p' | sed 's/^/    /')"
```

**Verification:** Run `bash scripts/hq-blockers.sh` — agents with `## Needs from Supervisor` heading should now appear in the needs section.

## Next actions
- CEO: apply diff above to `scripts/hq-blockers.sh` (1-line change, owner: `ceo-copilot`).
- CEO (optional): also audit `scripts/pushback-escalations.sh` and other scripts that parse outbox sections — same pattern may appear elsewhere.

## Blockers
- None.

## ROI estimate
- ROI: 20
- Rationale: Fixes the org-wide blocker dashboard so all agents' needs are visible to the CEO regardless of which escalation heading they use. One-line change with immediate impact on triage quality.
