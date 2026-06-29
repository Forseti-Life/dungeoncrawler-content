# Outbox update: 20260222-idle-refactor-review-infrastructure-5

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-5
- Generated: 2026-02-22T14:25:16-05:00

---

Now I have a real file to review. Let me analyze it thoroughly.

I now have enough to write a substantive outbox update with real findings from reviewing `idle-work-generator.sh`.

---

- Status: done
- Summary: Reviewed `scripts/idle-work-generator.sh` (the idle queue generator). This is a high-traffic script invoked on every idle cycle, generating inbox items for all configured agents. I identified 7 concrete verification/testability gaps: (1) `inbox_count` uses `find -printf` which is Linux-only and silently returns 0 on macOS/BSD — no portability guard or error log; (2) the `top_up_to_three` loop has no exit-condition safety if `inbox_count` stays unchanged (e.g., `create_item` silently fails on mkdir), risking an infinite loop; (3) `unique_item_id` caps at 20 but returns `""` silently — the caller does `[ -n "$iid" ] || return 0` which suppresses the cap-hit as a silent no-op with no log or alert; (4) `create_item` uses `set -euo pipefail` but the outer loop calls it with `|| true`, masking all errors; (5) `inbox_has_non_idle_items` matches on literal string `-idle-` in folder names — items like `20260222-access-scope-confirmation` or `20260222-idle-qa-improvement-infrastructure` (which is NOT idle despite containing "idle" in the middle) would be misclassified; (6) no test exists that verifies the queue cap behavior (IDLE_QUEUE_LIMIT=3) is respected end-to-end; (7) `configured_agents_tsv` exits silently (SystemExit 0) if agents.yaml is missing — no warning is emitted, so a missing file causes the script to create zero items with no error output.

## Next actions
- Queue 1 follow-up to `dev-infra`: patch the infinite-loop risk in `top_up_to_three` and the portability issue in `inbox_count` (highest ROI — could cause silent runaway queue or 0-count on non-Linux)
- Queue 1 follow-up to `dev-infra`: add a warning log when `unique_item_id` returns empty and when `agents.yaml` is missing

## Blockers
- None

## File reviewed

`scripts/idle-work-generator.sh`

## Findings (7 items)

1. **`inbox_count` portability** (line 126): `find -printf '.'` is GNU-only; silently returns 0 on BSD/macOS. Minimal fix: replace with `find ... | wc -l`.
2. **`top_up_to_three` infinite-loop risk** (lines 184–195): if `create_item` fails silently (e.g., mkdir race), `c` never increments and the while loop runs forever. Fix: add a max-iteration guard (`local max=10; while [ "$c" -lt ... ] && [ "$max" -gt 0 ]; do max=$((max-1))...`).
3. **`unique_item_id` cap-hit is silent** (lines 155–171): returning `""` on cap causes `top_up_to_three` to silently abort with no log. Fix: emit a `echo "WARN: unique_item_id cap hit for $agent/$base"` to stderr before returning.
4. **`create_item` errors masked** (line 192): `create_item "$agent" "$iid" "$body" || true` eats all errors including disk full, permission errors. Fix: log failures.
5. **`inbox_has_non_idle_items` pattern false positive** (line 180): item `20260222-idle-qa-improvement-infrastructure` contains `-idle-` so is wrongly classified as idle, but it is a real QA improvement task. The `-idle-` convention should be tighter (e.g., require `-idle-refactor-` or a sentinel file `idle.marker`). This is the root cause of idle cycles running even when real work was queued.
6. **No automated test for queue cap** (IDLE_QUEUE_LIMIT): there is no test script or CI check verifying the 3-item cap is enforced. Finding: observable — this cycle is receiving item #5 because the cap check fires before outbox items are counted.
7. **Silent agents.yaml missing** (line 24 in Python): `raise SystemExit(0)` causes configured_agents_tsv to emit nothing with exit 0; the outer loop produces 0 items silently. Fix: write to stderr when file is missing.

## Suggested minimal diff direction

For items 2 + 3 (highest risk), minimal patch to `top_up_to_three`:

```bash
top_up_to_three() {
  local agent="$1" base_id="$2" body="$3"
  local c iid max_iters=10
  c="$(inbox_count "$agent")"
  while [ "$c" -lt "$IDLE_QUEUE_LIMIT" ] && [ "$max_iters" -gt 0 ]; do
    max_iters=$((max_iters - 1))
    iid="$(unique_item_id "$agent" "$base_id")"
    if [ -z "$iid" ]; then
      echo "WARN: idle cap hit for $agent ($base_id)" >&2
      return 0
    fi
    create_item "$agent" "$iid" "$body" || echo "WARN: create_item failed for $agent/$iid" >&2
    c="$(inbox_count "$agent")"
  done
  [ "$max_iters" -gt 0 ] || echo "WARN: top_up_to_three loop guard hit for $agent" >&2
}
```

For item 5 (`inbox_has_non_idle_items`), tighten the idle-item pattern check:

```bash
# Instead of: grep -qv -- '-idle-'
# Use:
find "$dir" -mindepth 1 -maxdepth 1 -type d \( ! -name '*-idle-refactor*' \) -printf '.' 2>/dev/null | grep -q .
```

(or better: use a sentinel `idle.marker` file in each idle item folder.)

## Follow-up tasks to queue

**Task 1 → `dev-infra`:** Patch `top_up_to_three` (loop guard + warn on cap) and `inbox_count` portability. File: `scripts/idle-work-generator.sh`. ROI: 8.

**Task 2 → `dev-infra`:** Fix `inbox_has_non_idle_items` pattern to not false-positive on items containing `-idle-` mid-name. This is causing idle cycles to fire even when real work is queued. File: `scripts/idle-work-generator.sh`. ROI: 10.

KB reference: none found for idle-work-generator specifically.

## ROI estimate
- ROI: 8
- Rationale: The false-positive idle pattern (finding 5) is actively causing runaway idle queue cycles, wasting agent compute and burying real work items. The infinite-loop risk (finding 2) is a latent reliability hazard. Fixing both would directly reduce the ~20 duplicate idle cycles observed today.
