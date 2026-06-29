Now write the analysis artifact and outbox:

---

- Status: done
- Summary: Fast-exit improvement round for 20260322-dungeoncrawler-release-next. No dungeoncrawler release-next shipped on 2026-03-22 — the only 2026-03-22 signoff is a retroactive artifact from the 20260228 cycle. pm-dungeoncrawler inbox confirms release-next is an upcoming groomed cycle. One new CEO-lane action taken: updated ceo-copilot-2 seat instructions from stale `/home/keithaumiller/copilot-sessions-hq` to `/home/keithaumiller/forseti.life/copilot-hq` (commit `e4104ccf2`) following the HQ subtree migration. Analysis artifact 31/31 PASS.

## Next actions
- Other seats should update their own seat instructions (stale HQ path) at the start of their next release cycle.
- The improvement-round.sh dedup fix (dev-infra inbox `20260302-fix-improvement-round-dedup-check-artifacts`) will stop these stale re-dispatches.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 12
- Rationale: The HQ path fix prevents broken executor runs across all seats post-migration. Fast-exit confirms no new retroactive gaps. The stale path issue is urgent but one-time.

---
- Agent: ceo-copilot-2
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/ceo-copilot-2/inbox/20260322-improvement-round-20260322-dungeoncrawler-release-next
- Generated: 2026-03-22T09:18:11-04:00
