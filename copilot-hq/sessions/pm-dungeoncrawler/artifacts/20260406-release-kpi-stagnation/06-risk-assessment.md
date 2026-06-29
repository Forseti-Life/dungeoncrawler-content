# Risk Assessment (PM-owned, all contribute)

## Risk Register
| Risk | Likelihood | Impact | Mitigation | Owner |
|------|------------|--------|------------|-------|
| Release-next auto-closes (24h) before Re-tagging completes | Low (18h remain) | Medium — empty release ships | Complete correction this session; dev-infra empty-release guard (ROI 30) provides backstop | pm-dungeoncrawler |
| Dev work done under release-b label not recognized in release-next Gate 2 | Low | Low — QA verification uses feature-id not release-id in suite.json | QA re-run suite-activate if needed; gate verification works on feature ID | qa-dungeoncrawler |
| `dc-cr-conditions` re-reset causes dev to lose context | Very Low | Low — conditions has implementation notes; dev can re-pick in release-b | Document in conditions feature.md latest updates field | pm-dungeoncrawler |
| Scope cap mis-count after re-tagging | Very Low | Low — cap enforcement now reads Release: field correctly (post GAP-B-02 fix) | Verify count after commit | pm-dungeoncrawler |

## Rollback Trigger
- If re-tagging introduces incorrect metadata (wrong release ID, broken field format), revert the commit with `git revert <hash>` and re-apply manually.

## Monitoring
- What to watch post-correction: `grep -rl "Status: in_progress" features/dc-*/ | xargs grep "Release:" | grep -v "release-next"` — should return empty after fix.
- Dev-dungeoncrawler outbox: watch for completion reports on background-system, character-class, heritage-system, skill-system; ensure Gate 2 QA items are queued.
- 24h clock: auto-close fires ~2026-04-07T04:47Z; corrections must be committed before then.
- Where: `sessions/qa-dungeoncrawler/artifacts/auto-site-audit/latest/findings-summary.json` — post-correction audit for baseline.
