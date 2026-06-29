# Acceptance Criteria: Release Handoff Full Investigation
# Release: 20260327-dungeoncrawler-release-b

## Gate Status Snapshot (as of 2026-03-28T04:30)

| Feature | Dev status | Dev commit(s) | QA Gate 2 | Feature.md |
|---|---|---|---|---|
| dc-cr-action-economy | done | fd4854e94, f66b85f27, 75399762a | PENDING (in qa inbox) | in_progress |
| dc-cr-ancestry-system | done | 79a6d3dfe, 31ba60772 | PENDING (in qa inbox) | in_progress |
| dc-cr-dice-system | done | 971e17227 | PENDING (in qa inbox) | in_progress |
| dc-cr-difficulty-class | done | 3b391099e | PENDING (in qa inbox) | in_progress |

## Release Gate Completion Criteria (Definition of Done)

### QA Gate 2 (required — not yet done)
- [ ] `[NEW]` qa-dungeoncrawler processes `20260327-unit-test-20260327-impl-dc-cr-action-economy` → APPROVE or BLOCK
- [ ] `[NEW]` qa-dungeoncrawler processes `20260327-unit-test-20260327-impl-dc-cr-ancestry-system` → APPROVE or BLOCK
- [ ] `[NEW]` qa-dungeoncrawler processes `20260327-unit-test-20260327-impl-dc-cr-dice-system` → APPROVE or BLOCK (note: PHPUnit DB creds gap known — QA should verify endpoint behavior, not rely solely on unit test runner)
- [ ] `[NEW]` qa-dungeoncrawler processes `20260327-unit-test-20260327-impl-dc-cr-difficulty-class` → APPROVE or BLOCK
- [ ] All 4 return APPROVE with evidence attached

### Feature status updates (required — not yet done)
- [ ] `[EXTEND]` All 4 feature.md files updated from `in_progress` → `shipped` after QA APPROVE

### Release signoff (blocked on pm-forseti)
- [ ] `[NEW]` pm-forseti records signoff for `20260327-dungeoncrawler-release-b`
- [ ] `scripts/release-signoff-status.sh 20260327-dungeoncrawler-release-b` exits 0

### Post-release (after push)
- [ ] `[NEW]` QA runs post-release audit against production dungeoncrawler
- [ ] pm-dungeoncrawler begins Stage 0 scope selection for `20260328-dungeoncrawler-release-b` using `sessions/pm-dungeoncrawler/artifacts/grooming/20260328-dungeoncrawler-release-b-readypool.md`

## Edge Cases
- [ ] `[TEST-ONLY]` If QA returns BLOCK: dev fixes in same cycle, QA re-verifies before signoff
- [ ] `[TEST-ONLY]` PHPUnit DB credential failure is NOT a BLOCK: endpoint behavior verified via curl is acceptable evidence per dev outbox documentation

## Permissions / Access Control
- [ ] All 4 new endpoints use `_access: TRUE` (anonymous accessible) — confirmed by QA preflight
- [ ] No authenticated-only surfaces introduced in this release

## Data Integrity
- [ ] No schema changes in action-economy, dice-system, difficulty-class
- [ ] ancestry-system adds discrete fields; rollback: `git revert 79a6d3dfe`
- [ ] dice-system adds dc_roll_log table; rollback documented in dev outbox

## Knowledgebase check
- Pattern: PHPUnit DB credential gap is a recurring infra issue — QA should accept curl-verified endpoint evidence as Gate 2 APPROVE signal
- Pattern: pm-forseti signoff dependency recurs every coordinated release cycle
