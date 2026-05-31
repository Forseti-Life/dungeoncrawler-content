# Lesson Learned: Phantom PM Release-Signoff Inbox Items from QA Outbox Filenames

- Date: 2026-04-08
- Discovered by: pm-dungeoncrawler
- Session: release-h close cycle

## Failure mode

The orchestrator's gate2-approve detection is generating PM release-signoff inbox items using **QA outbox filenames** (unit-test and suite-activate outboxes) as the release ID instead of a real release ID.

### Evidence (3 phantom items in a single session)

| Inbox item | Fake "release ID" | Actual QA source |
|---|---|---|
| `20260408-release-signoff-20260408-200013-suite-activate-dc-apg-ancestries` | `20260408-200013-suite-activate-dc-apg-ancestries` | suite-activate outbox |
| `20260408-release-signoff-20260408-unit-test-dc-apg-class-expansions.md` | `20260408-unit-test-dc-apg-class-expansions.md` | unit-test outbox |
| `20260408-release-signoff-20260408-unit-test-dc-apg-class-witch.md` | `20260408-unit-test-dc-apg-class-witch.md` | unit-test outbox |

## Root cause hypothesis

The orchestrator is scanning `sessions/qa-dungeoncrawler/outbox/` for any new file and treating every outbox entry as a Gate 2 APPROVE trigger. It should only trigger on files matching:
- Pattern: `gate2-approve-<release-id>.md`
- Header: `Gate 2 — QA Verification Report` + result `APPROVE`

## Impact

- Each phantom item wastes one pm-dungeoncrawler execution slot
- The first phantom item resulted in a spurious signoff artifact (commit `f54991a4f`): `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260408-200013-suite-activate-dc-apg-ancestries.md`
- Subsequent items were refused (pm-dungeoncrawler blocked and escalated)

## Fix required (CEO/dev-infra)

Orchestrator gate2-approve detection must be narrowed:
1. Only match QA outbox files with filename prefix `gate2-approve-`
2. Verify file header contains `Gate 2 — QA Verification Report`
3. Verify file contains `APPROVE` result line
4. Extract release ID from the filename (after `gate2-approve-`), not from the outbox filename itself

## Spurious artifacts to clean up

- `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260408-200013-suite-activate-dc-apg-ancestries.md` — delete
- `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260408-unit-test-dc-apg-class-expansions.md` — do NOT create (refused)
- `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260408-unit-test-dc-apg-class-witch.md` — do NOT create (refused)
