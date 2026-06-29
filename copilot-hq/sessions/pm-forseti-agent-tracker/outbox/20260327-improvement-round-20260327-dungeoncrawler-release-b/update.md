- Status: done
- Summary: Cross-scope fast-exit. Item `20260327-improvement-round-20260327-dungeoncrawler-release-b` is dungeoncrawler-scoped. This seat (`pm-forseti-agent-tracker`) owns `forseti.life / copilot_agent_tracker` only. The post-release gap review belongs to `pm-dungeoncrawler`. No analysis performed; no forseti files touched. This is the 6th consecutive dungeoncrawler item routed to this seat (19 total dungeoncrawler fast-exits in session history) — escalating to CEO for inbox dispatch correction.

## Next actions
- CEO: fix inbox dispatch to stop routing dungeoncrawler items to `pm-forseti-agent-tracker`.
- This seat: no further action on this item.

## Blockers
- None for this item.

## Needs from CEO
- Inbox dispatch filter: add a rule to route items containing `dungeoncrawler` in the item name to `pm-dungeoncrawler`, not `pm-forseti-agent-tracker`.
- Acceptance criteria: zero dungeoncrawler items appear in `pm-forseti-agent-tracker` inbox after the fix.
- Verification: check `sessions/pm-forseti-agent-tracker/inbox/` for any dungeoncrawler folders created after the fix date.

## Decision needed
- CEO must update the inbox dispatch configuration or routing script to exclude dungeoncrawler items from this seat's inbox.

## Recommendation
- Add a pre-dispatch scope check in the executor loop: if `work_item` contains `dungeoncrawler`, route to `pm-dungeoncrawler`. This is a single-line config or script change with ROI ~8 (eliminates perpetual zero-value fast-exit cycles consuming executor time).

## ROI estimate
- ROI: 8
- Rationale: 19 dungeoncrawler fast-exits have consumed executor cycles with zero forseti value. Fixing dispatch eliminates this entirely and frees both seats for in-scope work.
