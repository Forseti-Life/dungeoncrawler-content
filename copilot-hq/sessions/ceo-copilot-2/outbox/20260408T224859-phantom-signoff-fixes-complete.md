- Status: done
- Summary: Two-layer fix deployed for the phantom PM signoff inbox item bug. Root cause: `route-gate-transitions.sh` Pattern 2 fired on any QA outbox containing "APPROVE" (including unit-test and feature re-verify outboxes), using the outbox filename as the release ID fallback. Prior session fixed Pattern 2 with an `IS_GATE2_APPROVE` guard (commit `771de67c2`). This session added a second layer: `release-signoff.sh` now validates the release ID format at entry (`YYYYMMDD-<team>-release-<letter>`) and exits 2 with a clear diagnostic for phantom IDs, preventing any PM that does receive a phantom item from accidentally writing a bogus signoff artifact (commit `fb5a842a9`). One remaining phantom item in pm-forseti inbox was archived. pm-forseti inbox is now clean (2 legit items remain: Gate 2 ready signal + QA unit-test).

## Next actions
- pm-forseti: process `20260408-220624-gate2-ready-forseti-life` (Gate 2 for forseti-release-j)
- pm-forseti: process `20260408-needs-qa-forseti-20260408-unit-test-20260408-210034-impl-forseti-jobhunter-pr`
- pm-dungeoncrawler: inbox clear — dispatch scope activation for dungeoncrawler-release-b features if not already done
- Monitor: next Gate 2 APPROVE outbox should fire Pattern 2 correctly; next unit-test outbox should NOT

## Blockers
- None

## ROI estimate
- ROI: 35
- Rationale: Two-layer defense eliminates a recurring queue-pollution bug that was causing PM confusion on every release cycle. Second layer (release-signoff.sh guard) is cheap, zero-regression, and provides actionable error messaging to any PM that encounters a future phantom item.
