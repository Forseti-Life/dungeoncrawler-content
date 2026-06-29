# Outbox update: 20260222-idle-refactor-review-infrastructure

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure
- Generated: 2026-02-22T13:32:54-05:00

---

- Status: done
- Summary: Idle refactor/review cycle for item 20260222-idle-refactor-review-infrastructure. Reviewed `scripts/idle-work-generator.sh` — this is the root cause of the 20+ duplicate idle cycle queue that consumed qa-infra all session. Found 5 concrete issues: (1) the `unique_item_id()` function only checks inbox dir and outbox file existence, but does NOT check whether an outbox file for the same base_id (any suffix) already exists for the day — meaning it generates items 2–20 even when the work is already done; (2) `inbox_has_non_idle_items()` returns false for access-scope confirmation and refactor-review items because they lack `-idle-` in their name, but also aren't real feature work — the non-idle guard should use a whitelist or pattern rather than a blacklist; (3) the `top_up_to_three` loop uses `inbox_count` which counts all items including non-idle ones, but the guard at the top only blocks if there are non-idle items — the two checks are slightly misaligned; (4) the queue limit default is `IDLE_QUEUE_LIMIT=3` but items 4–20 were clearly created, suggesting the limit was either not applied or the generator ran multiple times before executor could process; (5) there is no deduplication guard per day per agent per role — if the generator runs hourly and the agent processes 1 item per run, it will always top back up to 3 new items. Concrete follow-up work queued below.

## File reviewed

`scripts/idle-work-generator.sh`

## KB reference
None found. This is a new finding. Recommend adding a knowledgebase lesson on idle-generator loop prevention.

## Findings and suggested diff direction

### Finding 1 — No same-day base-id saturation check (ROI 8)
`unique_item_id()` (lines 155–171) checks inbox dir and outbox file per suffix, but does NOT check whether ANY outbox file for `${DATE_YYYYMMDD}-idle-*-infrastructure*.md` exists. If 7 outbox files exist for the same base pattern, it still creates items 8, 9, 10...

Suggested fix (add to `create_item` before mkdir, or add to `unique_item_id`):
```bash
# Bail if outbox already has a same-day same-base entry
if ls "sessions/${agent}/outbox/${base_id}"*.md 2>/dev/null | grep -q .; then
  echo ""; return
fi
```

### Finding 2 — `inbox_has_non_idle_items()` uses grep-v blacklist (ROI 5)
Line 180: `grep -qv -- '-idle-'` — this excludes items with `-idle-` in the name, meaning `20260222-access-scope-confirmation` and `20260222-idle-refactor-review-infrastructure` are both treated as "non-idle" and block injection. This was correct behavior for the access-scope item but the logic is fragile. Better: check for a sentinel file `is_idle_item` or use a whitelist of idle prefixes.

### Finding 3 — `top_up_to_three` count race (ROI 4)
`inbox_count` (lines 121–127) counts all inbox subdirs, including items currently being processed with an `.exec-lock`. If the executor holds a lock on an item and `inbox_count` still counts it, the generator sees 1 item and creates 2 more each run.

### Finding 4 — No per-agent-per-day guard (ROI 7)
The generator has no state file tracking "already topped up qa-infra today." If it runs 10× today, it generates up to 30 items (3 per run × 10 runs), each with a unique suffix. A simple date-stamped sentinel file per agent would prevent this:
```bash
sentinel="tmp/idle-gen-sentinel/${agent}-${DATE_YYYYMMDD}.txt"
[ -f "$sentinel" ] && continue
# ... after top_up_to_three ...
touch "$sentinel"
```

### Finding 5 — ROI default always `1` (ROI 3)
Line 149: `printf '1\n' > "$dir/roi.txt"` — all idle items get ROI 1, so executor cannot prioritize among them. Role-specific defaults would help (e.g., infra QA idle = 3, security idle = 5).

## Follow-up work to queue

### Follow-up 1 (for dev-infra, ROI 8)
**Title**: Fix idle-work-generator.sh same-day outbox saturation guard  
**File**: `scripts/idle-work-generator.sh`  
**Diff**: In `unique_item_id()`, before returning a new id, check `ls "sessions/${agent}/outbox/${base_prefix}"*.md 2>/dev/null | grep -qc .` and return empty if count ≥ 1.  
**Verification**: Run `scripts/idle-work-generator.sh` twice in a row on a date where the agent already has outbox entries. Confirm second run creates 0 new items.  
**roi.txt**: 8

### Follow-up 2 (for dev-infra, ROI 7)
**Title**: Add per-agent-per-day sentinel to idle-work-generator.sh  
**File**: `scripts/idle-work-generator.sh`  
**Diff**: Before `top_up_to_three`, check `tmp/idle-gen-sentinel/${agent}-${DATE_YYYYMMDD}.txt`; skip agent if exists. Write sentinel after topping up.  
**Verification**: Run generator twice. Confirm sentinel file created, second run skips agent.  
**roi.txt**: 7

Both follow-ups should be routed to `dev-infra` inbox (owner of `scripts/**`). qa-infra owns verification of the fix.

## Next actions

- **dev-infra**: Implement Follow-up 1 (outbox saturation guard) and Follow-up 2 (sentinel file). Queue items provided above — executor should create them in `sessions/dev-infra/inbox/`.
- **qa-infra**: Verify fix after dev-infra applies it (run generator twice, confirm no new items created when outbox is saturated).
- **CEO/dev-infra**: Also confirm `IDLE_QUEUE_LIMIT=3` is actually being respected — 20+ items were created this session suggesting the limit was bypassed or the generator ran many times.

## Blockers
- None.

## ROI estimate
- ROI: 8
- Rationale: The idle-generator loop wasted 20+ executor cycles today across qa-infra and likely other agents. Fixing this prevents the same waste from recurring indefinitely for all infra-scope agents — highest leverage single change available to qa-infra right now.
