# Fix: post-coordinated-push.sh must advance tmp/release-cycle-active/<team>.release_id

## Background

`scripts/post-coordinated-push.sh` writes the pushed marker file (e.g., `tmp/auto-push-dispatched/<combined>.pushed`) and files release signoffs, but does **not** update `tmp/release-cycle-active/<team>.release_id`. That advancement is done asynchronously by the orchestrator's `_release_cycle_step()`.

During `20260408-dungeoncrawler-release-c`, pm-forseti processed the push-ready inbox item and ran `post-coordinated-push.sh` before the orchestrator had a tick to advance the dungeoncrawler cycle. `tmp/release-cycle-active/dungeoncrawler.release_id` was still `release-b`, causing the script to fail. pm-forseti had to manually update the file before the push could proceed.

Root cause: **release cycle state advancement is not atomic with the push**.

## Required change

In `scripts/post-coordinated-push.sh`, after successfully writing the pushed marker (Step 2), add Step 3: for each coordinated team, read the team's `next_release_id` file (`tmp/release-cycle-active/<team>.next_release_id`) and write it as the new `tmp/release-cycle-active/<team>.release_id`.

### Acceptance criteria

1. After `post-coordinated-push.sh` completes successfully, `tmp/release-cycle-active/dungeoncrawler.release_id` (and all coordinated team `release_id` files) are advanced to the value from their corresponding `.next_release_id` files.
2. If a team's `.next_release_id` file does not exist, log a warning and skip that team (do not fail).
3. The orchestrator's `_release_cycle_step()` detects the already-advanced `release_id` on its next tick and does not re-advance or error out.
4. Running `post-coordinated-push.sh` twice is idempotent: the second run detects the marker already exists and exits without overwriting `release_id` files.
5. Add or update test coverage in `scripts/tests/` covering the advancement behavior.

## Files in scope

- `scripts/post-coordinated-push.sh` — primary change (Step 3: advance release_id files)

## Verification method

```bash
# Before: dungeoncrawler.release_id = old_release_id, next_release_id = new_release_id
# After post-coordinated-push.sh: dungeoncrawler.release_id = new_release_id
bash scripts/post-coordinated-push.sh
cat tmp/release-cycle-active/dungeoncrawler.release_id  # should show new_release_id
```

## ROI

35 — eliminates manual release_id fix that blocked coordinated push for release-c; prevents recurrence in every subsequent coordinated release cycle.
