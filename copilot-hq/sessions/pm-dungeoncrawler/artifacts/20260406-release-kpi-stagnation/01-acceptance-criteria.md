# Acceptance Criteria (PM-owned)

## Gap analysis reference

Note: This is a PM pipeline-health / data-correction item, not a feature implementation.
Tags reflect PM-owned remediation type.

## Happy Path (release scope corrected and pipeline active)
- [ ] `[EXTEND]` `dc-cr-background-system` feature.md shows `Release: 20260406-dungeoncrawler-release-next` and `Status: in_progress`. Verify: `grep "Status:\|Release:" features/dc-cr-background-system/feature.md`.
- [ ] `[EXTEND]` `dc-cr-character-class` feature.md shows `Release: 20260406-dungeoncrawler-release-next` and `Status: in_progress`. Verify: `grep "Status:\|Release:" features/dc-cr-character-class/feature.md`.
- [ ] `[EXTEND]` `dc-cr-heritage-system` feature.md shows `Release: 20260406-dungeoncrawler-release-next` and `Status: in_progress`. Verify: `grep "Status:\|Release:" features/dc-cr-heritage-system/feature.md`.
- [ ] `[EXTEND]` `dc-cr-skill-system` feature.md shows `Release: 20260406-dungeoncrawler-release-next` and `Status: in_progress`. Verify: `grep "Status:\|Release:" features/dc-cr-skill-system/feature.md`.
- [ ] `[EXTEND]` `dc-cr-conditions` feature.md shows `Status: ready` (deferred from release-next scope). Verify: `grep "Status:" features/dc-cr-conditions/feature.md`.
- [ ] `[EXTEND]` No feature shows `Status: in_progress` with `Release: 20260406-dungeoncrawler-release-b` (old mis-tag cleaned). Verify: `grep -rl "Status: in_progress" features/dc-*/ 2>/dev/null | xargs grep -l "Release: 20260406-dungeoncrawler-release-b"` returns empty.
- [ ] `[EXTEND]` Changes committed to git in copilot-hq repo.

## Edge Cases
- [ ] `[TEST-ONLY]` If QA suite-activate inbox items for these 4 features were issued under release-b scope, they must be re-issued for release-next. Verify: `ls sessions/qa-dungeoncrawler/inbox/ | grep -E "background-system|character-class|heritage-system|skill-system"` shows current items.
- [ ] `[EXTEND]` Scoreboard `knowledgebase/scoreboards/dungeoncrawler.md` updated to reflect corrected in_progress count for release-next.

## Failure Modes
- [ ] `[TEST-ONLY]` If the 24h auto-close fires before corrections are committed, the empty-release guard in orchestrator should prevent a close-now for a release with 0 Gate 2 APPROVEs (pending dev-infra fix `20260406-orchestrator-age-empty-release-guard`).
- [ ] `[TEST-ONLY]` If dev-dungeoncrawler has already committed work for these features with wrong release-b tag, the work is still valid — only the release metadata needs updating, not the implementation.

## Permissions / Access Control
- [ ] Anonymous user behavior: N/A (process item).
- [ ] Authenticated user behavior: N/A.
- [ ] Admin behavior: N/A.

## Data Integrity
- [ ] Feature.md edits are metadata-only; no DB schema or application state affected.
- [ ] Rollback: `git revert` on the correction commit restores prior state.

## Knowledgebase check
- `sessions/pm-dungeoncrawler/artifacts/release-kpi-stagnation/01-acceptance-criteria.md` — prior instance for format reference.
