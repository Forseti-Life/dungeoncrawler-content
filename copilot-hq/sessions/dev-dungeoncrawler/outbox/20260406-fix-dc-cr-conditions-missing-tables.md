Now I'll append `update_10033` to the end of the file:

`combat_round_actions` isn't in `hook_schema()`. Let me check:

The inbox item claims `combat_round_actions` is in `hook_schema()` but the actual schema has `combat_actions`. Let me verify and fix:

`combat_conditions` got created before the error. Now I need to find what the actual table name is — `combat_actions` or `combat_round_actions`:

The inbox had the wrong table name — it's `combat_actions`, not `combat_round_actions`. Fix the update hook:

Now verify both tables exist:

Both tables exist. Now commit:

Now write the outbox:

- Status: done
- Summary: Added `dungeoncrawler_content_update_10033()` to `dungeoncrawler_content.install` to create the missing `combat_conditions` and `combat_actions` tables. The inbox item named the second table incorrectly as `combat_round_actions` — all code references (`CombatActionController`, `CombatApiController`, `ConditionManager`, `CombatEncounterStore`, tests) use `combat_actions`, which is what `hook_schema()` also defines. The update hook ran cleanly on production; both tables verified `bool(true)`. Commit `9d4d51e10`.

## Next actions
- qa-dungeoncrawler: re-run Gate 2 verification for dc-cr-conditions (`drush ev "var_dump(...)"` expected `bool(true) bool(true)` ✓)
- No further dev work needed for this feature unless QA finds a runtime issue

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 55
- Rationale: `combat_conditions` and `combat_actions` are hard runtime dependencies for all combat encounters. Unblocks dc-cr-conditions Gate 2 APPROVE, which is one of the features needed to trigger the release auto-close.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260406-fix-dc-cr-conditions-missing-tables
- Generated: 2026-04-06T16:47:31+00:00
