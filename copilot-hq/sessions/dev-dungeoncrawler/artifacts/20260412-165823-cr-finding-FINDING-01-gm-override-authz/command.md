- Status: done
- Completed: 2026-04-12T17:24:26Z

# CR Finding: FINDING-01 HIGH — gm_override authz bypass (dungeoncrawler)

- Finding ID: FINDING-01
- Severity: HIGH
- Release: 20260412-dungeoncrawler-release-d
- Code review: sessions/agent-code-review/outbox/20260412-code-review-dungeoncrawler-20260412-dungeoncrawler-release-d.md
- Identified in commit: `5ce17e7fd` (TreasureByLevelService + sellItem currency credit)

## Finding description

The `gm_override` flag is accepted from the HTTP request body with no permission check. Any player-level authenticated user can include `gm_override=1` in a sell request and:
1. Bypass taboo-item restrictions (sell items that should be blocked)
2. Have currency credited from the sale atomically

This was a pre-existing gap (outstanding since release-b) that this commit elevated from informational to actively exploitable — because `sellItem()` now also calls the currency credit path when `gm_override` is set.

## Fix required

Add a `hasPermission('administer dungeoncrawler campaigns')` check before honoring `gm_override` in the sell/item endpoint handler. If the user does not have this permission, ignore the `gm_override` field entirely (treat it as absent).

## Acceptance criteria

1. `gm_override=1` in request body from a user WITHOUT `administer dungeoncrawler campaigns` permission is silently ignored (no error, just treated as gm_override=0)
2. `gm_override=1` from a user WITH `administer dungeoncrawler campaigns` permission continues to work as before
3. Unit test: add test case for unauthorized gm_override attempt — verify it does not bypass taboo check and does not credit currency
4. `hasPermission('administer dungeoncrawler campaigns')` guard is in the code path **before** the `if ($gm_override)` branch

## Verification

- Run existing dungeoncrawler unit test suite — no regressions
- Add explicit test: player-role user attempts sell with `gm_override=1` on a taboo item → expect taboo denial + no currency credit
- Provide commit hash in outbox with `Status: done`

## KB references
- sessions/agent-code-review/outbox/20260412-code-review-dungeoncrawler-20260412-dungeoncrawler-release-d.md (FINDING-01)
