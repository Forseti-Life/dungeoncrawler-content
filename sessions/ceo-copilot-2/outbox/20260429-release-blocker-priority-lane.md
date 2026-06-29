- Status: done
- Summary: Implemented option 3 for ROI handling: current-release blockers now run in a dedicated priority lane inside `agent-exec-next.sh`, instead of competing directly with aged backlog ROI. Added `scripts/lib/release-priority.sh` to detect active current-release items and classify release blockers (signoff, gate, code-review follow-up, release cleanup/close, blocking follow-up). Updated `agent-exec-next.sh` to sort inbox items by lane first, then ROI, and updated `hq-status.sh` to display the same effective next item so dashboard output matches actual executor behavior. This immediately corrected the live `pm-dungeoncrawler` queue: `20260429-code-review-followup-20260412-dungeoncrawler-release-y` is now the next item ahead of stale backlog entries.

## Verification
- `bash -n scripts/agent-exec-next.sh scripts/hq-status.sh scripts/lib/release-priority.sh`
- `PYTHONPATH=/home/ubuntu/forseti.life pytest -q scripts/tests/test_release_priority_lane.py orchestrator/tests/test_parallel_release_priority.py`
- `bash scripts/hq-status.sh` now reports `pm-dungeoncrawler` next inbox as `20260429-code-review-followup-20260412-dungeoncrawler-release-y`

## Next actions
- Let the next orchestrator execution consume `pm-dungeoncrawler`'s release-y follow-up item
- Let `pm-forseti` write the real signoff artifact for `20260412-forseti-release-v`
