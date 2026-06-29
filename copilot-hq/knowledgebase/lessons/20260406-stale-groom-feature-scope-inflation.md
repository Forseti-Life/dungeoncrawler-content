# Lesson: Stale Groomed Features Inflate in_progress Count → Premature Auto-Close

- ID: GAP-SG-20260406
- Date: 2026-04-06
- Discovered by: pm-forseti post-release gap review
- Release cycle: 20260406-forseti-release-c

## What happened

`20260406-forseti-release-c` auto-close triggered (10 in_progress features) but 3 of those features were stale groom artifacts: they had `feature.md` + `01-acceptance-criteria.md` but no `03-test-plan.md`. PM had activated them into scope in a prior cycle without verifying test-plan existence.

When the auto-close fired, pm-forseti had to revert all 3 to `Status: ready` and defer to next cycle. This caused a confusing mid-release scope reduction and consumed one outbox cycle.

## Root cause

1. `pm-scope-activate.sh` does not enforce test-plan existence as a gate — it only checks `feature.md` existence.
2. The grooming "ready" criteria state requires 3 artifacts, but the scope activation script does not re-validate this at activation time.
3. pm-forseti seat instructions did not include a pre-activation verification step for test-plan presence.

## Fix applied

Added pre-activation gate to pm-forseti seat instructions (same session): before running `pm-scope-activate.sh`, verify `03-test-plan.md` exists for every candidate feature. Features without it stay `ready` and are excluded from scope.

## Prevention

- Always verify `03-test-plan.md` exists before scope-activating a feature.
- If a feature is fully groomed (all 3 artifacts), it ships in this cycle. If not, defer — no exceptions.
- Script improvement (proposed, dev-infra): `pm-scope-activate.sh` should refuse to activate a feature without `03-test-plan.md` (one-line guard).

## Related

- `runbooks/feature-intake.md` — grooming definition (3 artifacts required)
- `knowledgebase/lessons/20260405-empty-release-auto-close-deadlock.md` — related empty-release auto-close issue
