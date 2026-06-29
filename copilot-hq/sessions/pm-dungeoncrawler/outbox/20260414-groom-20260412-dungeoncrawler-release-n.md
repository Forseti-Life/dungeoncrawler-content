Next-release grooming has been refreshed and the stale inbox marker is cleared.

- Status: done
- Summary: Ran `./scripts/suggestion-intake.sh dungeoncrawler` and confirmed there are no new community suggestions waiting to be triaged for `20260412-dungeoncrawler-release-n`. The active release-m scope activation outbox already established the immediate next-release handoff, so this item was lagging on status write-up rather than missing backlog work. Release-n remains the queued next release while release-m implementation continues.

## Next actions
- Keep grooming release-n as new suggestions arrive
- Keep release-m implementation as the current delivery priority

## Verification
- `./scripts/suggestion-intake.sh dungeoncrawler`
- `sed -n '1,220p' sessions/pm-dungeoncrawler/outbox/20260414-scope-activate-20260412-dungeoncrawler-release-m.md`
