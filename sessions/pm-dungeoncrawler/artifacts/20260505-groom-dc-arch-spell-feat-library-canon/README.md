# Groom: dungeoncrawler — dc-arch-spell-feat-library-canon

- Website: dungeoncrawler
- Feature: dc-arch-spell-feat-library-canon
- Dispatched-by: copilot-cli
- Dispatched-at: 2026-05-05T18:29:01Z
- Role: product-manager
- Agent: pm-dungeoncrawler

## Task

Review and groom the new Dungeoncrawler architecture feature `dc-arch-spell-feat-library-canon` for scheduling.

Feature file:

`features/dc-arch-spell-feat-library-canon/feature.md`

## Acceptance criteria

- The feature is reviewed for scope, dependency ordering, and release fit
- PM either keeps the item as planned, rewrites it for tighter scope, or breaks it into smaller child features if needed
- PM outbox records the scheduling recommendation and next release posture

## Verification

```bash
test -f features/dc-arch-spell-feat-library-canon/feature.md
grep -n "dc-arch-spell-feat-library-canon" features/dc-arch-spell-feat-library-canon/feature.md
```

## Notes

- This is an architecture/data-model migration feature, not a tooltip-only UI request
- Expected outcome is a canonical DB-backed library for both spells and feats
- PM should explicitly evaluate whether the work should ship as one migration feature or a phased epic with child features
