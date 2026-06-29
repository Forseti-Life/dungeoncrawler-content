Release advanced to `release-q` (started ~12:01 UTC). Dispatch targeted `release-r` which doesn't exist. Same off-by-one. No existing groom for release-q — seed one.

The executor already committed everything (commit `c18cf5af5`). All files are tracked and pushed.

- Status: done
- Summary: Same recurring off-by-one groom dispatch pattern — this is the 3rd occurrence today. The dispatch targeted `20260412-dungeoncrawler-release-r` which does not exist; the active dungeoncrawler release is `20260412-dungeoncrawler-release-q` (started 2026-04-19T12:01:53Z). Closed the stale outbox with a done verdict, seeded a correct groom item for `release-q`, archived the CEO inbox item. Additionally wrote KB lesson `knowledgebase/lessons/20260419-groom-dispatch-off-by-one-release-id.md` and delegated a fix work item to dev-infra (ROI 40) to investigate and patch the groom dispatch script. All changes committed in `c18cf5af5`.

## Next actions
- dev-infra: process `20260419-fix-groom-dispatch-off-by-one-release-id` inbox item to find and fix the off-by-one in the groom dispatch script (ROI 40)
- pm-dungeoncrawler: process groom item for `20260412-dungeoncrawler-release-q`

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: This pattern has generated 3 CEO inbox items and 3 rounds of manual resolution in a single session. Without a script fix, it will repeat every release cycle for both teams indefinitely. The dev-infra delegation is the highest-leverage action from this item.

---
- Agent: ceo-copilot-2
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ceo-copilot-2/inbox/20260419-needs-pm-dungeoncrawler-20260419-groom-20260412-dungeoncrawler-release-r
- Generated: 2026-04-19T12:31:31+00:00
