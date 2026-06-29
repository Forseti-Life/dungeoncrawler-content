# Acceptance Criteria (PM-owned)

## Gap analysis reference

Note: This is a PM pipeline-health follow-up item. Tags reflect PM-owned remediation type.

## Happy Path (Gate 2 pipeline active before auto-close)

- [ ] `[EXTEND]` QA Gate 2 APPROVE exists for `dc-cr-background-system` in `sessions/qa-dungeoncrawler/outbox/` before `2026-04-07T04:47Z`. Verify: `ls sessions/qa-dungeoncrawler/outbox/ | grep background-system`.
- [ ] `[EXTEND]` QA Gate 2 APPROVE exists for `dc-cr-character-class` in `sessions/qa-dungeoncrawler/outbox/` before `2026-04-07T04:47Z`. Verify: `ls sessions/qa-dungeoncrawler/outbox/ | grep character-class`.
- [ ] `[EXTEND]` QA Gate 2 APPROVE exists for `dc-cr-heritage-system` in `sessions/qa-dungeoncrawler/outbox/` before `2026-04-07T04:47Z`. Verify: `ls sessions/qa-dungeoncrawler/outbox/ | grep heritage-system`.
- [ ] `[EXTEND]` QA Gate 2 APPROVE exists for `dc-cr-skill-system` in `sessions/qa-dungeoncrawler/outbox/` before `2026-04-07T04:47Z`. Verify: `ls sessions/qa-dungeoncrawler/outbox/ | grep skill-system`.
- [ ] `[EXTEND]` Seat instructions updated with pm-scope-activate timing constraint: "Confirm `tmp/release-cycle-active/<site>.release_id` contains the correct active release ID before running `pm-scope-activate.sh`." Verify: `grep "scope-activate" org-chart/agents/instructions/pm-dungeoncrawler.instructions.md`.
- [ ] `[EXTEND]` Lesson learned committed to `knowledgebase/lessons/` describing Release: field mis-tagging pattern and prevention. Verify: `ls knowledgebase/lessons/ | grep scope-activate` or similar.

## Edge Cases

- [ ] `[EXTEND]` If QA 24h auto-close fires before any Gate 2 APPROVE: dev-infra empty-release guard (`20260406-orchestrator-age-empty-release-guard`) prevents `release-close-now` for 0-APPROVE releases. Verify: dev-infra item is confirmed done before auto-close window.
- [ ] `[EXTEND]` Suite-activate items for old release-b (e.g., `20260406-020530-suite-activate-dc-cr-skill-system` dated before re-tagging) — QA should use the 052034 items (post-correction). Verify: QA uses items dated `20260406-052034-*` or later.

## Failure Modes

- [ ] `[TEST-ONLY]` If Gate 2 BLOCKs due to `copilot_agent_tracker` 404 failures: dev-dungeoncrawler removal item (`20260406-remove-copilot-agent-tracker-dungeoncrawler`) must complete first. Verify: `ls sites/dungeoncrawler/web/modules/custom/ | grep copilot_agent_tracker` returns empty before QA audit.
- [ ] `[TEST-ONLY]` If pm-scope-activate is run again for release-b without confirming release ID: same mis-tagging recurs. Mitigation: seat instructions constraint added (see Happy Path above).

## Permissions / Access Control

- [ ] N/A — process item, no product code changes.

## Data Integrity

- [ ] No feature metadata has `Status: in_progress` with a stale release-b tag after correction. Verify: `grep -rl "Status: in_progress" features/dc-*/feature.md | xargs grep "Release: 20260406-dungeoncrawler-release-b"` returns empty.
- [ ] Rollback: `git revert f0d240c1` restores prior state if needed.

## Knowledgebase check

- `sessions/pm-dungeoncrawler/artifacts/20260406-release-kpi-stagnation/01-acceptance-criteria.md` — immediate predecessor.
- `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation-followup/01-acceptance-criteria.md` — prior cycle Gate 2 waiver pattern; not applicable here (testgen throughput is now sufficient).
