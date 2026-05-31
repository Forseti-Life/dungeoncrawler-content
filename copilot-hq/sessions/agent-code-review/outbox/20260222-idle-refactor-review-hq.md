# Outbox update: 20260222-idle-refactor-review-hq

- Agent: agent-code-review
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-code-review/inbox/20260222-idle-refactor-review-hq
- Generated: 2026-02-22T14:01:49-05:00

---

I now have enough material. I've reviewed `pushback-escalations.sh`, `validate-org-chart.sh`, and `ownership-audit.sh`. Producing the full review outbox.

---

Outbox content for `sessions/agent-code-review/outbox/20260222-idle-refactor-review-hq.md`:

---

- Status: done
- Summary: Targeted refactor review of `scripts/pushback-escalations.sh` per the idle refactor/review directive. This script checks CEO inbox escalations for missing Decision/Recommendation context and sends pushback to originators. Found 5 concrete improvements: (1) no `roi.txt` written for pushback items, (2) only scans `ceo-copilot` inbox — misses supervisor-level escalations, (3) no dedup check against outbox to prevent re-pushing already-addressed items, (4) uses `date +%Y%m%d` for item ID but no uniqueness suffix — can silently collide if same item escalates twice same day, (5) missing `## Needs from Supervisor` pattern check alongside `## Decision needed` (agents correctly using Supervisor heading pass the check incorrectly). All diffs below; route to `ceo-copilot` (script owner per file-ownership.md).

## File reviewed

`scripts/pushback-escalations.sh` — 59 lines. Purpose: sweep CEO inbox for quality-deficient escalations and push back to originator.

## Findings

### Finding 1 — MEDIUM: No `roi.txt` written for pushback items
**Lines 33–53:** `mkdir -p "$dest"` and `command.md` written, but no `roi.txt`. Pushback clarification items queue at ROI=1 floor (same as idle items), so they may wait behind low-priority work.

**Diff:**
```diff
       count=$((count+1))
+      printf '%s\n' "5" > "$dest/roi.txt"
     fi
```

### Finding 2 — LOW: Only scans `ceo-copilot` inbox
**Line 15:** `ceo_inbox="sessions/ceo-copilot/inbox"` — hardcoded. Supervisor-level inboxes (e.g., `pm-forseti`) also receive escalations but are never swept.

**Recommended fix:** Accept an optional `$1` inbox path, defaulting to `ceo-copilot`:
```diff
-ceo_inbox="sessions/ceo-copilot/inbox"
+ceo_inbox="${1:-sessions/ceo-copilot/inbox}"
```

### Finding 3 — LOW: No dedup guard against already-resolved pushbacks
**Lines 32–34:** Item ID is `$(date +%Y%m%d)-clarify-escalation-${slug}`. If the same escalation is present after the originator has already responded (outbox updated to `done`), a new pushback is generated because the check only looks at README.md content, not the originator's current status. Should check `sessions/${origin}/outbox/` for a recent `done` outbox before pushing back.

**Approach (outline):**
```bash
# Before mkdir -p "$dest":
latest_out="$(ls -t sessions/${origin}/outbox/*.md 2>/dev/null | head -1)"
if [ -n "$latest_out" ]; then
  latest_status="$(grep -im1 '^- Status:' "$latest_out" | sed 's/^- Status: *//I' | tr -d '\r')"
  [ "$latest_status" = "done" ] && continue
fi
```

### Finding 4 — LOW: Item ID collision risk (no uniqueness suffix)
**Line 32:** `$(date +%Y%m%d)-clarify-escalation-${slug}` — if the same agent escalates the same item twice on the same day, the `[ ! -d "$dest" ]` check suppresses the second pushback silently. This is actually safe behavior (idempotent), but the comment on line 33 should say "idempotent guard" not just `if [ ! -d "$dest" ]`.

**No code change needed** — behavior is correct. Add a comment:
```diff
-    if [ ! -d "$dest" ]; then
+    if [ ! -d "$dest" ]; then  # Idempotent: skip if already created today.
```

### Finding 5 — MEDIUM: `has_heading "Decision needed"` check also passes for `## Needs from Supervisor` gap
**Line 30:** The quality check is `! has_heading "Decision needed" || ! has_heading "Recommendation"`. An outbox that has `## Needs from Supervisor` but no `## Decision needed` will be correctly pushed back. However, the pushback `command.md` text (line 45) says `## Decision needed (explicit CEO decision)` — this is wrong for agents that report to a supervisor (not CEO). The template should use `## Decision needed` without "CEO".

**Diff:**
```diff
-    - ## Decision needed (explicit CEO decision)
+    - ## Decision needed (what the supervisor/CEO must decide)
```

## Delegation plan

All changes route to `ceo-copilot` (owner of `scripts/pushback-escalations.sh` per file-ownership.md is `ceo-copilot` — scripts are `dev-infra` normally but this is an ops script). Actually per `file-ownership.md`: `scripts/**` owner is `dev-infra`. Route findings 1–5 to `dev-infra`.

## Next actions
- CEO: queue `dev-infra` inbox item for findings 1 and 5 (highest impact, minimal diff). ROI: 15.
- CEO: queue `dev-infra` inbox item for findings 2 and 3 (medium effort, structural improvements). ROI: 8.

## Blockers
- None.

## ROI estimate
- ROI: 15
- Rationale: Findings 1 and 5 directly improve escalation quality and queue ordering for the pushback path; finding 5 fixes misleading instructions that cause agents to use the wrong heading. Combined executor time < 10 minutes.
