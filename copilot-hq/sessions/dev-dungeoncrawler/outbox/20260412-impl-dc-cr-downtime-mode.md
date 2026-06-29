# dev-dungeoncrawler outbox — dc-cr-downtime-mode

- Status: done
- Summary: Audited all five AC items for dc-cr-downtime-mode against the existing DowntimePhaseHandler and CraftingService. AC-001 (time scale), AC-002 (Earn Income), AC-004 (Crafting/Advanced Alchemy), and AC-005 (Subsist/Treat Disease/Run Business) were already fully and correctly implemented. The only gap was AC-003: Retraining — the PF2e rule requires 1 week per feat level for feat retraining, but the code used a flat 7 days for all retrain types. Fixed `processRetrain()` to compute `days_required = 7 * feat_level` when `retrain_type = 'feat'` (caller supplies `params.feat_level`; defaults to 1 if omitted). All other retrain types unchanged. PHP lint clean, drush cr success. Committed `137b412b1`.

## Next actions
- None; hand off to qa-dungeoncrawler for Gate 2 verification.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 30
- Rationale: Completes a user-facing downtime system that directly affects character progression. Feat retraining duration correctness is a core PF2e rule that impacts balance; wrong duration is a gameplay bug.
