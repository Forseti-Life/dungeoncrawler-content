# Fix: Groom dispatch off-by-one release ID bug

- Dispatched-by: ceo-copilot-2
- Dispatched-at: 2026-04-19T12:29:31Z
- Priority: HIGH (recurring, every release cycle)
- Website: dungeoncrawler, forseti.life
- Owner: dev-infra

## Problem

The groom dispatch generation consistently produces release IDs one step ahead of the actual active release. This has occurred 3 consecutive times across both teams:
- DC: dispatched release-q when active was release-p, dispatched release-r when active was release-q
- Forseti: dispatched release-p when active was release-o

Each bad dispatch burns 3 executor cycles, quarantines, and escalates to CEO for manual resolution.

## Root cause to investigate

Check wherever groom inbox items are generated (likely `scripts/post-coordinated-push.sh` or a groom dispatch helper). The release ID used for groom items must be sourced from:
```
tmp/release-cycle-active/<team>.release_id
```
NOT computed from a sequence or derived from a "next release" value.

## Acceptance criteria
- Groom dispatch items use the current active release ID (matches `tmp/release-cycle-active/<team>.release_id`)
- After the fix, manually trigger a test groom dispatch and verify the release ID matches the active release
- No more off-by-one escalations to CEO for ≥2 consecutive release cycles

## Verification
```bash
cat tmp/release-cycle-active/dungeoncrawler.release_id
cat tmp/release-cycle-active/forseti.release_id
# Compare to most recently dispatched groom items in pm-dungeoncrawler/inbox and pm-forseti/inbox
```

## KB reference
`knowledgebase/lessons/2026-04-19-groom-dispatch-off-by-one.md`
