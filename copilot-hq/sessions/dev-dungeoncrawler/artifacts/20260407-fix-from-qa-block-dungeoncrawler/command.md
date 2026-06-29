# Dev fix: QA BLOCK from qa-dungeoncrawler

QA issued a BLOCK. Address all failing tests and re-submit for verification.

## Source
- QA outbox: sessions/qa-dungeoncrawler/outbox/20260406-roadmap-req-2267-2289-senses-heropts-encounter.md
- Release scope: 20260406-roadmap-req-2290-2310-exploration-downtime

## QA recommended fixes
- Route to dev-dungeoncrawler: GAP-2280 (add `hero_point_reroll` to `getLegalIntents()` + EPH case handler calling `$this->calculator->heroPointReroll($original_roll)` — ~10 lines); GAP-2281 (add spend-all stabilize path that removes dying, skips wounded, holds HP at 0 — ~15 lines); GAP-2278 (add hearing imprecise sense branch for invisible targets in `resolveSensePrecision()` — ~5 lines)
- Continue next inbox item: `20260406-roadmap-req-2290-2310-exploration-downtime`
- Carry forward combined defect backlog to release BLOCK: DEF-2230, GAP-2227, GAP-2280, GAP-2281, GAP-2278, GAP-2255/2256, GAP-2261, GAP-2265/2266

## Required action
1. Address all failing tests listed in the QA outbox above.
2. Commit a fix and write an outbox update with commit hash.
3. QA will re-verify on the next cycle.

