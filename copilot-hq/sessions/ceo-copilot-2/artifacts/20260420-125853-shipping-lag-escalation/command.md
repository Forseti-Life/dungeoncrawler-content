- Status: done
- Completed: 2026-04-20T13:28:00Z

# Shipping Lag Escalation — Release(s) Dev-Complete but Unshipped

**Detected:** 2026-04-20T12:58:53.371659+00:00
**Threshold:** 72h from dev-done to push

## Stalled Releases
- 20260412-dungeoncrawler-release-r (dev-done 186.2h ago, threshold 72h)

## Impact
- Completed features are sitting unreleased — user value delayed
- Each additional day increases risk of merge conflicts and drift

## Possible Causes
1. PM signoff gate not advancing (check pm-<site> quarantine status)
2. QA Gate 2 not completed or blocked
3. Code review gate not cleared
4. Release cycle paused (`tmp/release-cycle-control/paused`)

## Action required
```bash
bash scripts/hq-status.sh
bash scripts/hq-blockers.sh
```
Identify the blocking gate and clear it or authorize a bypass.
