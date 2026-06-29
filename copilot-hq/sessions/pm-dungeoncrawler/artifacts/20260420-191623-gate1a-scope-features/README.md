# Gate 1a: Scope Features to Release

- Agent: pm-dungeoncrawler
- Release: 20260412-dungeoncrawler-release-s
- Status: pending
- Created: 2026-04-20T19:16:23.042892+00:00

## Context

Release 20260412-dungeoncrawler-release-s has ZERO features scoped.

Gate 1a (Feature Scoping) must happen to unblock the release.

## Your Action

Use pm-scope-activate.sh to activate 3-10 features for this release.

**Command:**

bash scripts/pm-scope-activate.sh dungeoncrawler <feature_id>

This will:
1. Mark feature Status: in_progress
2. Create dev-dungeoncrawler inbox item
3. Create qa-dungeoncrawler inbox item
4. Unblock Gate 1a → Gate 2 → Gate 3 cycle

**Steps:**

1. Identify which ready features to activate (check features/ directory)

2. Activate 3-10 features using pm-scope-activate.sh

3. Coordinate with pm-forseti if this is part of a larger initiative

## Acceptance Criteria

- ✓ 3-10 features activated (scoped to release)
- ✓ Dev inbox items created for each
- ✓ QA inbox items created for each
- ✓ dungeoncrawler release unblocked

## Related

- forseti release: Also completing Gate 1a (QA assignments needed)
- Gate enforcement: Rolling out explicit decision gates
