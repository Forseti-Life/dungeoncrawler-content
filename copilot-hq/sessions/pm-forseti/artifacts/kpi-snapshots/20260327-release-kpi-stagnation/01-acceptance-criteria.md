# Acceptance Criteria (PM-owned)

## Gap analysis reference
All criteria are `[TEST-ONLY]` — implementation (scoreboard files) exists; this is a content-only update.

## Happy Path
- [x] `[TEST-ONLY]` `knowledgebase/scoreboards/forseti.life.md` has a `2026-03-27` entry covering `20260326-dungeoncrawler-release-b` close with all 6 standard metrics populated.
- [x] `[TEST-ONLY]` `knowledgebase/scoreboards/dungeoncrawler.md` has a `2026-03-27` entry covering `20260326-dungeoncrawler-release-b` close with all 6 standard metrics populated.
- [x] `[TEST-ONLY]` All active gaps (GAP-DC-STALL-01, GAP-26B-01, GAP-26B-02, `/characters/create` SSL timeout) reflected in the dungeoncrawler scoreboard open-items section.

## Edge Cases
- [x] `[TEST-ONLY]` No scoreboard entry is older than 7 days at time of next release close.

## Failure Modes
- [x] `[TEST-ONLY]` Error messages are clear and actionable (N/A for content update).
- [x] `[TEST-ONLY]` Invalid input is rejected with explicit feedback (N/A for content update).

## Permissions / Access Control
- [x] Anonymous user behavior: N/A (scoreboard is internal HQ file).
- [x] Authenticated user behavior: N/A.
- [x] Admin behavior: N/A.

## Data Integrity
- [x] No data loss — prepend new entry; existing entries preserved.
- [x] Rollback path: prior scoreboard content remains below new entry.

## Knowledgebase check
- Prior pattern: `sessions/pm-forseti/artifacts/kpi-snapshots/20260326-release-kpi-stagnation/01-acceptance-criteria.md`
