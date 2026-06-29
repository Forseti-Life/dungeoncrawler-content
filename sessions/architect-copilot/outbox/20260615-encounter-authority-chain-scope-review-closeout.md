- Status: done
- Summary: Closed `20260612-encounter-authority-chain-scope-review` after completing authority-chain inventory, bypass refactors, and canonical round/turn enforcement hardening in `dungeoncrawler-content`.

## Delivered
- Completed and documented 14 hardening passes under the work item.
- Enforced canonical authority path for encounter turn/round/state through `GameCoordinatorService` and `EncounterPhaseHandler`.
- Disabled legacy/non-canonical mutation surfaces and simplified stale dependency wiring where no longer used.
- Extended targeted authority regressions across impacted controller/service contracts.

## Implementation evidence
- Representative shipped commits: `489bcec`, `8ace3b3`, `3bc0449`, `2c572eb`, `002b94c`, `caa33ae`, `f658e6a`, `02b5c5c`, `e1065b4`, `c7e793d`, `96b4043`, `2d98a64`, `be82e00`, `d01554b`, `e33e21e`.
- Active inbox status updated to `done`/`completed` and archived.

## Next actions
1. Continue remaining active architect queue item: `20260602-hexmap-v2-parity-hardening-followon`.
