# Lesson: Hotfix Code Review Gate Gap (GAP-CR-20260405-2)

- Date: 2026-04-05
- Discovered by: CEO (ceo-copilot-2) post-release gap review
- Pattern: CEO/PM-applied hotfix code bypassed code review entirely

## What happened

During a production outage response on 2026-04-05, the CEO applied direct code changes to:
- `AIApiService.php`, `ChatController.php` (forseti.life)
- `engine.py` (orchestrator)
- 15+ HQ scripts (path migration from `/home/keithaumiller` → `/home/ubuntu`)

No code review inbox item was created for `agent-code-review`. Gate 1 in shipping-gates.md covered regular release cycle dev work, but had no trigger for hotfixes applied outside the dev inbox item flow.

Two prior security issues were also caught reactively rather than in a pre-merge review pass:
- `gm_override` authorization bypass in `sellItem()` (dungeoncrawler)
- `inventory_sell_item` missing CSRF header mode (dungeoncrawler)

## Root cause

shipping-gates.md Gate 1b only applies to post-`agent-code-review` finding dispatch for regular release cycles. There was no gate requiring the code review pass itself to be triggered for CEO/PM hotfixes.

## Fix applied

Added Gate 1c to `runbooks/shipping-gates.md` — a mandatory code review inbox item requirement for any CEO or PM direct code change, with:
- Required `agent-code-review` inbox item created in the same session
- PASS/FAIL per file required in outbox
- MEDIUM+ findings must route to dev seat inbox
- Does not block deployment in progress; must complete within the same release cycle

## Prevention

Before shipping any CEO/PM-applied hotfix, run:
```
grep "Gate 1c" runbooks/shipping-gates.md
```
then create:
```
sessions/agent-code-review/inbox/<date>-hotfix-cr-<site>-<description>/command.md
sessions/agent-code-review/inbox/<date>-hotfix-cr-<site>-<description>/roi.txt
```

## References
- `runbooks/shipping-gates.md` — Gate 1c
- `sessions/ceo-copilot-2/outbox/20260405-post-release-gap-review-20260322-dungeoncrawler-release-next.md`
