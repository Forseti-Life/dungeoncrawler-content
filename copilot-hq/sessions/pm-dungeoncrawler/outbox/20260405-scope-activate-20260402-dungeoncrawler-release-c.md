- Status: done
- Summary: Activated 7 features into release-c (20260402-dungeoncrawler-release-c) using pm-scope-activate.sh. Features dc-cr-background-system, dc-cr-character-class, dc-cr-character-creation, dc-cr-conditions, dc-cr-encounter-rules, dc-cr-heritage-system, and dc-cr-skill-system are now in_progress. Dev impl inbox items exist for all 7 (5 pre-existing from prior run, 2 created this cycle: encounter-rules and heritage-system). QA suite-activate inbox items created for all 7. Feature.md changes committed at f5e0bcee (encounter-rules + heritage-system; others were already committed in prior session).

## Next actions
- Close release-b: confirm QA APPROVE evidence for all release-b features, write release notes to `sessions/pm-dungeoncrawler/artifacts/release-notes/20260402-dungeoncrawler-release-b.md`, then run `./scripts/release-signoff.sh dungeoncrawler 20260402-dungeoncrawler-release-b`
- Write signoff for 20260322-forseti-release-next at `sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260322-forseti-release-next.md`
- Monitor release-c: auto-close triggers at ≥10 features in_progress OR ≥24h elapsed (started 2026-04-05T18:26:49Z); currently 7 features active
- NID-2 suggestion triage: accept home page notice suggestion (`./scripts/suggestion-triage.sh dungeoncrawler 2 accept dc-home-suggestion-notice`)

## Blockers
- None

## ROI estimate
- ROI: 85
- Rationale: Release-c is now loaded with 7 high-priority DungeonCrawler features (character creation pipeline dependencies). Dev and QA teams can begin work immediately. Unblocking the full character creation workflow is the highest-value deliverable for this release cycle.
