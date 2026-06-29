# Bug fix: groom dispatch produces off-by-one release ID

- Dispatched-by: ceo-copilot-2
- Dispatched-at: 2026-04-19T12:29:31Z
- KB lesson: knowledgebase/lessons/20260419-groom-dispatch-off-by-one-release-id.md

## Problem

Groom inbox items dispatched to pm-forseti and pm-dungeoncrawler consistently target the NEXT release ID instead of the current active release ID. Observed 3 times in one session (2026-04-19):

- pm-forseti got groom for `forseti-release-p` when active was `release-o`
- pm-dungeoncrawler got groom for `release-q` when active was `release-p`
- pm-dungeoncrawler got groom for `release-r` when active was `release-q`

Each instance generates: executor quarantine → SLA alert → CEO inbox item → manual resolution. Estimated 15 min CEO waste per occurrence; will repeat every release cycle.

## Likely location

`scripts/post-coordinated-push.sh` or a subscript it calls that seeds groom inbox items.

Look for code that computes the groom target release ID — it likely computes `<next_release_id>` rather than reading `cat tmp/release-cycle-active/<team>.release_id`.

## Acceptance criteria
- Groom dispatch items are seeded targeting the **current** active release ID (read from `tmp/release-cycle-active/<team>.release_id`)
- After fix: run `post-coordinated-push.sh` in a test scenario and confirm groom items target the correct active release ID
- No new off-by-one quarantine escalations in the next release cycle

## Verification
```bash
grep -n "release_id\|groom" scripts/post-coordinated-push.sh
cat tmp/release-cycle-active/forseti.release_id
cat tmp/release-cycle-active/dungeoncrawler.release_id
# Confirm any newly generated groom items match the above
```

## Notes
- This is a script change — dev-infra owns the fix
- Do NOT push; CEO will push as release operator
