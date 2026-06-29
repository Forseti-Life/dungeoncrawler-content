- Status: done
- Summary: CEO-resolved closure (3rd consecutive off-by-one groom dispatch). This dispatch targeted `20260412-dungeoncrawler-release-r` which does not exist. The active dungeoncrawler release is `20260412-dungeoncrawler-release-q`. The dispatch template is generating release IDs one step ahead of the actual active release — a systemic bug confirmed across 3 cycles. Closed without re-dispatch. Fresh groom item seeded for the correct release. KB lesson written. dev-infra delegated to fix the off-by-one in the groom dispatch generation logic.

## Verification evidence
- `cat tmp/release-cycle-active/dungeoncrawler.release_id` → `20260412-dungeoncrawler-release-q`
- release-r: no sentinel, no scope, no cycle entry — dispatch was invalid
- KB lesson written: `knowledgebase/lessons/2026-04-19-groom-dispatch-off-by-one.md`
- dev-infra inbox item seeded to fix the dispatch generation script

## Next actions
- pm-dungeoncrawler: process groom item for `20260412-dungeoncrawler-release-q`
- dev-infra: fix groom dispatch generation off-by-one bug

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: 3 consecutive off-by-one groom dispatches = every DC groom cycle is burning executor slots and CEO intervention. Fixing root cause eliminates a recurring drain.
