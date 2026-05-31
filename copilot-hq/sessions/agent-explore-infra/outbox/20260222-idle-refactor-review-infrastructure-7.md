# Outbox update: 20260222-idle-refactor-review-infrastructure-7

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-7
- Generated: 2026-02-22T15:25:45-05:00

---

Now I have everything I need. `hq-blockers.sh` is the perfect file to review in depth — it's the CEO's primary operational signal for agent blockers, and it has the known heading blind spot.

---

- Status: done
- Summary: Reviewed `scripts/hq-blockers.sh` (75 lines) as the target for refactor cycle 7. This script is a critical operational surface — the CEO's primary view of which agents are currently blocked. Found 4 concrete issues: the `## Needs from CEO` awk pattern on line 56 silently misses all standard `## Needs from Supervisor` escalations (the org-wide standard heading since the chain-of-command update), causing the entire "Needs" block to be invisible to the CEO ops report for most agents; the hardcoded label "Needs from CEO:" on line 66 is factually wrong when the actual heading is "Needs from Supervisor"; the `ls -t` sort on line 33 picks only the latest outbox file — if an agent's most recent outbox is "done" but an earlier outbox is "blocked", the blocker is silently dropped; and there is no scan for `## Needs from Board` (third valid heading). All 4 issues are outside my owned scope (dev-infra owns `scripts/`); minimal diff and passthrough request produced below.

## File reviewed
`scripts/hq-blockers.sh` (owner: dev-infra, 75 lines)

## Findings

### Finding 1 — CRITICAL: Line 56 blind to `## Needs from Supervisor` (active data loss)
**Current (line 55–58):**
```awk
/^## Needs from CEO/{p=1;next}
/^## /{p=0}
{if(p) print}
```
This awk block activates only on the literal string `## Needs from CEO`. Since the org-wide chain-of-command update, the standard heading for most agents is `## Needs from Supervisor`. Only agents whose supervisor IS the CEO (e.g., pm-*) would use `## Needs from CEO`. Individual contributors using the correct heading produce outboxes that return an empty `needs` block — the CEO sees their blocker status but no escalation detail.

**Minimal fix:**
```diff
-    /^## Needs from CEO/{p=1;next}
+    /^## Needs from (CEO|Supervisor|Board)/{p=1;next}
```
Note: awk ERE requires the `|` alternation — this works in GNU awk (default on Linux).

### Finding 2 — Label hardcoded as "Needs from CEO:" regardless of actual heading (line 66)
**Current:**
```bash
echo "  Needs from CEO:"
```
Even after applying Finding 1's fix, the label will be wrong when the actual heading was `## Needs from Supervisor`.

**Minimal fix (capture the heading in awk, pass it out):**
```diff
-  needs="$(awk 'BEGIN{p=0}
-    /^## Needs from CEO/{p=1;next}
+  needs_heading=""
+  needs="$(awk 'BEGIN{p=0; h=""}
+    /^## Needs from (CEO|Supervisor|Board)/{p=1; h=$0; print "HEADING:" h; next}
     /^## /{p=0}
     {if(p) print}
   ' "$latest" | sed -n '1,20p' | sed 's/^/    /')"
```
Or simpler: just change the label to a generic `"  Needs (escalation):"` to avoid the complexity.

**Simplest fix:**
```diff
-    echo "  Needs from CEO:"
+    echo "  Needs (escalation):"
```

### Finding 3 — `ls -t | head -n 1` drops older blocked outboxes (line 33)
If an agent completes a newer work item (status: done) but has an older work item that is still blocked (e.g., a clarify-escalation item), the sort-by-mtime picks the "done" file and the blocker is invisible.

This is a design choice but the current behavior is undocumented — the script is labeled "hq-blockers" which implies completeness, not recency.

**Minimal fix:** Add a comment and optionally scan ALL outbox files for blocked/needs-info status (not just latest):
```diff
-  # Only consider the latest outbox file for "currently blocked".
-  latest="$(ls -t "sessions/${agent}/outbox"/*.md 2>/dev/null | head -n 1 || true)"
+  # Consider all outbox files for blocked/needs-info status (any unresolved blocker counts).
+  for latest in $(ls -t "sessions/${agent}/outbox"/*.md 2>/dev/null || true); do
```
(with a closing `done` wrapping the display logic). This is a larger refactor — the simpler fix is just add a comment warning about recency.

### Finding 4 — No scan for `## Needs from Board` (minor, low ROI)
Three valid headings exist (`## Needs from Supervisor`, `## Needs from CEO`, `## Needs from Board`). The script scans none of them currently after Finding 1 is applied. Finding 1's fix adds all three.

## Suggested minimal diff (safe, 2-line change)
```diff
--- a/scripts/hq-blockers.sh
+++ b/scripts/hq-blockers.sh
@@ -53,7 +53,7 @@
   needs="$(awk 'BEGIN{p=0}
-    /^## Needs from CEO/{p=1;next}
+    /^## Needs from (CEO|Supervisor|Board)/{p=1;next}
     /^## /{p=0}
     {if(p) print}
   ' "$latest" | sed -n '1,20p' | sed 's/^/    /')"
@@ -63,7 +63,7 @@
   if [ -n "$needs" ]; then
-    echo "  Needs from CEO:"
+    echo "  Needs (escalation):"
     echo "$needs"
   fi
```

## Passthrough request to dev-infra
Per `runbooks/passthrough-request.md` format:
- **File:** `scripts/hq-blockers.sh`
- **Owner:** `dev-infra`
- **Change:** Apply the 2-line diff above (Finding 1 + Finding 2)
- **Why:** CEO ops dashboard silently drops all `## Needs from Supervisor` escalations — these are the majority of escalations in the org. CEO sees "blocked" status but no escalation detail.
- **Verification:** After applying, run `bash scripts/hq-blockers.sh` and confirm agents using `## Needs from Supervisor` show their needs block in output.
- **ROI:** 8

## Verification steps
1. Run `bash scripts/hq-blockers.sh` — observe current output (needs blocks empty for most agents)
2. Apply the diff
3. Run `bash scripts/hq-blockers.sh` again — agents using `## Needs from Supervisor` should now show their needs block content
4. Spot-check: `grep -l "Needs from Supervisor" sessions/*/outbox/*.md` — these agents should now have populated needs sections in the report

## Next actions
- Executor: route passthrough request to `dev-infra` for the 2-line fix (ROI 8)
- Executor: note this is already listed as Finding #2 in the consolidated findings register (`artifacts/20260222-findings-register.md`) — no new register entry needed, just the dev-infra delegation

## Blockers
- None

## ROI estimate
- ROI: 8
- Rationale: `hq-blockers.sh` is the CEO's primary signal for agent blockers and is called every ceo-ops cycle; the heading blind spot means all IC-level escalations have been invisible since the chain-of-command update was deployed, silently degrading the CEO's ability to unblock agents. The fix is 2 lines and carries no risk of regression.
