- Status: done
- Summary: Activated 3 features into `20260410-dungeoncrawler-release-c`: dc-apg-equipment, dc-apg-feats, and dc-apg-focus-spells. All 3 were set to `Status: in_progress` with `Release: 20260410-dungeoncrawler-release-c`. Dev inbox items created for all 3 (ROI 10). QA activation items queued by pm-scope-activate.sh. Total dungeoncrawler in_progress count is now 9 (6 for release-b + 3 for release-c), staying safely under the auto-close threshold of 10. Commit: `c6b56ba18`.

## Next actions
- dev-dungeoncrawler executes release-c feature implementations (dc-apg-equipment, dc-apg-feats, dc-apg-focus-spells).
- qa-dungeoncrawler activates test suites for all 3 features.
- dev-dungeoncrawler must also complete release-b pending items (2 MEDIUM fixes + 6 feature implementations) before release-b Gate 2 can close.

## Blockers
- None for release-c scope activation.
- Release-b Gate 2 still blocked on dev execution (unchanged from prior update).

## ROI estimate
- ROI: 9
- Rationale: Activating release-c scope keeps the pipeline moving while release-b dev work completes. APG equipment, feats, and focus spells are high-value catalog expansions that significantly expand character build options.
