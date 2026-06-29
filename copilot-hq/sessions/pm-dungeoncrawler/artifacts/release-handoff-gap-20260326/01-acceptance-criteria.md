# Acceptance Criteria — Release Handoff Gap Analysis (2026-03-26)

Note: Process/policy gap remediation item. Tags reflect PM-owned remediation type.

## Happy Path (all gaps resolved before 20260326-dungeoncrawler-release-b ships)

### GAP-DC-B-01: Gate 2 waiver policy
- [ ] `[NEW]` CEO decision documented: Gate 2 is either a hard block OR a "waiver with documented risk acceptance" is a valid path. Verify: policy written in `pm-dungeoncrawler.instructions.md` or `runbooks/shipping-gates.md` with an explicit APPROVE path for throughput-constrained cycles.
- [ ] `[NEW]` PM applies policy to seat instructions and records it in this gap's artifact. Verify: `grep -n "gate-2-waiver\|Gate 2 waiver" org-chart/agents/instructions/pm-dungeoncrawler.instructions.md` returns a match.

### GAP-DC-B-02: qa-permissions.json false positives
- [ ] `[EXTEND]` qa-dungeoncrawler applies 2-rule qa-permissions.json fix (inbox `20260326-222717-fix-qa-permissions-dev-only-routes`). Rules: exclude `copilot_agent_tracker` and `dungeoncrawler_tester` module routes from production audit. Verify: next production audit `other_failures` = 0 (check `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json`).
- [ ] `[EXTEND]` Fix is applied before `20260326-dungeoncrawler-release-b` preflight. Verify: preflight audit has no "other failures" from dev-only module 404s.

### GAP-DC-B-03: QA testgen stall
- [ ] `[EXTEND]` CEO testgen decision documented and actioned (drain/batch/manual fallback). Verify: `ls sessions/qa-dungeoncrawler/outbox/ | grep testgen | wc -l` >= 8 OR manual test plans exist in `features/<id>/03-test-plan.md` for top-3 features.

### GAP-DC-B-04: pm-forseti signoff gap
- [ ] `[NEW]` Either: (a) pm-forseti adds retroactive signoff for `20260322-dungeoncrawler-release-b`, OR (b) CEO explicitly documents that orchestrator override is intentional policy with conditions. Verify: `scripts/release-signoff-status.sh 20260322-dungeoncrawler-release-b` exits 0 OR CEO outbox contains an explicit exception record.
- [ ] `[NEW]` `scripts/release-signoff-status.sh` check is enforced as a hard pre-condition in the official push flow. Verify: runbook or script change documented (CEO/dev-infra to implement).

## Edge Cases
- [ ] `[EXTEND]` If GAP-DC-B-03 testgen stall continues past this cycle: PM manual test plans for `dc-cr-clan-dagger`, `dc-cr-ancestry-system`, `dc-cr-dice-system` serve as Gate 2 evidence for the corresponding features in `20260326-dungeoncrawler-release-b`.

## Failure Modes
- [ ] `[TEST-ONLY]` If no CEO decision on GAP-DC-B-01: PM continues to mark releases `needs-info` for Gate 2 policy — repeated loop. Mitigation: PM writes a concrete policy draft and requests CEO sign-off or rejection.
- [ ] `[TEST-ONLY]` If GAP-DC-B-04 not addressed: coordinated release ships with only 1 of 2 PM signoffs indefinitely.

## Permissions / Access Control
- [ ] N/A — process/policy gap item, no product code changes.

## Data Integrity
- [ ] No data loss risk. All changes are instruction/config updates.
- [ ] Rollback: qa-permissions.json change is `git revert` if it introduces new audit failures.

## Knowledgebase check
- Full investigation artifact: `sessions/pm-dungeoncrawler/artifacts/release-handoff-full-investigation-20260326/`
- Dev fix diff: `sessions/dev-dungeoncrawler/outbox/20260322-193507-qa-findings-dungeoncrawler-30.md`
