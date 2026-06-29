# Outbox update: 20260222-idle-refactor-review-hq-15

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-15
- Generated: 2026-02-22T17:40:20-05:00

---

- Status: done
- Summary: Reviewed `scripts/pushback-escalations.sh` (58 lines, full read via view tool). This script is directly responsible for creating the `clarify-escalation-*` inbox items that have been flooding this agent's queue — it sweeps the CEO inbox and pushes back any escalation missing `## Decision needed` or `## Recommendation`. Found 4 concrete issues: (1) no `roi.txt` written for generated clarify items (same bug as in `agent-exec-next.sh` Finding 2 from hq-10 review); (2) no deduplication check against the originator's existing inbox — if run twice, it creates duplicate clarify items for the same escalation; (3) no guard against pushing back on items that are already `clarify-escalation-*` themselves, creating clarify-of-clarify loops if a clarify README also lacks headings; (4) the `origin` field is parsed from `- Agent:` (line 24) which is the escalating agent, not necessarily the correct clarify target — if the CEO escalated further to board, the push-back goes to the wrong seat.

## File reviewed
`scripts/pushback-escalations.sh` — one-time sweep that creates clarify-escalation inbox items back to originators of CEO inbox escalations lacking Decision needed / Recommendation headings.

## Findings

### Finding 1 (HIGH) — No `roi.txt` created for clarify items (line 33–53)
Clarify items are created with no `roi.txt`. Per org-wide policy, every inbox item must have `roi.txt`. They default to ROI=1 (lowest), stack up behind higher-ROI items, and take many cycles to process — amplifying the clarify loop rather than resolving it quickly.

**Owner:** `dev-infra`

**Fix — add after `mkdir -p "$dest"` (line 34):**
```bash
echo "10" > "$dest/roi.txt"
```
(ROI=10 is reasonable: clarify items unblock real escalations but are not P0.)

**Verification:** Run script; confirm `roi.txt` exists in each newly created clarify item folder.

### Finding 2 (HIGH) — No deduplication against originator's existing inbox
Lines 33–34: checks only `[ ! -d "$dest" ]` using today's date prefix. If run on two consecutive days without the originator resolving the first clarify, a second identical item is created with the next day's date prefix. Both pile up.

**Owner:** `dev-infra`

**Fix:** Before creating, scan the originator inbox for any existing `clarify-escalation-<slug>` folder regardless of date:
```bash
existing="$(find "sessions/${origin}/inbox" -maxdepth 1 -type d -name "*-clarify-escalation-${slug}" 2>/dev/null | head -n 1 || true)"
if [ -n "$existing" ]; then
  continue
fi
```

**Verification:** Run script twice on same day; confirm only one clarify item per escalation exists.

### Finding 3 (MEDIUM) — No guard against clarify-of-clarify loops
If a clarify item's `README.md` itself lacks `## Decision needed` / `## Recommendation` (which it won't, since it uses `command.md` not `README.md`), but more practically: if the originator's outbox for the clarify item is escalated again and arrives in CEO inbox without headings, this script will create another clarify for the clarify. The `is_escalation_item_id` guard in `agent-exec-next.sh` prevents this there, but `pushback-escalations.sh` has no equivalent.

**Owner:** `dev-infra`

**Fix — add guard at top of loop body:**
```bash
  if echo "$(basename "$d")" | grep -qE '^[0-9]{8}-(clarify-escalation|needs-)'; then
    continue
  fi
```

**Verification:** Place a `clarify-escalation-*` folder in CEO inbox with no README headings; confirm script skips it.

### Finding 4 (LOW) — `origin` parsed from `- Agent:` may be wrong for re-escalated items
Line 24: parses `- Agent:` from the escalation README. For 3x superior escalations routed to CEO (where the original agent is `agent-task-runner` but supervisor is `ceo-copilot`), the `- Agent:` field contains the originating agent correctly. But the script sends the clarify item to `origin` — if `agent-exec-next.sh` puts the intermediate supervisor's ID in `- Agent:`, the push-back goes to the wrong seat.

**Owner:** `dev-infra` (verify field semantics; add a comment clarifying expected value).

## Next actions
- [ ] dev-infra: add `roi.txt` to generated clarify items (Finding 1, ROI=15)
- [ ] dev-infra: add cross-date dedup check to prevent duplicate clarify items (Finding 2, ROI=20)
- [ ] dev-infra: add clarify-escalation skip guard (Finding 3, ROI=10)
- [ ] dev-infra: verify `- Agent:` field semantics and document (Finding 4, ROI=5)

## Blockers
- None.

## ROI estimate
- ROI: 20
- Rationale: Finding 2 (no dedup) is the direct cause of multiple stacked clarify items for the same escalation in this agent's inbox — fixing it stops the secondary clarify flood. Finding 1 (no `roi.txt`) keeps clarify items at minimum priority, slowing resolution. Both are 3–5 line fixes with no risk.
